<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LedgerReportService
{
    /**
     * Format a raw SQL amount as a fixed two-decimal string — money never
     * crosses the API as a float.
     */
    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * Posted-entry lines (approved + voided). Voided originals are kept so
     * each void/reversal pair nets to zero — dropping the voided half while
     * keeping its reversal would leave phantom negative balances behind
     * (see ledger-best-practices.md §5.1: "the reversal pair is the record").
     */
    private function postedLinesQuery(): Builder
    {
        return DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->whereIn('e.status', [JournalEntry::STATUS_APPROVED, JournalEntry::STATUS_VOIDED]);
    }

    /**
     * Apply an inclusive entry-date range. Boundaries go through whereDate()
     * because entry_date is stored with a time part — comparing against a
     * bare 'Y-m-d' string would exclude every row dated on the upper bound.
     */
    private function applyEntryDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from !== null, fn ($q) => $q->whereDate('e.entry_date', '>=', $from))
            ->when($to !== null, fn ($q) => $q->whereDate('e.entry_date', '<=', $to));
    }

    /**
     * Trial balance: per-account debit/credit totals and balances computed
     * from approved lines, paginated across the chart of accounts.
     *
     * @return array<string, mixed>
     */
    public function trialBalance(?string $from = null, ?string $to = null, int $page = 1, int $perPage = 50): array
    {
        $aggregates = $this->applyEntryDateRange($this->postedLinesQuery(), $from, $to)
            ->groupBy('l.account_id')
            ->selectRaw('l.account_id, SUM(l.debit) AS debit_total, SUM(l.credit) AS credit_total, SUM(l.debit - l.credit) AS balance')
            ->get()
            ->keyBy('account_id');

        $totalDebit = $aggregates->reduce(
            fn (string $carry, $row): string => bcadd($carry, (string) $row->debit_total, 2),
            '0.00'
        );
        $totalCredit = $aggregates->reduce(
            fn (string $carry, $row): string => bcadd($carry, (string) $row->credit_total, 2),
            '0.00'
        );

        $accounts = ChartOfAccount::query()->orderBy('code')->paginate($perPage, ['*'], 'page', $page);

        $rows = $accounts->through(fn (ChartOfAccount $account) => [
            'account_id' => $account->id,
            'account_code' => $account->code,
            'account_name' => $account->name,
            'account_type' => $account->type,
            'debit_total' => $this->money($aggregates->get($account->id)->debit_total ?? 0),
            'credit_total' => $this->money($aggregates->get($account->id)->credit_total ?? 0),
            'balance' => $this->money($aggregates->get($account->id)->balance ?? 0),
        ]);

        return [
            'data' => $rows->items(),
            'meta' => $this->paginationMeta($accounts),
            'totals' => [
                'debit' => $totalDebit,
                'credit' => $totalCredit,
                'is_balanced' => bccomp($totalDebit, $totalCredit, 2) === 0,
            ],
        ];
    }

    /**
     * Approved-entry lines for one account.
     */
    private function accountLinesQuery(ChartOfAccount $account): Builder
    {
        return $this->postedLinesQuery()->where('l.account_id', $account->id);
    }

    /**
     * General ledger for one account: chronological lines with a running
     * balance (window function), paginated. Balances include the opening
     * balance from before the requested date range.
     *
     * @return array<string, mixed>
     */
    public function accountLedger(ChartOfAccount $account, ?string $from = null, ?string $to = null, int $page = 1, int $perPage = 50): array
    {
        $opening = '0.00';

        if ($from !== null) {
            $opening = $this->money(
                $this->accountLinesQuery($account)
                    ->whereDate('e.entry_date', '<', $from)
                    ->selectRaw('COALESCE(SUM(l.debit - l.credit), 0) AS balance')
                    ->value('balance')
            );
        }

        $rangeFilter = fn (Builder $query): Builder => $this->applyEntryDateRange($query, $from, $to);

        $ledger = DB::query()
            ->fromSub(
                $rangeFilter($this->accountLinesQuery($account))
                    ->selectRaw('l.journal_entry_id, l.id, e.entry_date, e.voucher_number, l.reference, l.note, l.debit, l.credit, SUM(l.debit - l.credit) OVER (ORDER BY e.entry_date, e.id, l.id) AS range_running'),
                'gl'
            )
            ->orderBy('entry_date')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $rows = $ledger->through(fn ($row) => [
            'journal_entry_id' => $row->journal_entry_id,
            'entry_date' => Carbon::parse($row->entry_date)->toDateString(),
            'voucher_number' => $row->voucher_number,
            'reference' => $row->reference,
            'note' => $row->note,
            'debit' => $this->money($row->debit),
            'credit' => $this->money($row->credit),
            'running_balance' => $this->money((float) $opening + (float) $row->range_running),
        ]);

        $periodDebit = (float) $rangeFilter($this->accountLinesQuery($account))
            ->selectRaw('COALESCE(SUM(l.debit), 0) AS total')
            ->value('total');
        $periodCredit = (float) $rangeFilter($this->accountLinesQuery($account))
            ->selectRaw('COALESCE(SUM(l.credit), 0) AS total')
            ->value('total');

        return [
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
            ],
            'data' => $rows->items(),
            'meta' => $this->paginationMeta($ledger),
            'totals' => [
                'opening_balance' => $opening,
                'period_debit' => $this->money($periodDebit),
                'period_credit' => $this->money($periodCredit),
                'closing_balance' => $this->money($opening + $periodDebit - $periodCredit),
            ],
        ];
    }

    /**
     * AR aging per invoice: outstanding amounts derived from approved lines
     * stamped with invoice_id (Pattern A), bucketed relative to the as-of date.
     *
     * @return array<string, mixed>
     */
    public function arAging(?string $asOf = null): array
    {
        $asOfDate = $asOf !== null ? Carbon::parse($asOf)->startOfDay() : now()->startOfDay();

        $rows = $this->postedLinesQuery()
            ->whereNotNull('l.invoice_id')
            ->leftJoin('invoices as i', 'i.id', '=', 'l.invoice_id')
            ->groupBy('l.invoice_id')
            ->selectRaw('l.invoice_id, MAX(i.invoice_number) AS invoice_number, MAX(i.customer_name) AS customer_name, MAX(i.invoice_date) AS invoice_date, SUM(l.debit - l.credit) AS outstanding')
            ->havingRaw('ABS(SUM(l.debit - l.credit)) > 0.009')
            ->orderBy('invoice_number')
            ->get();

        $data = $rows->map(function ($row) use ($asOfDate): array {
            $daysOutstanding = $row->invoice_date !== null
                ? max(0, (int) round((float) Carbon::parse($row->invoice_date)->diffInDays($asOfDate, false)))
                : null;

            return [
                'invoice_id' => $row->invoice_id,
                'invoice_number' => $row->invoice_number,
                'customer_name' => $row->customer_name,
                'invoice_date' => $row->invoice_date,
                'days_outstanding' => $daysOutstanding,
                'bucket' => match (true) {
                    $daysOutstanding === null => 'unknown',
                    $daysOutstanding <= 30 => '0-30',
                    $daysOutstanding <= 60 => '31-60',
                    $daysOutstanding <= 90 => '61-90',
                    default => '91+',
                },
                'outstanding' => $this->money($row->outstanding),
            ];
        })->values();

        $totalOutstanding = $rows->reduce(
            fn (string $carry, $row): string => bcadd($carry, (string) $row->outstanding, 2),
            '0.00'
        );

        return [
            'data' => $data->all(),
            'meta' => [
                'as_of' => $asOfDate->toDateString(),
                'count' => $data->count(),
            ],
            'totals' => [
                'total_outstanding' => $totalOutstanding,
            ],
        ];
    }

    /**
     * Profit & loss (income statement) over a date range: revenue and expense accounts only,
     * sign-normalized (revenue credit−debit, expense debit−credit) so healthy
     * amounts read positive. Void/reversal pairs net to zero (see §2 of
     * docs/profit-loss.md). No pagination — the revenue/expense slice of the
     * chart is tiny; every account appears, zero activity included.
     *
     * @return array<string, mixed>
     */
    public function profitAndLoss(?string $from = null, ?string $to = null): array
    {
        $resolvedFrom = $from !== null ? Carbon::parse($from)->toDateString() : Carbon::now()->startOfYear()->toDateString();
        $resolvedTo = $to !== null ? Carbon::parse($to)->toDateString() : Carbon::now()->toDateString();

        $amounts = $this->applyEntryDateRange($this->postedLinesQuery(), $resolvedFrom, $resolvedTo)
            ->join('chart_of_accounts as acc', 'acc.id', '=', 'l.account_id')
            ->leftJoin('account_types as at', 'at.id', '=', 'acc.account_type_id')
            ->whereIn(DB::raw('COALESCE(at.slug, acc.type)'), ['revenue', 'expense'])
            ->groupBy('l.account_id')
            ->selectRaw("l.account_id, SUM(CASE WHEN COALESCE(at.slug, acc.type) = 'expense' THEN l.debit - l.credit ELSE l.credit - l.debit END) AS amount")
            ->pluck('amount', 'l.account_id');

        $accounts = ChartOfAccount::query()
            ->leftJoin('account_types as at', 'at.id', '=', 'chart_of_accounts.account_type_id')
            ->whereIn(DB::raw('COALESCE(at.slug, chart_of_accounts.type)'), ['revenue', 'expense'])
            ->orderBy('chart_of_accounts.code')
            ->get([
                'chart_of_accounts.id',
                'chart_of_accounts.code AS account_code',
                'chart_of_accounts.name AS account_name',
                DB::raw("COALESCE(at.slug, chart_of_accounts.type) AS section"),
            ]);

        $sections = ['revenue' => [], 'expenses' => []];
        $totalRevenue = '0.00';
        $totalExpense = '0.00';

        foreach ($accounts as $account) {
            $amount = $this->money($amounts->get($account->id) ?? 0);

            // DB slugs say "expense"; the API contract says "expenses".
            $sectionKey = $account->section === 'expense' ? 'expenses' : $account->section;

            $sections[$sectionKey][] = [
                'account_id' => $account->id,
                'account_code' => $account->account_code,
                'account_name' => $account->account_name,
                'amount' => $amount,
            ];

            if ($account->section === 'expense') {
                $totalExpense = bcadd($totalExpense, $amount, 2);
            } else {
                $totalRevenue = bcadd($totalRevenue, $amount, 2);
            }
        }

        return [
            'data' => $sections,
            'meta' => [
                'from' => $resolvedFrom,
                'to' => $resolvedTo,
            ],
            'totals' => [
                'total_revenue' => $totalRevenue,
                'total_expense' => $totalExpense,
                'net_profit' => bcsub($totalRevenue, $totalExpense, 2),
            ],
        ];
    }

    /**
     * AP aging per vendor: outstanding amounts derived from approved lines
     * stamped with AP invoice source, bucketed relative to the as-of date.
     *
     * @return array<string, mixed>
     */
    public function apAging(?string $asOf = null): array
    {
        $asOfDate = $asOf !== null ? Carbon::parse($asOf)->startOfDay() : now()->startOfDay();

        $apControlId = $this->apControlAccountId();

        $rows = $this->postedLinesQuery()
            ->where('e.source_type', 'ap_invoice')
            ->join('ap_invoices as ai', 'ai.id', '=', 'e.source_id')
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'l.account_id')
            ->where(fn ($q) => $q->where('l.account_id', $apControlId)->orWhere('coa.parent_id', $apControlId))
            ->groupBy('e.source_id')
            ->selectRaw('e.source_id AS ap_invoice_id, MAX(ai.ap_invoice_number) AS ap_invoice_number, MAX(v.vendor_name) AS vendor_name, MAX(ai.invoice_date) AS invoice_date, SUM(l.credit - l.debit) AS outstanding')
            ->leftJoin('vendors as v', 'v.id', '=', 'ai.vendor_id')
            ->havingRaw('ABS(SUM(l.credit - l.debit)) > 0.009')
            ->orderBy('ap_invoice_number')
            ->get();

        $data = $rows->map(function ($row) use ($asOfDate): array {
            $daysOutstanding = $row->invoice_date !== null
                ? max(0, (int) round((float) Carbon::parse($row->invoice_date)->diffInDays($asOfDate, false)))
                : null;

            return [
                'ap_invoice_id' => $row->ap_invoice_id,
                'ap_invoice_number' => $row->ap_invoice_number,
                'vendor_name' => $row->vendor_name,
                'invoice_date' => $row->invoice_date,
                'days_outstanding' => $daysOutstanding,
                'bucket' => match (true) {
                    $daysOutstanding === null => 'unknown',
                    $daysOutstanding <= 30 => '0-30',
                    $daysOutstanding <= 60 => '31-60',
                    $daysOutstanding <= 90 => '61-90',
                    default => '91+',
                },
                'outstanding' => $this->money($row->outstanding),
            ];
        })->values();

        $totalOutstanding = $rows->reduce(
            fn (string $carry, $row): string => bcadd($carry, (string) $row->outstanding, 2),
            '0.00'
        );

        return [
            'data' => $data->all(),
            'meta' => [
                'as_of' => $asOfDate->toDateString(),
                'count' => $data->count(),
            ],
            'totals' => [
                'total_outstanding' => $totalOutstanding,
            ],
        ];
    }

    /**
     * Balance sheet: assets, liabilities, and equity as of a given date.
     * Uses approved + voided journal entry lines. Retained earnings is the
     * opening balance; current-period net profit/loss is pulled from P&L.
     *
     * @return array<string, mixed>
     */
    public function balanceSheet(?string $asOf = null): array
    {
        $resolvedTo = $asOf !== null ? Carbon::parse($asOf)->toDateString() : now()->toDateString();

        $aggregates = $this->postedLinesQuery()
            ->whereDate('e.entry_date', '<=', $resolvedTo)
            ->groupBy('l.account_id')
            ->selectRaw('l.account_id, SUM(l.debit - l.credit) AS balance')
            ->get()
            ->keyBy('account_id');

        $accounts = ChartOfAccount::query()
            ->orderBy('code')
            ->get();

        $sections = [
            'assets' => [],
            'liabilities' => [],
            'equity' => [],
        ];

        $totalAssets = '0.00';
        $totalLiabilities = '0.00';
        $totalEquity = '0.00';

        foreach ($accounts as $account) {
            $balance = $this->money($aggregates->get($account->id)->balance ?? 0);

            if (bccomp($balance, '0.00', 2) === 0) {
                continue;
            }

            $section = match ($account->type) {
                'asset' => 'assets',
                'liability' => 'liabilities',
                'equity' => 'equity',
                default => null,
            };

            if ($section === null) {
                continue;
            }

            $sections[$section][] = [
                'account_id' => $account->id,
                'account_code' => $account->code,
                'account_name' => $account->name,
                'balance' => $balance,
            ];

            match ($section) {
                'assets' => $totalAssets = bcadd($totalAssets, $balance, 2),
                'liabilities' => $totalLiabilities = bcadd($totalLiabilities, $balance, 2),
                'equity' => $totalEquity = bcadd($totalEquity, $balance, 2),
            };
        }

        $pl = $this->profitAndLoss(
            Carbon::now()->startOfYear()->toDateString(),
            $resolvedTo
        );

        $netProfit = $pl['totals']['net_profit'];

        return [
            'data' => $sections,
            'meta' => [
                'as_of' => $resolvedTo,
            ],
            'totals' => [
                'total_assets' => $totalAssets,
                'total_liabilities' => $totalLiabilities,
                'total_equity' => $totalEquity,
                'net_profit' => $netProfit,
                'total_liabilities_equity' => bcadd(bcadd($totalLiabilities, $totalEquity, 2), $netProfit, 2),
            ],
        ];
    }

    /**
     * Standard pagination metadata for a LengthAwarePaginator.
     *
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @return array<string, int>
     */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    private function apControlAccountId(): int
    {
        return (int) ChartOfAccount::where('code', '2100')->value('id');
    }
}
