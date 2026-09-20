<?php

use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Services\AccountService;
use App\Services\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function storeInvoicePayload(string $number, string $date, array $items): array
{
    return [
        'invoice_number' => $number,
        'invoice_date' => $date,
        'customer_name' => 'ACI Logistics',
        'exchange_rate' => 110.0000,
        'items' => $items,
    ];
}

test('zero-value invoice items are dropped from journal lines instead of posting zero lines', function () {
    $this->postJson('/api/invoices', storeInvoicePayload('MR/IMP/BCLL/1100', '2026-08-19', [
        ['key' => 'doc_fee', 'rate_usd' => 100.0000, 'rate_bdt' => 110.0000],
        ['key' => 'cleaning', 'rate_usd' => 0.0000, 'rate_bdt' => 0.0000],
    ]))->assertCreated();

    $entry = JournalEntry::firstOrFail();
    $lines = $entry->lines;

    expect($lines)->toHaveCount(2);
    expect($entry->total_debit)->toBe('11000.00');
    expect($lines->every(fn ($line) => bccomp((string) $line->debit, '0', 2) === 1 || bccomp((string) $line->credit, '0', 2) === 1))->toBeTrue();
});

test('an invoice with no billable items creates no journal entry', function () {
    $this->postJson('/api/invoices', storeInvoicePayload('MR/IMP/BCLL/1200', '2026-08-19', [
        ['key' => 'doc_fee', 'rate_usd' => 0.0000, 'rate_bdt' => 0.0000],
    ]))->assertCreated();

    expect(JournalEntry::count())->toBe(0);
});

test('re-saving a document after void posts a fresh entry and never resurrects the voided one', function () {
    $this->postJson('/api/invoices', storeInvoicePayload('MR/IMP/BCLL/1300', '2026-08-19', [
        ['key' => 'doc_fee', 'rate_usd' => 100.0000, 'rate_bdt' => 110.0000],
    ]))->assertCreated();

    $original = JournalEntry::firstOrFail();
    $this->postJson("/api/journal-entries/{$original->id}/approve")->assertOk();
    $this->postJson("/api/journal-entries/{$original->id}/void")->assertNoContent();

    $invoice = Invoice::where('invoice_number', 'MR/IMP/BCLL/1300')->firstOrFail();

    $fresh = app(JournalService::class)->createInvoiceEntry($invoice->refresh());

    expect($fresh)->not->toBeNull();
    expect((int) $fresh->id)->not->toBe((int) $original->id);
    expect($fresh->status)->toBe(JournalEntry::STATUS_DRAFT);
    expect($fresh->voucher_number)->not->toBe($original->voucher_number);
    expect($fresh->reverses_journal_entry_id)->toBeNull();

    // Exactly one active non-reversal entry for the source: the voided
    // original plus its reversal remain as history, the fresh draft is new.
    $sourceEntries = JournalEntry::where('source_type', 'invoice')->where('source_id', $invoice->id);
    expect($sourceEntries->count())->toBe(3);
    expect((clone $sourceEntries)->whereIn('status', [JournalEntry::STATUS_DRAFT, JournalEntry::STATUS_APPROVED])->whereNull('reverses_journal_entry_id')->count())->toBe(1);
    expect($original->fresh()->status)->toBe(JournalEntry::STATUS_VOIDED);
});

test('approving an entry dated on or before the period lock date is rejected', function () {
    config()->set('accounting.period_lock_date', '2026-08-15');

    $this->postJson('/api/invoices', storeInvoicePayload('MR/IMP/BCLL/1400', '2026-08-15', [
        ['key' => 'doc_fee', 'rate_usd' => 100.0000, 'rate_bdt' => 110.0000],
    ]))->assertCreated();

    $locked = JournalEntry::firstOrFail();

    $this->postJson("/api/journal-entries/{$locked->id}/approve")
        ->assertUnprocessable()
        ->assertJsonPath('errors.journal_entry.0', 'Books closed on/before 2026-08-15.');

    expect($locked->fresh()->status)->toBe(JournalEntry::STATUS_DRAFT);
});

