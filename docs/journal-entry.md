# Laravel Backend Spec — Journal Entries

This document defines the chart of accounts, database schema, Eloquent models, and API
resources for double-entry bookkeeping in the shipping-management backend. Journal entries
are **auto-generated** when invoices and money receipts are saved, then **reviewed and
approved/voided** by an accountant. The frontend consumes the endpoints below on the
Journal Entry pages (`app/(app)/features/journal/`).

## 1. Overview

| Source document | Entry posted on save | Ledger effect |
|---|---|---|
| **Invoice** | Debit `Accounts Receivable`; Credit `Service Revenue` per fee item | Recognize revenue + receivable |
| **Money Receipt** | Debit `Bank`/`Cash` (by `payment_term`); Credit `Accounts Receivable` per invoice | Record cash receipt + clear receivable |

Rules:

- Functional currency is **BDT**. All amounts posted in BDT.
- Debits must equal credits on every entry; otherwise the save of the source document fails (422).
- Entries are created as **`draft`**. Only `draft` entries can be edited/deleted.
  `approve` locks an entry and posts it; `void` reverses it.
- Deleting or editing an invoice / money receipt after its entry was **approved** must be
  blocked (or handled by voiding the entry first).

## 2. Chart of Accounts — Table `chart_of_accounts`

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | bigint unsigned | — | primary key |
| `code` | string(20) | no | **unique**, e.g. `1010`, `1100.001` |
| `name` | string(255) | no | display name |
| `type` | string(20) | no | `asset` \| `liability` \| `equity` \| `revenue` \| `expense` |
| `parent_id` | bigint unsigned | yes | FK → `chart_of_accounts.id`, for sub-accounts |
| `is_active` | boolean | no | default `true` |
| `is_system` | boolean | no | default `false`; system accounts cannot be deactivated |
| `created_by` | bigint unsigned | yes | FK → `employees.id` |
| `created_at` / `updated_at` | timestamp | — | |

**Seeded accounts (migration seeder):**

| Code | Name | Type | Notes |
|---|---|---|---|
| `1010` | Cash in Hand | asset | system |
| `1020` | Bank – One Bank PLC (RD 0021020013801) | asset | system |
| `1100` | Accounts Receivable (control) | asset | system |
| `1100.xxx` | AR – {customer name} | asset | auto-created per customer |
| `3000` | Retained Earnings | equity | system |
| `4001` | DOC Fee Income | revenue | maps `doc_fee` |
| `4002` | Admin Fee Income | revenue | maps `admin_fee` |
| `4003` | Cleaning Income | revenue | maps `cleaning` |
| `4004` | Survey Income | revenue | maps `survey` |
| `4005` | Lift-On Income | revenue | maps `lift_on_20`/`lift_on_40` |
| `4006` | Detention Income | revenue | maps `det_20`/`det_40` |
| `4007` | FCL DG Income | revenue | maps `fcl_dg` |
| `4008` | Misc. Income | revenue | maps `misc` + unknown keys |
| `4100` | Service Revenue (fallback) | revenue | used when no key mapping |

**Migration fragment (`xxxx_create_chart_of_accounts_table.php`):**

```php
Schema::create('chart_of_accounts', function (Blueprint $table) {
    $table->id();
    $table->string('code', 20)->unique();
    $table->string('name');
    $table->string('type', 20);
    $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
    $table->boolean('is_active')->default(true);
    $table->boolean('is_system')->default(false);
    $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
    $table->timestamps();
});
```

### AR sub-account auto-creation

`AccountService::ensureCustomerArAccount($customerName)` returns the AR account for a
customer, creating `1100.{next}` named `AR – {customer}` when it does not exist. Resolution
by exact `name` match first; fall back to code sequence. This keeps per-customer receivable
balances while each entry line still references the originating `invoice_id` (see §4) for
per-invoice balance reporting.

## 3. Tables

