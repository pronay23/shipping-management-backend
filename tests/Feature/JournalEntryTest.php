<?php

use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('storing an invoice auto-creates a balanced draft journal entry', function () {
    $this->postJson('/api/invoices', [
        'invoice_number' => 'MR/IMP/BCLL/100',
        'invoice_date' => '2026-08-19',
        'customer_name' => 'ACI Logistics',
        'exchange_rate' => 110.0000,
        'items' => [
            ['key' => 'doc_fee', 'label' => 'DOC Fee', 'rate_usd' => 100.0000, 'rate_bdt' => 110.0000],
            ['key' => 'cleaning', 'label' => 'Cleaning', 'rate_usd' => 50.0000, 'rate_bdt' => 110.0000],
        ],
    ])->assertCreated();

    $entry = JournalEntry::firstOrFail();

    expect($entry->source_type)->toBe('invoice');
    expect($entry->status)->toBe(JournalEntry::STATUS_DRAFT);
    expect($entry->total_debit)->toBe('16500.00');
    expect($entry->total_credit)->toBe('16500.00');
    expect($entry->voucher_number)->toBe('JV-2026-0001');

    $arAccount = ChartOfAccount::where('name', 'AR – ACI Logistics')->first();
    expect($arAccount)->not->toBeNull();
    expect($arAccount->code)->toBe('1100.001');
    expect($arAccount->parent_id)->toBe(ChartOfAccount::where('code', '1100')->value('id'));

    $lines = $entry->lines;

    expect($lines)->toHaveCount(3);
    expect($lines->first()->account_id)->toBe($arAccount->id);
    expect($lines->first()->debit)->toBe('16500.00');
    expect($lines->first()->invoice_id)->toBe($entry->source_id);
    expect($lines->first()->reference)->toBe('MR/IMP/BCLL/100');

    expect($lines->get(1)->account_id)->toBe(ChartOfAccount::where('code', '4001')->value('id'));
    expect($lines->get(1)->credit)->toBe('11000.00');
    expect($lines->get(2)->account_id)->toBe(ChartOfAccount::where('code', '4003')->value('id'));
    expect($lines->get(2)->credit)->toBe('5500.00');
});

test('storing a money receipt auto-creates a balanced draft journal entry', function () {
    $invoice = Invoice::factory()->create([
        'invoice_number' => 'MR/IMP/BCLL/200',
        'customer_name' => 'ACI Logistics',
        'total_bdt' => 100000.00,
    ]);

    $this->postJson('/api/money-receipts', [
        'money_receipt_number' => 'MR/BCLL/2026/0819-101530',
        'money_receipt_date' => '2026-08-19',
        'customer_name' => 'ACI Logistics',
        'total_bdt' => 50000.00,
        'payment_term' => 'Bank Transfer',
        'invoices' => [
            ['invoice_id' => $invoice->id, 'paid_amount' => 50000.00],
        ],
    ])->assertCreated();

    $entry = JournalEntry::where('source_type', 'money_receipt')->firstOrFail();

    expect($entry->status)->toBe(JournalEntry::STATUS_DRAFT);
    expect($entry->total_debit)->toBe('50000.00');
    expect($entry->total_credit)->toBe('50000.00');

    $lines = $entry->lines;

    expect($lines)->toHaveCount(2);
    expect($lines->first()->account_id)->toBe(ChartOfAccount::where('code', '1020')->value('id'));
    expect($lines->first()->debit)->toBe('50000.00');
    expect($lines->first()->note)->toBe('Bank Transfer');

    expect($lines->get(1)->account_id)->toBe(ChartOfAccount::where('name', 'AR – ACI Logistics')->value('id'));
    expect($lines->get(1)->credit)->toBe('50000.00');
    expect($lines->get(1)->invoice_id)->toBe($invoice->id);
    expect($lines->get(1)->reference)->toBe('MR/BCLL/2026/0819-101530');
});

