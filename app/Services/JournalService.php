<?php

namespace App\Services;

use App\Models\ApInvoice;
use App\Models\ApPayment;
use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\MoneyReceipt;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class JournalService
{
    /**
     * Invoice item keys mapped to their revenue chart-of-account codes.
     *
     * @var array<string, string>
     */
    private const REVENUE_MAP = [
        'doc_fee' => '4001',
        'admin_fee' => '4002',
        'cleaning' => '4003',
        'survey' => '4004',
        'lift_on_20' => '4005',
        'lift_on_40' => '4005',
        'det_20' => '4006',
        'det_40' => '4006',
        'fcl_dg' => '4007',
        'misc' => '4008',
    ];

    /**
     * Expense account codes for AP invoice item descriptions.
     *
     * @var array<string, string>
     */
    private const EXPENSE_MAP = [
        'ocean_freight' => '5001',
        'terminal_handling' => '5002',
        'port_charges' => '5003',
        'cnf_commission' => '5004',
        'customs_duty' => '5005',
        'transport' => '5006',
        'warehouse' => '5007',
        'insurance' => '5008',
        'office_supplies' => '5009',
        'misc' => '5099',
    ];

    public function __construct(public AccountService $accounts) {}

    /**
     * Create (or refresh) the draft journal entry for an invoice.
     */
    public function createInvoiceEntry(Invoice $invoice): ?JournalEntry
    {
        return $this->createSourceEntry($invoice, 'invoice');
    }

    /**
     * Create (or refresh) the draft journal entry for a money receipt.
     */
    public function createMoneyReceiptEntry(MoneyReceipt $moneyReceipt): ?JournalEntry
    {
        return $this->createSourceEntry($moneyReceipt, 'money_receipt');
    }

    /**
     * Create (or refresh) the draft journal entry for an AP invoice.
     */
    public function createApInvoiceEntry(ApInvoice $apInvoice): ?JournalEntry
    {
        return $this->createApSourceEntry($apInvoice, 'ap_invoice');
    }

    /**
     * Create (or refresh) the draft journal entry for an AP payment.
     */
    public function createApPaymentEntry(ApPayment $apPayment): ?JournalEntry
    {
        return $this->createApSourceEntry($apPayment, 'ap_payment');
    }

    /**
     * Regenerate a draft entry's lines from its source document and refresh
     * the memo, dates, and totals. Runs inside a transaction.
     */
    public function post(JournalEntry $entry): JournalEntry
    {
        return DB::transaction(function () use ($entry) {
            $source = $this->sourceFor($entry);
            $lines = match (true) {
                $source instanceof Invoice => $this->invoiceEntryLines($source),
                $source instanceof MoneyReceipt => $this->moneyReceiptEntryLines($source),
                $source instanceof ApInvoice => $this->apInvoiceEntryLines($source),
                $source instanceof ApPayment => $this->apPaymentEntryLines($source),
            };

            $this->assertBalanced($lines);

            $entry->lines()->delete();
            $entry->lines()->createMany($lines);

            $entry->update([
                'memo' => $this->memoFor($source),
                'entry_date' => $this->entryDateFor($source),
                'total_debit' => $this->sumColumn($lines, 'debit'),
                'total_credit' => $this->sumColumn($lines, 'credit'),
                'status' => JournalEntry::STATUS_DRAFT,
                'updated_by' => $this->currentUserId(),
            ]);

            return $entry->fresh('lines');
        });
    }

    /**
     * Approve a draft entry, locking its row so concurrent approvals lose
     * with a clean 409 instead of racing.
     */
    public function approve(JournalEntry $entry, ?int $userId = null): JournalEntry
    {
        return DB::transaction(function () use ($entry, $userId) {
            $entry = $this->lockEntry($entry);

            if ($entry->status !== JournalEntry::STATUS_DRAFT) {
                throw new HttpException(409, 'Only draft journal entries can be approved.');
            }

            $this->assertPeriodOpen($entry->entry_date);

            $entry->update([
                'status' => JournalEntry::STATUS_APPROVED,
                'updated_by' => $userId ?? $this->currentUserId(),
            ]);

            return $entry->fresh('lines');
        });
    }

    /**
     * Void an entry. Draft entries are deleted (no ledger impact). Approved
     * entries get an approved reversing entry and are then marked voided —
     * all inside one transaction with the row locked.
     */
    public function void(JournalEntry $entry, ?int $userId = null): void
    {
        DB::transaction(function () use ($entry, $userId) {
            $entry = $this->lockEntry($entry);

            if ($entry->status === JournalEntry::STATUS_VOIDED) {
                throw new HttpException(409, 'Journal entry is already voided.');
            }

            $this->assertPeriodOpen($entry->entry_date);

            if ($entry->status === JournalEntry::STATUS_DRAFT) {
                $entry->delete();

                return;
            }

            $userId = $userId ?? $this->currentUserId();

            $lines = $entry->lines->map(fn ($line) => [
                'account_id' => $line->account_id,
                'invoice_id' => $line->invoice_id,
                'reference' => $line->reference,
                'debit' => $line->credit,
                'credit' => $line->debit,
                'note' => $line->note,
            ])->all();

            $reversal = new JournalEntry([
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'reverses_journal_entry_id' => $entry->id,
                'entry_date' => now()->toDateString(),
                'memo' => "Reversal of {$entry->voucher_number}",
                'total_debit' => $entry->total_credit,
                'total_credit' => $entry->total_debit,
                'voucher_number' => $this->nextVoucherNumber(now()->toDateString()),
                'status' => JournalEntry::STATUS_APPROVED,
                'created_by' => $userId,
            ]);
            $reversal->save();
            $reversal->lines()->createMany($lines);

            $entry->update([
                'status' => JournalEntry::STATUS_VOIDED,
                'voided_at' => now(),
                'voided_by' => $userId,
            ]);
        });
    }

    /**
     * Generate the next `JV-{year}-{seq}` voucher number inside the current
     * transaction, locking the latest row to prevent duplicates.
     */
    public function nextVoucherNumber(string $entryDate): string
    {
        $year = Carbon::parse($entryDate)->format('Y');

        $last = JournalEntry::whereYear('entry_date', $year)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('voucher_number');

        $sequence = $last === null ? 1 : ((int) Str::afterLast($last, '-')) + 1;

        return sprintf('JV-%s-%04d', $year, $sequence);
    }

    /**
     * Create the draft entry for a source document, or refresh the existing
     * draft when the document is edited. The existence check takes a row lock
     * inside the transaction so two concurrent saves can never create a
     * second entry for the same source. Voided entries are excluded on
     * purpose: after void + re-save, a fresh entry is legitimate.
     */
    private function createSourceEntry(Invoice|MoneyReceipt $source, string $sourceType): ?JournalEntry
    {
        return DB::transaction(function () use ($source, $sourceType) {
            $entry = JournalEntry::where('source_type', $sourceType)
                ->where('source_id', $source->id)
                ->whereIn('status', [JournalEntry::STATUS_DRAFT, JournalEntry::STATUS_APPROVED])
                ->whereNull('reverses_journal_entry_id')
                ->lockForUpdate()
                ->first();

            if ($entry !== null && $entry->status === JournalEntry::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'journal_entry' => 'Cannot modify document; its journal entry is approved. Void the entry first.',
                ]);
            }

            $lines = $sourceType === 'invoice'
                ? $this->invoiceEntryLines($source)
                : $this->moneyReceiptEntryLines($source);

            if ($lines === []) {
                $entry?->delete();

                return null;
            }

            $this->assertBalanced($lines);

            if ($entry === null) {
                $entry = new JournalEntry([
                    'source_type' => $sourceType,
                    'source_id' => $source->id,
                    'entry_date' => $this->entryDateFor($source),
                    'voucher_number' => $this->nextVoucherNumber($this->entryDateFor($source)),
                    'total_debit' => $this->sumColumn($lines, 'debit'),
                    'total_credit' => $this->sumColumn($lines, 'credit'),
                    'status' => JournalEntry::STATUS_DRAFT,
                    'created_by' => $this->currentUserId(),
                ]);
                $entry->save();
            }

            $entry->lines()->delete();
            $entry->lines()->createMany($lines);

            $entry->update([
                'memo' => $this->memoFor($source),
                'entry_date' => $this->entryDateFor($source),
                'total_debit' => $this->sumColumn($lines, 'debit'),
                'total_credit' => $this->sumColumn($lines, 'credit'),
                'status' => JournalEntry::STATUS_DRAFT,
                'updated_by' => $this->currentUserId(),
            ]);

            return $entry->fresh('lines');
        });
    }

    /**
     * Create the draft entry for an AP source document, or refresh the
     * existing draft when the document is edited.
     */
    private function createApSourceEntry(ApInvoice|ApPayment $source, string $sourceType): ?JournalEntry
    {
        return DB::transaction(function () use ($source, $sourceType) {
            $entry = JournalEntry::where('source_type', $sourceType)
                ->where('source_id', $source->id)
                ->whereIn('status', [JournalEntry::STATUS_DRAFT, JournalEntry::STATUS_APPROVED])
                ->whereNull('reverses_journal_entry_id')
                ->lockForUpdate()
                ->first();

            if ($entry !== null && $entry->status === JournalEntry::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'journal_entry' => 'Cannot modify document; its journal entry is approved. Void the entry first.',
                ]);
            }

            $lines = $sourceType === 'ap_invoice'
                ? $this->apInvoiceEntryLines($source)
                : $this->apPaymentEntryLines($source);

            if ($lines === []) {
                $entry?->delete();

                return null;
            }

            $this->assertBalanced($lines);

            if ($entry === null) {
                $entry = new JournalEntry([
                    'source_type' => $sourceType,
                    'source_id' => $source->id,
                    'entry_date' => $this->entryDateFor($source),
                    'voucher_number' => $this->nextVoucherNumber($this->entryDateFor($source)),
                    'total_debit' => $this->sumColumn($lines, 'debit'),
                    'total_credit' => $this->sumColumn($lines, 'credit'),
                    'status' => JournalEntry::STATUS_DRAFT,
                    'created_by' => $this->currentUserId(),
                ]);
                $entry->save();
            }

            $entry->lines()->delete();
            $entry->lines()->createMany($lines);

            $entry->update([
                'memo' => $this->memoFor($source),
                'entry_date' => $this->entryDateFor($source),
                'total_debit' => $this->sumColumn($lines, 'debit'),
                'total_credit' => $this->sumColumn($lines, 'credit'),
                'status' => JournalEntry::STATUS_DRAFT,
                'updated_by' => $this->currentUserId(),
            ]);

            return $entry->fresh('lines');
        });
    }

    /**
     * Build the balanced lines for an invoice entry.
     *
     * @return array<int, array<string, mixed>>
     */
    private function invoiceEntryLines(Invoice $invoice): array
    {
        $lines = [];
        $totalBdt = '0.00';

        foreach ($invoice->items as $item) {
            $credit = number_format((float) $item->total_bdt, 2, '.', '');

            if (bccomp($credit, '0.00', 2) === 0) {
                continue;
            }

            $totalBdt = bcadd($totalBdt, $credit, 2);

            $lines[] = [
                'account_id' => $this->revenueAccountFor($item->key)->id,
                'invoice_id' => null,
                'reference' => $invoice->invoice_number,
                'debit' => 0,
                'credit' => $credit,
                'note' => $item->label ?? $item->key,
            ];
        }

        if ($lines === []) {
            return [];
        }

        array_unshift($lines, [
            'account_id' => $this->accounts->ensureCustomerArAccount($invoice->customer_name)->id,
            'invoice_id' => $invoice->id,
            'reference' => $invoice->invoice_number,
            'debit' => $totalBdt,
            'credit' => 0,
            'note' => "Invoice {$invoice->invoice_number}",
        ]);

        return $lines;
    }

    /**
     * Build the balanced lines for a money receipt entry.
     *
     * @return array<int, array<string, mixed>>
     */
    private function moneyReceiptEntryLines(MoneyReceipt $moneyReceipt): array
    {
        $links = $moneyReceipt->invoiceLinks
            ->filter(fn ($link) => (float) $link->paid_amount > 0)
            ->values();

        if ($links->isEmpty()) {
            return [];
        }

        $paidTotal = $links->reduce(
            fn (string $carry, $link): string => bcadd($carry, (string) $link->paid_amount, 2),
            '0.00'
        );

        $lines = [
            [
                'account_id' => $this->bankCashAccountFor($moneyReceipt->payment_term)->id,
                'invoice_id' => null,
                'reference' => $moneyReceipt->money_receipt_number,
                'debit' => $paidTotal,
                'credit' => 0,
                'note' => $moneyReceipt->payment_term,
            ],
        ];

        $arAccount = $this->accounts->ensureCustomerArAccount($moneyReceipt->customer_name);

        foreach ($links as $link) {
            $lines[] = [
                'account_id' => $arAccount->id,
                'invoice_id' => $link->invoice_id,
                'reference' => $moneyReceipt->money_receipt_number,
                'debit' => 0,
                'credit' => number_format((float) $link->paid_amount, 2, '.', ''),
                'note' => $link->invoice !== null
                    ? "Against invoice {$link->invoice->invoice_number}"
                    : 'Against outstanding invoices',
            ];
        }

        return $lines;
    }

    /**
     * Build the balanced lines for an AP invoice entry.
     *
     * @return array<int, array<string, mixed>>
     */
    private function apInvoiceEntryLines(ApInvoice $apInvoice): array
    {
        $lines = [];
        $totalBdt = '0.00';

        foreach ($apInvoice->items as $item) {
            $debit = number_format((float) $item->total_bdt, 2, '.', '');

            if (bccomp($debit, '0.00', 2) === 0) {
                continue;
            }

            $totalBdt = bcadd($totalBdt, $debit, 2);

            $lines[] = [
                'account_id' => $item->account_id,
                'invoice_id' => null,
                'reference' => $apInvoice->ap_invoice_number,
                'debit' => $debit,
                'credit' => 0,
                'note' => $item->description,
            ];
        }

        if ($lines === []) {
            return [];
        }

        $lines[] = [
            'account_id' => $this->accounts->ensureVendorApAccount($apInvoice->vendor?->vendor_name)->id,
            'invoice_id' => null,
            'reference' => $apInvoice->ap_invoice_number,
            'debit' => 0,
            'credit' => $totalBdt,
            'note' => "AP Invoice {$apInvoice->ap_invoice_number}",
        ];

        return $lines;
    }

    /**
     * Build the balanced lines for an AP payment entry.
     *
     * @return array<int, array<string, mixed>>
     */
    private function apPaymentEntryLines(ApPayment $apPayment): array
    {
        $links = $apPayment->invoiceLinks
            ->filter(fn ($link) => (float) $link->paid_amount > 0)
            ->values();

        if ($links->isEmpty()) {
            return [];
        }

        $paidTotal = $links->reduce(
            fn (string $carry, $link): string => bcadd($carry, (string) $link->paid_amount, 2),
            '0.00'
        );

        $lines = [];

        $apAccount = $this->accounts->ensureVendorApAccount($apPayment->vendor?->vendor_name);

        foreach ($links as $link) {
            $lines[] = [
                'account_id' => $apAccount->id,
                'invoice_id' => null,
                'reference' => $apPayment->ap_payment_number,
                'debit' => number_format((float) $link->paid_amount, 2, '.', ''),
                'credit' => 0,
                'note' => $link->apInvoice !== null
                    ? "Against AP invoice {$link->apInvoice->ap_invoice_number}"
                    : 'Against outstanding AP invoices',
            ];
        }

        $lines[] = [
            'account_id' => $this->bankCashAccountFor($apPayment->payment_method)->id,
            'invoice_id' => null,
            'reference' => $apPayment->ap_payment_number,
            'debit' => 0,
            'credit' => $paidTotal,
            'note' => $apPayment->payment_method,
        ];

        return $lines;
    }

    /**
     * Resolve the revenue account for an invoice item key, falling back to
     * Misc. Income and then to Service Revenue when nothing matches.
     */
    private function revenueAccountFor(?string $key): ChartOfAccount
    {
        $code = self::REVENUE_MAP[strtolower((string) $key)] ?? '4008';

        $account = ChartOfAccount::where('code', $code)->where('is_active', true)->first();

        if ($account === null) {
            $account = ChartOfAccount::where('code', '4100')->where('is_active', true)->first();
        }

        if ($account === null) {
            throw new RuntimeException('No revenue account is available for journal posting.');
        }

        return $account;
    }

    /**
     * Resolve the bank/cash account for a money receipt payment term, falling
     * back to Cash in Hand when the term is unmapped.
     */
    private function bankCashAccountFor(?string $paymentTerm): ChartOfAccount
    {
        $code = config("journal.payment_term_accounts.{$paymentTerm}");
        $code = is_string($code) ? $code : AccountService::CASH_CODE;

        $account = ChartOfAccount::where('code', $code)->where('is_active', true)->first();

        if ($account === null) {
            $account = ChartOfAccount::where('code', AccountService::CASH_CODE)->where('is_active', true)->first();
        }

        if ($account === null) {
            throw new RuntimeException('No cash/bank account is available for journal posting.');
        }

        return $account;
    }

    /**
     * Assert that debits equal credits, every line has exactly one side, and
     * the entry total is not zero.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function assertBalanced(array $lines): void
    {
        foreach ($lines as $line) {
            $hasDebit = bccomp((string) $line['debit'], '0', 2) === 1;
            $hasCredit = bccomp((string) $line['credit'], '0', 2) === 1;

            if ($hasDebit === $hasCredit) {
                throw ValidationException::withMessages([
                    'journal_entry' => 'A journal entry line must have exactly one of a debit or a credit.',
                ]);
            }
        }

        $totalDebit = $this->sumColumn($lines, 'debit');
        $totalCredit = $this->sumColumn($lines, 'credit');

        if (bccomp($totalDebit, '0.00', 2) === 0) {
            throw ValidationException::withMessages([
                'journal_entry' => 'Journal entry total must be greater than zero.',
            ]);
        }

        if (bccomp($totalDebit, $totalCredit, 2) !== 0) {
            throw ValidationException::withMessages([
                'journal_entry' => 'Journal entry is not balanced: debits must equal credits.',
            ]);
        }
    }

    /**
     * Sum a money column across lines with bcmath — never float math.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function sumColumn(array $lines, string $column): string
    {
        return array_reduce(
            array_column($lines, $column),
            fn (string $carry, mixed $amount): string => bcadd($carry, (string) $amount, 2),
            '0.00'
        );
    }

    /**
     * Re-read an entry inside the current transaction with a row lock so
     * concurrent approve/void attempts serialize and the loser re-reads fresh
     * state and fails with a clean 409.
     */
    private function lockEntry(JournalEntry $entry): JournalEntry
    {
        return JournalEntry::whereKey($entry->getKey())->lockForUpdate()->firstOrFail();
    }

    /**
     * Soft-close closed accounting periods: entries dated on or before the
     * configured cutoff cannot be approved or voided. An empty cutoff keeps
     * every period open.
     */
    private function assertPeriodOpen(Carbon|string|null $entryDate): void
    {
        $cutoff = trim((string) config('accounting.period_lock_date'));

        if ($cutoff === '' || $entryDate === null) {
            return;
        }

        $cutoffDate = Carbon::parse($cutoff)->startOfDay();
        $date = Carbon::parse($entryDate)->startOfDay();

        if ($date->lessThanOrEqualTo($cutoffDate)) {
            throw ValidationException::withMessages([
                'journal_entry' => "Books closed on/before {$cutoffDate->toDateString()}.",
            ]);
        }
    }

    private function memoFor(Invoice|MoneyReceipt|ApInvoice|ApPayment $source): string
    {
        return match (true) {
            $source instanceof Invoice => "Invoice {$source->invoice_number} raised for {$source->customer_name}",
            $source instanceof MoneyReceipt => "Payment received from {$source->customer_name}",
            $source instanceof ApInvoice => "AP Invoice {$source->ap_invoice_number} from {$source->vendor?->vendor_name}",
            $source instanceof ApPayment => "Payment to {$source->vendor?->vendor_name}",
        };
    }

    private function entryDateFor(Invoice|MoneyReceipt|ApInvoice|ApPayment $source): string
    {
        return match (true) {
            $source instanceof Invoice => ($source->invoice_date?->toDateString() ?? now()->toDateString()),
            $source instanceof MoneyReceipt => ($source->money_receipt_date?->toDateString() ?? now()->toDateString()),
            $source instanceof ApInvoice => ($source->invoice_date?->toDateString() ?? now()->toDateString()),
            $source instanceof ApPayment => ($source->payment_date?->toDateString() ?? now()->toDateString()),
        };
    }

    private function sourceFor(JournalEntry $entry): Invoice|MoneyReceipt|ApInvoice|ApPayment
    {
        return match ($entry->source_type) {
            'invoice' => $entry->source_id !== null ? Invoice::findOrFail($entry->source_id) : throw new RuntimeException('Entry has no invoice source.'),
            'money_receipt' => $entry->source_id !== null ? MoneyReceipt::findOrFail($entry->source_id) : throw new RuntimeException('Entry has no money receipt source.'),
            'ap_invoice' => $entry->source_id !== null ? ApInvoice::findOrFail($entry->source_id) : throw new RuntimeException('Entry has no AP invoice source.'),
            'ap_payment' => $entry->source_id !== null ? ApPayment::findOrFail($entry->source_id) : throw new RuntimeException('Entry has no AP payment source.'),
            default => throw new RuntimeException('Unsupported journal entry source type.'),
        };
    }

    private function currentUserId(): ?int
    {
        return auth('sanctum')->id();
    }
}