### Table: `journal_entries`

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | bigint unsigned | — | primary key |
| `voucher_number` | string(30) | no | **unique**, `JV-YYYY-####` (see §6) |
| `entry_date` | date | no | source doc date |
| `memo` | text | yes | human description |
| `source_type` | string(20) | yes | `invoice` \| `money_receipt` |
| `source_id` | bigint unsigned | yes | FK to source doc (polymorphic, no FK constraint) |
| `total_debit` | decimal(18,2) | no | must equal `total_credit` |
| `total_credit` | decimal(18,2) | no | |
| `status` | string(20) | no | `draft` \| `approved` \| `voided`, default `draft` |
| `voided_at` | timestamp | yes | set when voided |
| `voided_by` | bigint unsigned | yes | FK → `employees.id` |
| `created_by` / `updated_by` | bigint unsigned | yes | FK → `employees.id` |
| `created_at` / `updated_at` | timestamp | — | |

```php
Schema::create('journal_entries', function (Blueprint $table) {
    $table->id();
    $table->string('voucher_number', 30)->unique();
    $table->date('entry_date');
    $table->text('memo')->nullable();
    $table->string('source_type', 20)->nullable()->index();
    $table->unsignedBigInteger('source_id')->nullable()->index();
    $table->decimal('total_debit', 18, 2);
    $table->decimal('total_credit', 18, 2);
    $table->string('status', 20)->default('draft')->index();
    $table->timestamp('voided_at')->nullable();
    $table->foreignId('voided_by')->nullable()->constrained('employees')->nullOnDelete();
    $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
    $table->foreignId('updated_by')->nullable()->constrained('employees')->nullOnDelete();
    $table->timestamps();
    $table->index(['source_type', 'source_id']);
});
```

### Table: `journal_entry_lines`

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | bigint unsigned | — | primary key |
| `journal_entry_id` | bigint unsigned | no | FK → `journal_entries.id` (cascade), indexed |
| `account_id` | bigint unsigned | no | FK → `chart_of_accounts.id` |
| `invoice_id` | bigint unsigned | yes | FK → `invoices.id` (set on AR lines for per-invoice receivable) |
| `reference` | string(255) | yes | source doc number (invoice no., MR no.) |
| `debit` | decimal(18,2) | no | exactly one of `debit`/`credit` > 0 |
| `credit` | decimal(18,2) | no | |
| `note` | string(255) | yes | free text |
| `created_at` / `updated_at` | timestamp | — | |

```php
Schema::create('journal_entry_lines', function (Blueprint $table) {
    $table->id();
    $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
    $table->foreignId('account_id')->constrained('chart_of_accounts');
    $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
    $table->string('reference')->nullable();
    $table->decimal('debit', 18, 2)->default(0);
    $table->decimal('credit', 18, 2)->default(0);
    $table->string('note')->nullable();
    $table->timestamps();
    $table->index('account_id');
    $table->index('invoice_id');
});
```

## 4. Eloquent Models

### `App\Models\ChartOfAccount`

```php
class ChartOfAccount extends Model
{
    protected $fillable = [
        'code', 'name', 'type', 'parent_id', 'is_active', 'is_system', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
    public function lines(): HasMany { return $this->hasMany(JournalEntryLine::class); }
}
```

### `App\Models\JournalEntry`

```php
class JournalEntry extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'voucher_number', 'entry_date', 'memo', 'source_type', 'source_id',
        'total_debit', 'total_credit', 'status', 'voided_at', 'voided_by',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'entry_date'   => 'date',
        'total_debit'  => 'decimal:2',
        'total_credit' => 'decimal:2',
        'voided_at'    => 'datetime',
    ];

    public function lines(): HasMany { return $this->hasMany(JournalEntryLine::class); }
}
```

### `App\Models\JournalEntryLine`