test('cash payment term posts to the cash account', function () {
    $invoice = Invoice::factory()->create(['total_bdt' => 100000.00]);

    $this->postJson('/api/money-receipts', [
        'money_receipt_number' => 'MR/BCLL/2026/0819-101531',
        'customer_name' => 'ACI Logistics',
        'total_bdt' => 40000.00,
        'payment_term' => 'Cash',
        'invoices' => [
            ['invoice_id' => $invoice->id, 'paid_amount' => 40000.00],
        ],
    ])->assertCreated();

    $entry = JournalEntry::where('source_type', 'money_receipt')->firstOrFail();

    expect($entry->lines->first()->account_id)->toBe(ChartOfAccount::where('code', '1010')->value('id'));
});

test('money receipt entry posts only the actually paid amount', function () {
    $invoice = Invoice::factory()->create(['total_bdt' => 100000.00]);

    $this->postJson('/api/money-receipts', [
        'money_receipt_number' => 'MR/BCLL/2026/0819-101532',
        'customer_name' => 'ACI Logistics',
        'total_bdt' => 50000.00,
        'payment_term' => 'Cash',
        'invoices' => [
            ['invoice_id' => $invoice->id, 'paid_amount' => 40000.00],
        ],
    ])->assertCreated();

    $entry = JournalEntry::where('source_type', 'money_receipt')->firstOrFail();

    expect($entry->total_debit)->toBe('40000.00');
    expect($entry->total_credit)->toBe('40000.00');
});

test('journal entries can be listed newest first with a line count', function () {
    $this->postJson('/api/invoices', [
        'invoice_number' => 'MR/IMP/BCLL/300',
        'invoice_date' => '2026-08-19',
        'customer_name' => 'ACI Logistics',
        'exchange_rate' => 110.0000,
        'items' => [
            ['key' => 'doc_fee', 'label' => 'DOC Fee', 'rate_usd' => 100.0000, 'rate_bdt' => 110.0000],
        ],
    ])->assertCreated();

    $invoice = Invoice::firstOrFail();

    $this->postJson('/api/money-receipts', [
        'money_receipt_number' => 'MR/BCLL/2026/0819-101533',
        'customer_name' => 'ACI Logistics',
        'total_bdt' => 11000.00,
        'payment_term' => 'Cash',
        'invoices' => [
            ['invoice_id' => $invoice->id, 'paid_amount' => 11000.00],
        ],
    ])->assertCreated();

    $this->getJson('/api/journal-entries')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.source_type', 'money_receipt')
        ->assertJsonPath('0.line_count', 2)
        ->assertJsonPath('1.source_type', 'invoice');
});

test('a journal entry can be shown with denormalized line details', function () {
    $this->postJson('/api/invoices', [
        'invoice_number' => 'MR/IMP/BCLL/400',
        'invoice_date' => '2026-08-19',
        'customer_name' => 'ACI Logistics',
        'exchange_rate' => 110.0000,
        'items' => [
            ['key' => 'doc_fee', 'label' => 'DOC Fee', 'rate_usd' => 100.0000, 'rate_bdt' => 110.0000],
        ],
    ])->assertCreated();

    $entry = JournalEntry::firstOrFail();

    $this->getJson("/api/journal-entries/{$entry->id}")
        ->assertOk()
        ->assertJsonPath('voucher_number', $entry->voucher_number)
        ->assertJsonPath('line_count', 2)
        ->assertJsonPath('lines.0.account_code', '1100.001')
        ->assertJsonPath('lines.0.account_type', 'asset')
        ->assertJsonPath('lines.0.invoice_number', 'MR/IMP/BCLL/400')
        ->assertJsonPath('lines.1.account_code', '4001');
});

test('approving a draft entry locks it and rejects repeat approval', function () {
    $this->postJson('/api/invoices', [
        'invoice_number' => 'MR/IMP/BCLL/500',
        'invoice_date' => '2026-08-19',
        'customer_name' => 'ACI Logistics',
        'exchange_rate' => 110.0000,
        'items' => [
            ['key' => 'doc_fee', 'rate_usd' => 100.0000, 'rate_bdt' => 110.0000],
        ],
    ])->assertCreated();

    $entry = JournalEntry::firstOrFail();

    $this->postJson("/api/journal-entries/{$entry->id}/approve")
        ->assertOk()
        ->assertJsonPath('status', JournalEntry::STATUS_APPROVED);

    expect($entry->fresh()->status)->toBe(JournalEntry::STATUS_APPROVED);

    $this->postJson("/api/journal-entries/{$entry->id}/approve")
        ->assertStatus(409);
});

