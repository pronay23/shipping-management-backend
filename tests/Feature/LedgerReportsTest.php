<?php

use App\Models\ChartOfAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function storeInvoice(string $number, string $date, float $amount): void
{
    $rate = $amount / 100;

    test()->postJson('/api/invoices', [
        'invoice_number' => $number,
        'invoice_date' => $date,
        'customer_name' => 'ACI Logistics',
        'exchange_rate' => 110.0000,
        'items' => [
            ['key' => 'doc_fee', 'rate_usd' => 100.0000, 'rate_bdt' => $rate],
        ],
    ])->assertCreated();
}

function storeReceipt(string $number, string $date, int $invoiceId, float $paidAmount): void
{
    test()->postJson('/api/money-receipts', [
        'money_receipt_number' => $number,
        'money_receipt_date' => $date,
        'customer_name' => 'ACI Logistics',
        'total_bdt' => $paidAmount,
        'payment_term' => 'Cash',
        'invoices' => [
            ['invoice_id' => $invoiceId, 'paid_amount' => $paidAmount],
        ],
    ])->assertCreated();
}

function approveAllEntries(): void
{
    App\Models\JournalEntry::query()->get()->each(
        fn ($entry) => test()->postJson("/api/journal-entries/{$entry->id}/approve")->assertOk()
    );
}

function rowFor(array $rows, string $accountCode): array
{
    return collect($rows)->first(fn (array $row) => $row['account_code'] === $accountCode);
}

test('trial balance computes per-account balances from approved entries only', function () {
    storeInvoice('MR/IMP/BCLL/2100', '2026-08-10', 11000.0);
    $invoiceId = App\Models\Invoice::where('invoice_number', 'MR/IMP/BCLL/2100')->value('id');
    storeReceipt('MR/BCLL/2026/2100-A', '2026-08-11', $invoiceId, 4000.0);

    approveAllEntries();

    // A draft-only invoice never reaches the reports.
    storeInvoice('MR/IMP/BCLL/2101', '2026-08-11', 9999.0);

    $response = $this->getJson('/api/reports/trial-balance')->assertOk();

    $rows = $response->json('data');

    expect($response->json('totals.debit'))->toBe('15000.00');
    expect($response->json('totals.credit'))->toBe('15000.00');
    expect($response->json('totals.is_balanced'))->toBeTrue();

    $cash = rowFor($rows, '1010');
    expect($cash['debit_total'])->toBe('4000.00');
    expect($cash['balance'])->toBe('4000.00');

    $ar = rowFor($rows, '1100.001');
    expect($ar['debit_total'])->toBe('11000.00');
    expect($ar['credit_total'])->toBe('4000.00');
    expect($ar['balance'])->toBe('7000.00');

    $revenue = rowFor($rows, '4001');
    expect($revenue['credit_total'])->toBe('11000.00');
    expect($revenue['balance'])->toBe('-11000.00');

    expect(collect($rows)->every(fn (array $row) => is_string($row['balance'])))->toBeTrue();
});

test('trial balance paginates and respects the entry-date range as period movement', function () {
    storeInvoice('MR/IMP/BCLL/2200', '2026-08-10', 11000.0);
    $invoiceId = App\Models\Invoice::where('invoice_number', 'MR/IMP/BCLL/2200')->value('id');
    storeReceipt('MR/BCLL/2026/2200-A', '2026-08-11', $invoiceId, 4000.0);
    storeReceipt('MR/BCLL/2026/2200-B', '2026-08-12', $invoiceId, 3000.0);

    approveAllEntries();

    $accountCount = ChartOfAccount::count();

    $this->getJson('/api/reports/trial-balance?per_page=5&page=1')
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.total', $accountCount)
        ->assertJsonPath('meta.per_page', 5)
        ->assertJsonPath('meta.last_page', intdiv($accountCount + 4, 5));

    // Movement strictly inside 2026-08-12..: only the second receipt.
    $this->getJson('/api/reports/trial-balance?from=2026-08-12')
        ->assertOk()
        ->assertJsonPath('totals.debit', '3000.00')
        ->assertJsonPath('totals.credit', '3000.00');
});