```php
class JournalEntryLine extends Model
{
    protected $fillable = [
        'journal_entry_id', 'account_id', 'invoice_id', 'reference',
        'debit', 'credit', 'note',
    ];

    protected $casts = [
        'debit'  => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function entry(): BelongsTo { return $this->belongsTo(JournalEntry::class, 'journal_entry_id'); }
    public function account(): BelongsTo { return $this->belongsTo(ChartOfAccount::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
}
```

## 5. `JournalService`

All posting logic lives in `App\Services\JournalService`. Every method runs inside the
source document's transaction so a failed entry rolls back the document save.

### Line BDT resolution (mirror of the frontend)

The frontend resolves a line's BDT total in `getItemTotalBdt()`:
`rate_usd * rate_bdt` → `total_usd * rate_bdt` → `rate_usd * (rate_bdt | exchange_rate)` →
`total_usd * (rate_bdt | exchange_rate)` → `rate_bdt`.

Add the same accessor on `InvoiceItem` (as described in `laravel-invoice-api.md`) and reuse
it here so entry amounts always match the printed invoice.

### `createInvoiceEntry(Invoice $invoice): JournalEntry`

```
Debit   AR – {customer}                        = Σ line BDT (= invoice.total_bdt)
Credit  Revenue account per invoice item key   = line BDT per item
```

- One credit line per invoice item; unknown/missing `key` → `misc` (`4008`).
- AR line sets `invoice_id = $invoice->id`, `reference = $invoice->invoice_number`.
- Memo: `Invoice {invoice_number} raised for {customer}`.
- `entry_date` = `invoice_date`; `source_type = 'invoice'`, `source_id = $invoice->id`.

### `createMoneyReceiptEntry(MoneyReceipt $mr): JournalEntry`

```
Debit   Bank / Cash (per payment_term) = Σ paid_amount (received total)
Credit  AR – {customer}                = paid_amount per money_receipt_invoice row
```

- Bank/cash account mapping by `payment_term` (configurable, see §7):
  `Cash → 1010`; `Cheque`, `Pay Order`, `Bank Transfer`, `TT / Wire Transfer`,
  `Online Payment` → `1020`.
- One credit line **per invoice** (`money_receipt_invoice.paid_amount`), each setting
  `invoice_id` and `reference = $mr->money_receipt_number`.
- Validate `Σ paid_amount == received total`; mismatch → throw `ValidationException` (422).
- Memo: `Payment received from {customer}`.
- `entry_date` = `money_receipt_date`; `source_type = 'money_receipt'`, `source_id = $mr->id`.

### Balancing & posting

```php
public function post(JournalEntry $entry): JournalEntry
{
    $this->assertBalanced($entry);          // throws if debits != credits

    DB::transaction(function () use ($entry) {
        $entry->lines()->delete();
        $entry->lines()->createMany($this->normalizedLines($entry->sourceType(), ...));
        $entry->update([
            'total_debit'  => $entry->lines->sum('debit'),
            'total_credit' => $entry->lines->sum('credit'),
            'voucher_number' => $this->nextVoucherNumber($entry->entry_date),
            'status'       => JournalEntry::STATUS_DRAFT,
        ]);
    });

    return $entry->fresh('lines');
}
```

### `approve(JournalEntry $entry, $userId)`

- Throw if not `draft`.
- Set `status = approved` (locks lines). Optionally store `approved_by`.

### `void(JournalEntry $entry, $userId)`

- Throw if already `voided`.
- If `draft`: delete the entry (no ledger impact yet).
- If `approved`: create a **reversing entry** — same accounts and `reference`, with debit/credit
  swapped, `source_type/source_id` copied, memo prefixed `Reversal of {voucher_number}`,
  `status = approved`; then mark the original `voided`.

## 6. Voucher numbering

`JV-{year}-{0001..}`. Next number derived from the latest `journal_entries` row for the same
year, generated inside the posting transaction (lock the row / use `lockForUpdate`) to avoid
duplicates.

## 7. Payment-term → account mapping

Store a `payments`-style configuration (e.g. a `payment_term_accounts` table or config file):