test('entries dated after the period lock date still approve and drafts stay editable while locked', function () {
    config()->set('accounting.period_lock_date', '2026-08-15');

    $this->postJson('/api/invoices', storeInvoicePayload('MR/IMP/BCLL/1500', '2026-08-16', [
        ['key' => 'doc_fee', 'rate_usd' => 100.0000, 'rate_bdt' => 110.0000],
    ]))->assertCreated();

    $entry = JournalEntry::firstOrFail();

    $this->putJson("/api/invoices/{$entry->source_id}", [
        'invoice_number' => 'MR/IMP/BCLL/1500',
        'invoice_date' => '2026-08-16',
        'items' => [
            ['key' => 'admin_fee', 'rate_usd' => 20.0000, 'rate_bdt' => 110.0000],
        ],
    ])->assertOk();

    $this->postJson("/api/journal-entries/{$entry->id}/approve")->assertOk();
    expect($entry->fresh()->status)->toBe(JournalEntry::STATUS_APPROVED);
});

test('voiding an entry inside a closed period is rejected with a clear message', function () {
    config()->set('accounting.period_lock_date', null);

    $this->postJson('/api/invoices', storeInvoicePayload('MR/IMP/BCLL/1600', '2026-08-10', [
        ['key' => 'doc_fee', 'rate_usd' => 100.0000, 'rate_bdt' => 110.0000],
    ]))->assertCreated();

    $entry = JournalEntry::firstOrFail();
    $this->postJson("/api/journal-entries/{$entry->id}/approve")->assertOk();

    config()->set('accounting.period_lock_date', '2026-08-10');

    $this->postJson("/api/journal-entries/{$entry->id}/void")
        ->assertUnprocessable()
        ->assertJsonPath('errors.journal_entry.0', 'Books closed on/before 2026-08-10.');

    expect($entry->fresh()->status)->toBe(JournalEntry::STATUS_APPROVED);
    expect(JournalEntry::count())->toBe(1);
});

test('approving or voiding a voided entry returns a clean conflict instead of mutating history', function () {
    $this->postJson('/api/invoices', storeInvoicePayload('MR/IMP/BCLL/1700', '2026-08-19', [
        ['key' => 'doc_fee', 'rate_usd' => 100.0000, 'rate_bdt' => 110.0000],
    ]))->assertCreated();

    $entry = JournalEntry::firstOrFail();
    $this->postJson("/api/journal-entries/{$entry->id}/approve")->assertOk();
    $this->postJson("/api/journal-entries/{$entry->id}/void")->assertNoContent();

    $this->postJson("/api/journal-entries/{$entry->id}/approve")->assertStatus(409);
    $this->postJson("/api/journal-entries/{$entry->id}/void")->assertStatus(409);
});

test('the balance assertion enforces one side per line and rejects zero totals', function () {
    $service = new JournalService(new AccountService);
    $assertBalanced = new ReflectionMethod($service, 'assertBalanced');

    expect(fn () => $assertBalanced->invoke($service, [
        ['debit' => '100.00', 'credit' => '100.00'],
    ]))->toThrow(ValidationException::class);

    expect(fn () => $assertBalanced->invoke($service, [
        ['debit' => '0.00', 'credit' => '0.00'],
    ]))->toThrow(ValidationException::class);

    expect(fn () => $assertBalanced->invoke($service, []))->toThrow(ValidationException::class);

    expect(fn () => $assertBalanced->invoke($service, [
        ['debit' => '100.00', 'credit' => '50.00'],
    ]))->toThrow(ValidationException::class);

    $assertBalanced->invoke($service, [
        ['debit' => '1357.40', 'credit' => '0'],
        ['debit' => '0', 'credit' => '1000.00'],
        ['debit' => '0', 'credit' => '357.40'],
    ]);
});