test('voiding a draft entry deletes it', function () {
    $this->postJson('/api/invoices', [
        'invoice_number' => 'MR/IMP/BCLL/600',
        'invoice_date' => '2026-08-19',
        'customer_name' => 'ACI Logistics',
        'exchange_rate' => 110.0000,
        'items' => [
            ['key' => 'doc_fee', 'rate_usd' => 100.0000, 'rate_bdt' => 110.0000],
        ],
    ])->assertCreated();

    $entry = JournalEntry::firstOrFail();

    $this->postJson("/api/journal-entries/{$entry->id}/void")
        ->assertNoContent();

    expect(JournalEntry::find($entry->id))->toBeNull();
});

test('voiding an approved entry creates a reversing entry', function () {
    $this->postJson('/api/invoices', [
        'invoice_number' => 'MR/IMP/BCLL/700',
        'invoice_date' => '2026-08-19',
        'customer_name' => 'ACI Logistics',
        'exchange_rate' => 110.0000,
        'items' => [
            ['key' => 'doc_fee', 'rate_usd' => 100.0000, 'rate_bdt' => 110.0000],
        ],
    ])->assertCreated();

    $entry = JournalEntry::firstOrFail();

    $this->postJson("/api/journal-entries/{$entry->id}/approve")->assertOk();

    $this->postJson("/api/journal-entries/{$entry->id}/void")->assertNoContent();

    $original = $entry->fresh();
    expect($original->status)->toBe(JournalEntry::STATUS_VOIDED);
    expect($original->voided_at)->not->toBeNull();

    $reversal = JournalEntry::where('memo', "Reversal of {$entry->voucher_number}")->firstOrFail();
    expect($reversal->status)->toBe(JournalEntry::STATUS_APPROVED);
    expect($reversal->source_type)->toBe('invoice');
    expect($reversal->source_id)->toBe($entry->source_id);
    expect($reversal->total_debit)->toBe($entry->total_credit);
    expect($reversal->total_credit)->toBe($entry->total_debit);
    expect($reversal->lines->first()->debit)->toBe('0.00');
    expect($reversal->lines->first()->credit)->toBe('11000.00');
});

test('editing an invoice after its entry is approved is blocked', function () {
    $this->postJson('/api/invoices', [
        'invoice_number' => 'MR/IMP/BCLL/800',
        'invoice_date' => '2026-08-19',
        'customer_name' => 'ACI Logistics',
        'exchange_rate' => 110.0000,
        'items' => [
            ['key' => 'doc_fee', 'rate_usd' => 100.0000, 'rate_bdt' => 110.0000],
        ],
    ])->assertCreated();

    $invoice = Invoice::firstOrFail();
    $entry = JournalEntry::firstOrFail();

    $this->postJson("/api/journal-entries/{$entry->id}/approve")->assertOk();

    $this->putJson("/api/invoices/{$invoice->id}", [
        'invoice_number' => 'MR/IMP/BCLL/800',
        'invoice_date' => '2026-08-19',
        'items' => [
            ['key' => 'doc_fee', 'rate_usd' => 200.0000, 'rate_bdt' => 110.0000],
        ],
    ])->assertUnprocessable();
});

test('deleting an invoice after its entry is approved is blocked', function () {
    $this->postJson('/api/invoices', [
        'invoice_number' => 'MR/IMP/BCLL/900',
        'invoice_date' => '2026-08-19',
        'customer_name' => 'ACI Logistics',
        'exchange_rate' => 110.0000,
        'items' => [
            ['key' => 'doc_fee', 'rate_usd' => 100.0000, 'rate_bdt' => 110.0000],
        ],
    ])->assertCreated();

    $invoice = Invoice::firstOrFail();
    $entry = JournalEntry::firstOrFail();

    $this->postJson("/api/journal-entries/{$entry->id}/approve")->assertOk();

    $this->deleteJson("/api/invoices/{$invoice->id}")->assertUnprocessable();
});