test('account ledger returns a running balance across pages with opening balance support', function () {
    storeInvoice('MR/IMP/BCLL/2300', '2026-08-10', 11000.0);
    $invoiceId = App\Models\Invoice::where('invoice_number', 'MR/IMP/BCLL/2300')->value('id');
    storeReceipt('MR/BCLL/2026/2300-A', '2026-08-11', $invoiceId, 4000.0);
    storeReceipt('MR/BCLL/2026/2300-B', '2026-08-12', $invoiceId, 3000.0);

    approveAllEntries();

    $ar = ChartOfAccount::where('code', '1100.001')->firstOrFail();

    $pageOne = $this->getJson("/api/accounts/{$ar->id}/ledger?per_page=2&page=1")->assertOk();

    expect($pageOne->json('account.code'))->toBe('1100.001');
    expect($pageOne->json('data.0.journal_entry_id'))->toBe((int) App\Models\JournalEntry::orderBy('id')->first()->id);
    expect($pageOne->json('data.0.debit'))->toBe('11000.00');
    expect($pageOne->json('data.0.running_balance'))->toBe('11000.00');
    expect($pageOne->json('data.1.running_balance'))->toBe('7000.00');
    expect($pageOne->json('totals.opening_balance'))->toBe('0.00');
    expect($pageOne->json('totals.closing_balance'))->toBe('4000.00');

    $pageTwo = $this->getJson("/api/accounts/{$ar->id}/ledger?per_page=2&page=2")->assertOk();

    expect($pageTwo->json('data'))->toHaveCount(1);
    expect($pageTwo->json('data.0.running_balance'))->toBe('4000.00');

    $midAugust = $this->getJson("/api/accounts/{$ar->id}/ledger?from=2026-08-12")->assertOk();

    expect($midAugust->json('totals.opening_balance'))->toBe('7000.00');
    expect($midAugust->json('data.0.running_balance'))->toBe('4000.00');
    expect($midAugust->json('totals.period_debit'))->toBe('0.00');
    expect($midAugust->json('totals.period_credit'))->toBe('3000.00');
});

test('a void/reversal pair nets to zero in reports while keeping history visible', function () {
    storeInvoice('MR/IMP/BCLL/2400', '2026-08-10', 5000.0);
    approveAllEntries();

    $entry = App\Models\JournalEntry::firstOrFail();
    $this->postJson("/api/journal-entries/{$entry->id}/void")->assertNoContent();

    // Both halves of the pair stay in the ledger: the voided original
    // (credit) and its approved reversal (debit), canceling each other.
    $revenue = ChartOfAccount::where('code', '4001')->firstOrFail();
    $ledger = $this->getJson("/api/accounts/{$revenue->id}/ledger")->assertOk();

    expect($ledger->json('meta.total'))->toBe(2);
    expect($ledger->json('data.0.credit'))->toBe('5000.00');
    expect($ledger->json('data.1.debit'))->toBe('5000.00');
    expect($ledger->json('data.1.running_balance'))->toBe('0.00');

    // Net effect across the books is zero.
    $trialBalance = $this->getJson('/api/reports/trial-balance')->assertOk();
    $arRow = collect($trialBalance->json('data'))->firstWhere('account_code', '1100.001');
    $revenueRow = collect($trialBalance->json('data'))->firstWhere('account_code', '4001');

    expect($arRow['balance'])->toBe('0.00');
    expect($revenueRow['balance'])->toBe('0.00');
});

test('AR aging lists open invoices bucketed by age and excludes settled ones', function () {
    storeInvoice('MR/IMP/BCLL/2500', '2026-08-01', 11000.0);
    $openInvoice = App\Models\Invoice::where('invoice_number', 'MR/IMP/BCLL/2500')->value('id');
    storeInvoice('MR/IMP/BCLL/2501', '2026-08-05', 5500.0);
    $paidInvoice = App\Models\Invoice::where('invoice_number', 'MR/IMP/BCLL/2501')->value('id');

    storeReceipt('MR/BCLL/2026/2500-A', '2026-08-02', $openInvoice, 4000.0);
    storeReceipt('MR/BCLL/2026/2501-A', '2026-08-06', $paidInvoice, 5500.0);

    approveAllEntries();

    $aging = $this->getJson('/api/reports/ar-aging?as_of=2026-08-20')->assertOk();

    expect($aging->json('meta.as_of'))->toBe('2026-08-20');
    expect($aging->json('meta.count'))->toBe(1);
    expect($aging->json('totals.total_outstanding'))->toBe('7000.00');

    $row = $aging->json('data.0');
    expect($row['invoice_id'])->toBe((int) $openInvoice);
    expect($row['invoice_number'])->toBe('MR/IMP/BCLL/2500');
    expect($row['outstanding'])->toBe('7000.00');
    expect($row['days_outstanding'])->toBe(19);
    expect($row['bucket'])->toBe('0-30');

    $agedInvoiceIds = collect($aging->json('data'))->pluck('invoice_id');
    expect($agedInvoiceIds)->not->toContain((int) $paidInvoice);
});

function plSectionRow(array $report, string $section, string $accountCode): array
{
    return collect(data_get($report, "data.{$section}"))
        ->first(fn (array $row) => $row['account_code'] === $accountCode);
}

