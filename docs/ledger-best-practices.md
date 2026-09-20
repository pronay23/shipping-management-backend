# Ledger Best Practices

Practical rules for building and maintaining the double-entry ledger in this project.
This guide complements [`journal-entry.md`](journal-entry.md) (the backend spec) and refers
to its tables (`chart_of_accounts`, `journal_entries`, `journal_entry_lines`), services
(`JournalService`, `AccountService`), API routes, and the Next.js pages under
`app/(app)/features/journal/`.

> Golden rule: **the ledger is append-only financial truth.** Approved rows are history;
> history is corrected by adding new entries, never by editing old ones.

## 1. Core principles

1. **Immutability after posting.** Once an entry is `approved`, its lines, amounts, dates,
   and voucher number never change. There is no "edit approved entry" feature — by design.
2. **Corrections are new entries.** A mistake in an approved entry is fixed by voiding it
   (which creates a reversing entry) and letting the source document post a fresh entry.
   Net effect = zero, audit trail = intact.
3. **Every mutation leaves a trace.** `created_by`, `updated_by`, `voided_by`,
   `voided_at` exist precisely so any figure on screen can be traced to who did what and
   when. Never null them out or reuse vouchers.
4. **Balance is derived, not stored.** Account balances are computed from approved lines.
   Do not invent side tables of balances unless reporting performance demands it (§5).
5. **Single writer path.** All postings go through `JournalService`. Controllers, jobs,
   and seeders must never write `journal_entries` / `journal_entry_lines` directly.

## 2. Data integrity (Laravel)

### 2.1 Enforce balance inside the service transaction

`assertBalanced()` runs *before* anything is written; totals are recomputed from lines
inside the same transaction (see `post()` in journal-entry.md §5). Rules per line:

- exactly one of `debit` / `credit` is `> 0`, the other is `0`;
- amounts are `decimal(18,2)` — **never** floats, never string math in PHP;
- reject zero-total entries (`total_debit == 0`) — they are almost always bugs.

Optional hardening at the DB level (MySQL ≥ 8.0.16 enforces CHECK):

```php
// migration fragment
DB::statement(<<<'SQL'
    ALTER TABLE journal_entry_lines ADD CONSTRAINT chk_line_side
    CHECK ((debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0))
SQL);

DB::statement(<<<'SQL'
    ALTER TABLE journal_entries ADD CONSTRAINT chk_balanced
    CHECK (total_debit = total_credit AND total_debit > 0)
SQL);
```

Keep the checks in the service layer too — DB constraints are the last line of defense,
not the first.

### 2.2 Money handling

- PHP: DB casts give you strings (`"135740.00"`). Sum with `bcmath` (`bcadd($a, $b, 2)`)
  or collect in integer minor units — do not sum raw floats.