| payment_term | account |
|---|---|
| Cash | `1010` Cash in Hand |
| Cheque | `1020` Bank – One Bank PLC |
| Pay Order | `1020` Bank – One Bank PLC |
| Bank Transfer | `1020` Bank – One Bank PLC |
| TT / Wire Transfer | `1020` Bank – One Bank PLC |
| Online Payment | `1020` Bank – One Bank PLC |

## 8. API Resources

**GET `/api/journal-entries`** — list, newest first.

```json
{
  "data": [
    {
      "id": 1,
      "voucher_number": "JV-2026-0001",
      "entry_date": "2026-08-19",
      "memo": "Payment received from ACI Logistics",
      "source_type": "money_receipt",
      "source_id": 12,
      "total_debit": "135740.00",
      "total_credit": "135740.00",
      "status": "draft",
      "line_count": 2,
      "created_at": "2026-08-19T10:00:00.000000Z",
      "updated_at": "2026-08-19T10:00:00.000000Z"
    }
  ]
}
```

**GET `/api/journal-entries/{id}`**

```json
{
  "id": 1,
  "voucher_number": "JV-2026-0001",
  "entry_date": "2026-08-19",
  "memo": "Payment received from ACI Logistics",
  "source_type": "money_receipt",
  "source_id": 12,
  "total_debit": "135740.00",
  "total_credit": "135740.00",
  "status": "draft",
  "voided_at": null,
  "voided_by": null,
  "lines": [
    {
      "id": 1,
      "account_id": 1020,
      "account_code": "1020",
      "account_name": "Bank – One Bank PLC (RD 0021020013801)",
      "account_type": "asset",
      "invoice_id": null,
      "invoice_number": null,
      "reference": "MR/BCLL/2026/0819-101530",
      "debit": "135740.00",
      "credit": "0.00",
      "note": "Bank Transfer"
    },
    {
      "id": 2,
      "account_id": 1100,
      "account_code": "1100.001",
      "account_name": "AR – ACI Logistics",
      "account_type": "asset",
      "invoice_id": 5,
      "invoice_number": "MR/IMP/BCLL/26",
      "reference": "MR/BCLL/2026/0819-101530",
      "debit": "0.00",
      "credit": "135740.00",
      "note": "Against invoice MR/IMP/BCLL/26"
    }
  ],
  "created_at": "2026-08-19T10:00:00.000000Z",
  "updated_at": "2026-08-19T10:00:00.000000Z"
}
```

**POST `/api/journal-entries/{id}/approve`** — approve a draft entry. 409 if not draft.

**POST `/api/journal-entries/{id}/void`** — void (delete draft / reverse approved). 409 if already voided.

Line items are returned flat (`account_name`, `account_code`, `account_type`,
`invoice_number` denormalized onto the line) so the frontend can render without N+1 lookups.

## 9. Controller wiring into existing flows

- `InvoiceController@store/update` → `DB::transaction`: save invoice + bank details + items,
  then `JournalService::createInvoiceEntry($invoice)`.
- `MoneyReceiptController@store` → `DB::transaction`: save money receipt + items + invoice
  links, then `JournalService::createMoneyReceiptEntry($mr)`.
- `InvoiceController@destroy` / `MoneyReceiptController@destroy` → void the linked entry
  first (refuse if the source document is referenced by an approved entry unless voided).

## 10. Summary of Decisions

| Decision | Choice |
|---|---|
| Generation point | Backend, auto-created `draft` on source-doc save |
| Workflow | `draft` → `approved` → (`void` → reversal) |
| Functional currency | BDT only |
| AR granularity | One AR sub-account per customer; per-invoice tracking via `invoice_id` on lines |
| Revenue mapping | Invoice item `key` → revenue account; unknown keys → `misc` |
| Balancing | Debits == credits enforced; mismatch aborts source-doc save |
| Void handling | `draft` deleted; `approved` gets reversing entry |
| Voucher numbering | `JV-{year}-{seq}` |