function createApprovedExpenseEntry(string $voucher, string $date, float $amount): void
{
    $expenseAccount = App\Models\ChartOfAccount::factory()->create(['code' => '5001', 'name' => 'Office Rent', 'type' => 'expense']);
    $cashAccount = App\Models\ChartOfAccount::where('code', '1010')->firstOrFail();

    $entry = App\Models\JournalEntry::factory()->create([
        'voucher_number' => $voucher,
        'entry_date' => $date,
        'status' => App\Models\JournalEntry::STATUS_APPROVED,
        'total_debit' => $amount,
        'total_credit' => $amount,
    ]);

    App\Models\JournalEntryLine::factory()->create([
        'journal_entry_id' => $entry->id,
        'account_id' => $expenseAccount->id,
        'debit' => $amount,
        'credit' => 0,
    ]);
    App\Models\JournalEntryLine::factory()->create([
        'journal_entry_id' => $entry->id,
        'account_id' => $cashAccount->id,
        'debit' => 0,
        'credit' => $amount,
    ]);
}

test('profit and loss reports approved revenue with defaults and resolved range', function () {
    storeInvoice('MR/IMP/BCLL/2600', '2026-08-10', 11000.0);
    approveAllEntries();

    // Draft-only activity never reaches the P&L.
    storeInvoice('MR/IMP/BCLL/2601', '2026-08-11', 9999.0);

    $pl = $this->getJson('/api/reports/profit-and-loss')->assertOk();

    expect($pl->json('meta.from'))->toBe(now()->startOfYear()->toDateString());
    expect($pl->json('meta.to'))->toBe(now()->toDateString());

    $docFee = plSectionRow($pl->json(), 'revenue', '4001');
    expect($docFee['account_name'])->toBe('DOC Fee Income');
    expect($docFee['amount'])->toBe('11000.00');

    expect($pl->json('totals.total_revenue'))->toBe('11000.00');
    expect($pl->json('totals.total_expense'))->toBe('0.00');
    expect($pl->json('totals.net_profit'))->toBe('11000.00');

    // Every revenue/expense account is listed, zero activity included.
    $fclDg = plSectionRow($pl->json(), 'revenue', '4007');
    expect($fclDg['amount'])->toBe('0.00');
});

test('profit and loss excludes balance-sheet accounts and includes expenses with sign normalization', function () {
    storeInvoice('MR/IMP/BCLL/2700', '2026-08-10', 11000.0);
    approveAllEntries();
    createApprovedExpenseEntry('JV-TEST-0001', '2026-08-12', 3000.0);

    $pl = $this->getJson('/api/reports/profit-and-loss')->assertOk();

    $allCodes = collect($pl->json('data.revenue'))->merge($pl->json('data.expenses'))->pluck('account_code');
    expect($allCodes)->toContain('4001')->toContain('5001');
    expect($allCodes->filter(fn ($code) => in_array($code, ['1010', '1020', '1100', '3000', '1100.001'])))->toBeEmpty();

    $rent = plSectionRow($pl->json(), 'expenses', '5001');
    expect($rent['amount'])->toBe('3000.00');

    expect($pl->json('totals.total_revenue'))->toBe('11000.00');
    expect($pl->json('totals.total_expense'))->toBe('3000.00');
    expect($pl->json('totals.net_profit'))->toBe('8000.00');

    collect($pl->json('data.revenue'))->merge($pl->json('data.expenses'))
        ->each(fn (array $row) => expect($row['amount'])->toBeString());
});

test('profit and loss nets voided invoices against their reversals to zero', function () {
    storeInvoice('MR/IMP/BCLL/2800', '2026-08-10', 5000.0);
    approveAllEntries();

    $entry = App\Models\JournalEntry::firstOrFail();
    $this->postJson("/api/journal-entries/{$entry->id}/void")->assertNoContent();

    $this->getJson('/api/reports/profit-and-loss')
        ->assertOk()
        ->assertJsonPath('totals.total_revenue', '0.00')
        ->assertJsonPath('totals.net_profit', '0.00');

    expect(plSectionRow($this->getJson('/api/reports/profit-and-loss')->json(), 'revenue', '4001')['amount'])->toBe('0.00');
});

test('profit and loss respects the entry-date range', function () {
    storeInvoice('MR/IMP/BCLL/2900', '2026-08-10', 11000.0);
    approveAllEntries();

    $beforeRange = $this->getJson('/api/reports/profit-and-loss?from=2026-01-01&to=2026-07-31')->assertOk();
    expect($beforeRange->json('totals.total_revenue'))->toBe('0.00');
    expect($beforeRange->json('meta.from'))->toBe('2026-01-01');
    expect($beforeRange->json('meta.to'))->toBe('2026-07-31');

    $insideRange = $this->getJson('/api/reports/profit-and-loss?from=2026-08-10&to=2026-08-10')->assertOk();
    expect($insideRange->json('totals.total_revenue'))->toBe('11000.00');
    expect($insideRange->json('totals.net_profit'))->toBe('11000.00');
});