- JSON API: serialize decimals as **strings** (the spec's resources already do). This is
  also what the frontend types expect (`debit: string | number`, `types/index.ts:11-12`).

### 2.3 Referential links

- `source_type` + `source_id` is polymorphic without FK by design; still index the pair
  (already indexed) and validate the source exists before creating an entry.
- `invoice_id` on lines powers per-invoice AR reporting — set it on **every** AR line,
  not just some. Missing `invoice_id` silently breaks aging reports (§5.3).

## 3. Concurrency & idempotency

### 3.1 Voucher numbering

Already specified: derive `JV-{year}-{####}` inside the posting transaction with
`lockForUpdate` on the latest row for the year. Never generate vouchers outside the
transaction, and never retry voucher generation without the lock — two concurrent saves
must never share a number.

### 3.2 One entry per source document (duplicate-posting guard)

A retried `InvoiceController@store` or double-clicked save must not create a second
entry for the same invoice/MR. Guard inside the **same transaction** that creates the
entry:

```php
$existing = JournalEntry::where('source_type', $type)
    ->where('source_id', $id)
    ->whereIn('status', [JournalEntry::STATUS_DRAFT, JournalEntry::STATUS_APPROVED])
    ->lockForUpdate()
    ->exists();

if ($existing) {
    throw ValidationException::withMessages([
        'journal' => "An active journal entry already exists for {$type} #{$id}.",
    ]);
}
```

Notes:

- `lockForUpdate()` closes the check-then-insert race between two parallel requests.
- Voided entries are excluded on purpose: after void + re-save, a new entry for the same
  source is legitimate.
- Reversal entries copy the original's `source_type`/`source_id`; the original flips to
  `voided` **in the same transaction**, so at most one *active* entry per source still
  holds. If you later void a reversal, follow §4 — don't loosen this guard.

### 3.3 Approve / void races

Both endpoints must throw **409** (not 500) when the entry isn't in the expected state —
two users approving simultaneously, or approving while another voids. Pattern:

```php
$entry = JournalEntry::whereKey($id)->lockForUpdate()->firstOrFail();

if ($entry->status !== JournalEntry::STATUS_DRAFT) {
    abort(409, 'Entry is not in draft state.');
}
```

Locking the row means the loser waits, re-reads the fresh status, and gets a clean 409 —
which the frontend surfaces via `updateJournalStatus`'s error path
(`updateJournalStatus.ts:33-53`).

## 4. Void discipline

| Entry status | Void behavior |
|---|---|
| `draft` | Hard delete. Never reached the ledger, nothing to reverse. |
| `approved` | Create reversing entry (sides swapped, refs copied, memo `Reversal of {voucher}`), then mark original `voided` — **one transaction**. |
| `voided` | Reject with 409. No un-voiding; fix forward instead. |

Rules:

- Never `DELETE` an approved entry, even "temporarily". The reversal pair is the record.
- Always stamp `voided_at` + `voided_by` — the frontend renders them
  (`view/[id]/page.tsx:57-62`); blank values look like data loss.
- Deleting an invoice/money receipt whose entry is `approved` must be refused unless the
  entry is voided first (spec §9). Order matters: **void the entry, then delete the
  document** — otherwise the reversal references a ghost source.

## 5. Balances & reporting

### 5.1 Compute from lines (Pattern A — recommended)

At this project's scale, always compute; never trust a cached number:

```sql
-- Per-account balances (approved entries only)
SELECT l.account_id,
       c.code, c.name,
       SUM(l.debit - l.credit) AS balance
FROM journal_entry_lines l
JOIN journal_entries e ON e.id = l.journal_entry_id
JOIN chart_of_accounts c ON c.id = l.account_id
WHERE e.status = 'approved'
GROUP BY l.account_id, c.code, c.name
ORDER BY c.code;
```

Why this is correct with reversals: a voided original is excluded, its approved reversal
(sides swapped) is included — net effect zero, exactly as intended.

Expose aggregates through dedicated read endpoints
(e.g. `GET /api/accounts/{id}/ledger`, `GET /api/reports/trial-balance`). The frontend
must not sum lines itself (§7).

### 5.2 Cached balances (Pattern B — only if needed)

Only reach for a `account_balances` snapshot table when Pattern A measurably gets slow.
If you do: update it inside the approve/void transaction, store `balance` +
`last_entry_id` (for reconciliation), and add a nightly job that recomputes from scratch
to detect drift. A cached balance that can't be rebuilt from lines is a bug factory.

### 5.3 Core report queries

```sql
-- Trial balance (debits and credits must both sum to the same total)
SELECT c.code, c.name, c.type,
       SUM(l.debit - l.credit) AS balance
FROM journal_entry_lines l
JOIN journal_entries e ON e.id = l.journal_entry_id
JOIN chart_of_accounts c ON c.id = l.account_id
WHERE e.status = 'approved'
GROUP BY c.id, c.code, c.name, c.type;

-- General ledger for one account, running balance (MySQL 8+ window function)
SELECT e.entry_date, e.voucher_number, l.reference, l.note,
       l.debit, l.credit,
       SUM(l.debit - l.credit) OVER (ORDER BY e.entry_date, e.id, l.id) AS running_balance
FROM journal_entry_lines l
JOIN journal_entries e ON e.id = l.journal_entry_id
WHERE e.status = 'approved' AND l.account_id = ?
ORDER BY e.entry_date, e.id, l.id;

-- AR aging per invoice (uses invoice_id stamped on AR lines)
SELECT l.invoice_id,
       MAX(l.reference)      AS invoice_number,
       SUM(l.debit - l.credit) AS outstanding
FROM journal_entry_lines l
JOIN journal_entries e ON e.id = l.journal_entry_id
WHERE e.status = 'approved'
  AND l.invoice_id IS NOT NULL
GROUP BY l.invoice_id
HAVING ABS(SUM(l.debit - l.credit)) > 0.009;
```

### 5.4 Indexing & performance

Existing single-column indexes cover the basics. Add as reporting grows:

- `journal_entries (status, entry_date)` — date-ranged, status-filtered scans;
- paginate GL/trial-balance endpoints (they grow forever — the ledger never shrinks);
- archive concerns are far away; don't pre-optimize with partitioning now.

## 6. Period controls (soft close)

Prevent silent edits of closed accounting periods:

1. Store a cutoff, e.g. `config('accounting.period_lock_date')` or a settings row
   (`YYYY-MM-DD`). `null`/past-empty = everything open.
2. In `JournalService::approve()`, `void()`, and any future manual posting: reject when
   `entry_date < cutoff` with a clear 422 message ("Books closed on/before 2026-07-31").
3. Draft creation may stay allowed; approval into a closed period is what's blocked.
4. The frontend needs no special logic — it already renders API error messages
   (`view/[id]/page.tsx` action error path in `JournalActions.tsx:29-36`).

Reopening a period is an explicit, logged decision (change the setting, record who/why) —
never a side effect of a normal edit.

## 7. Frontend practices (Next.js)

What already works well in `app/(app)/features/journal/` — keep doing it:

- **Server Components fetch, clients act.** `view/[id]/page.tsx` fetches on the server;
  only `JournalActions` is a client component.
- **Money stays a string end-to-end.** Types use `string | number`
  (`types/index.ts:11-12`); format at render (`Intl.NumberFormat` / `৳` prefix), never
  `parseFloat` + arithmetic — float math corrupts taka amounts.
- **Status-gated actions.** `canApprove` / `canVoid` derive from `status`
  (`JournalActions.tsx:16-17`); buttons render disabled with a reason, and every action
  goes through `window.confirm` first — appropriate for irreversible operations.
- **Refresh after mutations.** `router.refresh()` re-runs the server fetch so status and
  badge reflect reality (`JournalActions.tsx:31`).
- **API errors become user-visible messages.** `updateJournalStatus.ts` unwraps
  `{ message }` / `{ errors }` into thrown `Error`s shown in the UI — a 409 race or a
  period-lock rejection reads as text, not a console line.

Additions to adopt going forward:

- **Never compute balances client-side** from fetched lines. Request aggregates from the
  report endpoints (§5.3); build read-only report pages
  (e.g. `/features/journal/reports/trial-balance`) as Server Components with pagination.
- **Don't optimistically flip status** before the POST resolves — ledger actions are
  consequential; show the busy label ("Approving…", "Voiding…") until confirmed.
- **After a 409**, call `router.refresh()` in the error path so the page shows the state
  that beat you, alongside the error message.
- **Treat `voided` as terminal in UI**: hide/disable further actions and show
  `voided_at`/`voided_by` context (already implemented — preserve it).

## 8. Checklist

| ✅ Do | ❌ Don't |
|---|---|
| Route all writes through `JournalService` inside `DB::transaction` | Insert/update `journal_entries` or lines ad-hoc |
| Correct approved entries via void → reversal | UPDATE amounts or DELETE approved entries |
| Lock rows (`lockForUpdate`) for voucher numbers, dup-guard, approve/void | Check-then-write without locks |
| Stamp `created_by` / `voided_by` / timestamps | Leave audit columns null or overwrite them |
| Serialize money as `decimal(18,2)` strings over JSON | Use floats anywhere near amounts |
| Set `invoice_id` on every AR line | Let AR lines go unlinked (breaks aging) |
| Compute balances/report aggregates on the backend | Sum lines in React for display |
| Return 409/422 with human-readable messages on state conflicts | Let races surface as 500s |
| Soft-close periods explicitly | Allow backdated approves after books close |

---

*Related docs: [`journal-entry.md`](journal-entry.md) · [`laravel-invoice-api.md`](laravel-invoice-api.md) · [`money-receipt.md`](money-receipt.md)*
