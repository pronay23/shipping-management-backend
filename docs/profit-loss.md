# Profit & Loss Report

Spec for the income-statement report built on top of the double-entry ledger.
It follows the reporting rules defined in [`ledger-best-practices.md`](ledger-best-practices.md)
(Pattern A: compute everything from posted lines, never cache) and reuses the
tables, services, and conventions described in [`journal-entry.md`](journal-entry.md).

> Golden rule: **P&L is derived, never stored.** Every figure on the report is a
> live aggregate over `journal_entry_lines`; there is no side table to drift.

## 1. What it reports

Movement of **revenue** and **expense** accounts over a date range:

- Revenue accounts (`4001` DOC Fee … `4008` Misc Income, fallback `4100`) carry
  credit balances naturally → amount = `SUM(credit − debit)`.
- Expense accounts carry debit balances naturally → amount = `SUM(debit − credit)`.
- Net profit = total revenue − total expenses. Positive = profit, negative = loss.

Everything else is out of scope by definition: asset/liability/equity accounts
(cash, bank, AR control and its per-customer `1100.xxx` children, retained
earnings `3000`) never appear on a P&L. Per-customer AR sub-accounts are assets,
so they are excluded even though they share the chart with revenue codes.

Not modeled (explicitly): tax provisions, accrual deferrals, and automatic
year-end closing to Retained Earnings (`3000`). The report shows *period
movement*; closing entries, if ever introduced, would be ordinary journal
entries and flow through this report like any other posting.

## 2. Which entries count

Identical semantics to the trial balance (§5.1 of the best-practices doc):

```sql
WHERE e.status IN ('approved', 'voided')
```

- **Drafts are excluded** — not yet financial truth.
- **Approved entries are included** — the books as they stand.
- **Voided originals are included together with their approved reversals**, so
  each void/reversal pair nets to exactly zero. Filtering voided rows out while
  keeping their reversals (or vice versa) would print phantom income after
  every correction — the pair is the record.

Date filtering applies to `journal_entries.entry_date` (the reversal's own
entry date decides which period its effect lands in).

## 3. API

`GET /api/reports/profit-and-loss`

Query parameters:

| Param | Type | Default | Notes |
|---|---|---|---|
| `from` | date | January 1 of the current year | Inclusive, matched against `entry_date` |
| `to` | date | today | Inclusive |

Response shape mirrors the trial-balance contract the frontend already types
(money as fixed two-decimal **strings**, never floats):

```json
{
    "data": {
        "revenue": [
            { "account_id": 9, "account_code": "4001", "account_name": "DOC Fee", "amount": "22000.00" }
        ],
        "expenses": []
    },
    "meta": { "from": "2026-01-01", "to": "2026-08-23" },
    "totals": {
        "total_revenue": "22000.00",
        "total_expense": "0.00",
        "net_profit": "22000.00"
    }
}
```

Rules:

- Rows are ordered by `account_code`. All revenue/expense accounts appear, even
  at `"0.00"`, so the layout stays stable period over period.
- `net_profit` may be negative (a loss); render it signed, don't clamp it.
- No pagination: the revenue+expense slice of the chart is tiny and bounded;
  paginating an income statement is UX noise. `meta` carries the resolved
  range instead.
- The period lock (`config('accounting.period_lock_date')`) does **not** apply
  here — it gates mutations (approve/void), never reads.

## 4. Backend implementation

Follows the existing split: thin controller, logic in
`app/Services/LedgerReportService.php`.

```php
// LedgerReportService
public function profitAndLoss(?string $from = null, ?string $to = null): array
{
    $range = [$this->resolveFrom($from), $this->resolveTo($to)];

    $rows = $this->postedLinesQuery()               // approved + voided, see §2
        ->join('chart_of_accounts as c', 'c.id', '=', 'l.account_id')
        ->whereIn('c.type', ['revenue', 'expense']) // resolve via account_type_id slug first
        ->whereBetween('e.entry_date', $range)
        ->groupBy('l.account_id', 'c.code', 'c.name', 'c.type')
        ->selectRaw("l.account_id, c.code AS account_code, c.name AS account_name, c.type AS account_type,
                     SUM(CASE WHEN c.type = 'expense' THEN l.debit - l.credit ELSE l.credit - l.debit END) AS amount")
        ->orderBy('c.code')
        ->get();
    ...
}
```

Notes:

- Account-type membership must be resolved the same way everywhere else in the
  codebase: `account_type_id` slug when set, legacy `type` string otherwise
  (both exist after the `account_types` backfill migration).
- Zero-activity accounts are filled back in PHP from `ChartOfAccount` so the
  response always lists the full revenue/expense slice.
- Totals sum with `bcadd(..., 2)`; `net_profit = bcsub(total_revenue, total_expense, 2)`.
- Route registered beside the other reports in `routes/api.php`
  (`LedgerReportController@profitAndLoss`), validation identical to
  `validateRange()` minus pagination.

## 5. Frontend implementation (Next.js)

Server Component page at `app/(app)/features/journal/reports/profit-loss/page.tsx`,
following the trial-balance page's established patterns:

- Fetch through a new `api/getProfitAndLoss.ts` normalizer typed in
  `journal/types/index.ts` (`amount`, totals as `string | number`).
- Two sections (Revenue / Expenses) rendered from `report.data`, each account
  name linking nowhere special — plain text is fine here.
- Format every amount with `formatCurrency(parseNumber(String(v)))`; never
  `parseFloat` arithmetic in components (§7 of the best-practices doc).
- Render `net_profit` under a clear label ("Net profit" / "Net loss" depending
  on sign) — a negative number silently labeled "profit" reads like a bug.
- Range inputs (if added later) go through `router.push` search params and are
  read server-side, like the ledger page's `page` param — no client fetching.

## 6. Testing checklist

Feature tests alongside `LedgerReportsTest`:

- [ ] Approved invoice posts revenue; P&L shows the mapped revenue accounts
      with positive amounts and correct `net_profit`.
- [ ] Draft-only activity is invisible.
- [ ] Voided invoice + its reversal contribute exactly `0.00` (pair included).
- [ ] `from`/`to` filter on `entry_date`; defaults resolve to YTD/today and
      appear in `meta`.
- [ ] Expense postings (once any exist) show under `expenses` with positive
      amounts and reduce `net_profit`.
- [ ] Cash/bank/AR accounts (`1010`, `1020`, `1100`, `1100.xxx`) never appear.
- [ ] All amounts serialize as strings; empty accounts present at `"0.00"`.

## 7. Checklist

| ✅ Do | ❌ Don't |
|---|---|
| Compute P&L from posted lines on request | Cache balances or store period aggregates |
| Include voided originals with their reversals | Filter either half of a void pair |
| Sign-normalize (revenue `credit−debit`, expense `debit−credit`) | Print raw `SUM(debit−credit)` for revenue |
| Serialize money as strings, allow negative `net_profit` | Use floats or clamp losses to zero |
| Resolve account types via `account_type_id` slug + legacy `type` | Hardcode code prefixes (`4xxx`) as type logic |
| Default range YTD→today, expose `from`/`to` | Return an unbounded lifetime "profit" |

---

*Related docs: [`ledger-best-practices.md`](ledger-best-practices.md) · [`journal-entry.md`](journal-entry.md) · [`laravel-invoice-api.md`](laravel-invoice-api.md)*
