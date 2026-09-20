# Laravel API Backend Spec — Import Shipment Invoice

This document defines the database schema, Eloquent models, and API resource shape for a
Laravel backend that persists the data entered on the invoice feature page
(`app/features/invoice/page.tsx`).

## 1. Data Overview

The invoice form holds three logical groups of data:

| Group | Purpose | Relation |
|---|---|---|
| **Invoice header** | Title, number, date, shipment details, exchange rate | 1 row per invoice |
| **Bank details** | Bank account the customer pays into | 1:1 with invoice |
| **Line items** | Fee rows (DOC Fee, Admin Fee, Cleaning, etc.) | 1:N with invoice |

### Derived values — do not store what can be computed

The BDT total per line is computed at runtime by `getItemTotalBdt()`
(`app/features/invoice/page.tsx:41-60`):

```ts
if (rateUsd && rateBdt)       return rateUsd * rateBdt;         // explicit rate * rate
if (totalUsd && rateBdt)      return totalUsd * rateBdt;
if (rateUsd)                  return rateUsd * (rateBdt || exRate);
if (totalUsd)                 return totalUsd * (rateBdt || exRate);
return rateBdt;
```

The same resolution order should be mirrorred in the backend (e.g. in a model mutator or a
service) so totals stay consistent. The individual rate/total columns are stored; the computed
line BDT is not stored.

Denormalized invoice-level totals (`total_usd`, `total_bdt`) may optionally be stored for fast
listing, but must be kept in sync with the line items.

---

## 2. Table: `invoices`

One row per invoice.

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | bigint unsigned | — | auto | primary key |
| `title` | string(255) | yes | `Invoice for Import Shipment` | |
| `invoice_number` | string(50) | no | — | **unique**, indexed |
| `invoice_date` | date | yes | `today` | named `invoice_date`, not `date` |
| `bl_number` | string(100) | yes | — | B/L number |
| `customer_name` | string(255) | yes | — | Customer's name |
| `vessel` | string(100) | yes | — | |
| `voyage` | string(50) | yes | — | |
| `registration_no` | string(100) | yes | — | |
| `containers` | string(255) | yes | — | free-text container(s) |
| `exchange_rate` | decimal(14,4) | yes | `110.0000` | USD → BDT |
| `amount_in_words` | text | yes | — | "In Word" value |
| `total_usd` | decimal(18,2) | yes | — | denormalized, keep in sync |
| `total_bdt` | decimal(18,2) | yes | — | denormalized, keep in sync |
| `created_at` | timestamp | — | — | |
| `updated_at` | timestamp | — | — | |

**Migration fragment (`database/migrations/xxxx_create_invoices_table.php`):**

```php
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->string('title')->nullable();
    $table->string('invoice_number')->unique();
    $table->date('invoice_date')->nullable();
    $table->string('bl_number')->nullable();
    $table->string('customer_name')->nullable();
    $table->string('vessel')->nullable();
    $table->string('voyage')->nullable();
    $table->string('registration_no')->nullable();
    $table->string('containers')->nullable();
    $table->decimal('exchange_rate', 14, 4)->nullable();
    $table->text('amount_in_words')->nullable();
    $table->decimal('total_usd', 18, 2)->nullable();
    $table->decimal('total_bdt', 18, 2)->nullable();
    $table->timestamps();
    $table->index('invoice_number');
    $table->index('invoice_date');
});
```

---

## 3. Table: `invoice_bank_details`

One bank-detail record per invoice (1:1).

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | bigint unsigned | — | primary key |
| `invoice_id` | bigint unsigned | no | FK → `invoices.id` (cascade), **unique** |
| `account_name` | string(255) | yes | |
| `rd_account_no` | string(100) | yes | RD account number |
| `bank_name` | string(255) | yes | |
| `branch_name` | string(255) | yes | |
| `swift_code` | string(20) | yes | SWIFT/BIC code |
| `routing_no` | string(50) | yes | |
| `address` | text | yes | full bank address |
| `created_at` | timestamp | — | |
| `updated_at` | timestamp | — | |

**Migration fragment (`xxxx_create_invoice_bank_details_table.php`):**

```php
Schema::create('invoice_bank_details', function (Blueprint $table) {
    $table->id();
    $table->foreignId('invoice_id')->unique()->constrained()->cascadeOnDelete();
    $table->string('account_name')->nullable();
    $table->string('rd_account_no')->nullable();
    $table->string('bank_name')->nullable();
    $table->string('branch_name')->nullable();
    $table->string('swift_code')->nullable();
    $table->string('routing_no')->nullable();
    $table->text('address')->nullable();
    $table->timestamps();
});
```

---

## 4. Table: `invoice_items`

Line items belonging to an invoice (1:N).

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | bigint unsigned | — | — | primary key |
| `invoice_id` | bigint unsigned | no | — | FK → `invoices.id` (cascade), indexed |
| `key` | string(50) | yes | — | stable slug (`doc_fee`, `admin_fee`, …) or null for ad-hoc rows |
| `label` | string(255) | yes | — | Particulars text |
| `qty_20` | integer unsigned | yes | `0` | 20' quantity |
| `qty_40` | integer unsigned | yes | `0` | 40' quantity |
| `rate_usd` | decimal(14,4) | yes | — | |
| `rate_bdt` | decimal(14,4) | yes | — | |
| `total_usd` | decimal(18,2) | yes | — | |
| `created_at` | timestamp | — | — | |
| `updated_at` | timestamp | — | — | |

> **Naming note:** the UI fields are `qty20`/`qty40`. The vertical apostrophe is part of the
> rendered label, not the field name, so the DB columns are `qty_20`/`qty_40`.

**Migration fragment (`xxxx_create_invoice_items_table.php`):**

```php
Schema::create('invoice_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
    $table->string('key')->nullable();
    $table->string('label')->nullable();
    $table->unsignedInteger('qty_20')->default(0);
    $table->unsignedInteger('qty_40')->default(0);
    $table->decimal('rate_usd', 14, 4)->nullable();
    $table->decimal('rate_bdt', 14, 4)->nullable();
    $table->decimal('total_usd', 18, 2)->nullable();
    $table->timestamps();
    $table->index('invoice_id');
});
```

---

## 5. Eloquent Models

### `App\Models\Invoice`

```php
class Invoice extends Model
{
    protected $fillable = [
        'title', 'invoice_number', 'invoice_date', 'bl_number', 'customer_name',
        'vessel', 'voyage', 'registration_no', 'containers', 'exchange_rate',
        'amount_in_words', 'total_usd', 'total_bdt',
    ];

    protected $casts = [
        'invoice_date'  => 'date',
        'exchange_rate' => 'decimal:4',
        'total_usd'     => 'decimal:2',
        'total_bdt'     => 'decimal:2',
    ];

    public function bankDetails(): HasOne
    {
        return $this->hasOne(InvoiceBankDetail::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
```

### `App\Models\InvoiceBankDetail`

```php
class InvoiceBankDetail extends Model
{
    protected $fillable = [
        'invoice_id', 'account_name', 'rd_account_no', 'bank_name',
        'branch_name', 'swift_code', 'routing_no', 'address',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
```

### `App\Models\InvoiceItem`

```php
class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id', 'key', 'label', 'qty_20', 'qty_40',
        'rate_usd', 'rate_bdt', 'total_usd',
    ];

    protected $casts = [
        'qty_20'    => 'integer',
        'qty_40'    => 'integer',
        'rate_usd'  => 'decimal:4',
        'rate_bdt'  => 'decimal:4',
        'total_usd' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
```

> Derived line BDT: add an accessor on `InvoiceItem` mirroring the frontend resolution order
> (`rate_usd * rate_bdt` → `total_usd * rate_bdt` → `rate_usd * (rate_bdt | exchange_rate)` →
> `total_usd * (rate_bdt | exchange_rate)` → `rate_bdt`) rather than storing it.

---

## 6. API Resource Shape

Use a **nested** payload so the JSON round-trips cleanly with the form component.

**GET `GET /api/invoices/{invoice}`**

```json
{
  "id": 1,
  "invoice_number": "MR/IMP/BCLL/26",
  "title": "Invoice for Import Shipment",
  "invoice_date": "2026-08-11",
  "bl_number": "...",
  "customer_name": "...",
  "vessel": "...",
  "voyage": "...",
  "registration_no": "...",
  "containers": "...",
  "exchange_rate": "110.0000",
  "amount_in_words": "...",
  "total_usd": "1234.00",
  "total_bdt": "135740.00",
  "bank_details": {
    "account_name": "Bangladesh Container Lines Limited",
    "rd_account_no": "0021020013801",
    "bank_name": "One Bank PLC",
    "branch_name": "Gulshan-1 Branch",
    "swift_code": "ONEBDDH003",
    "routing_no": "165261726",
    "address": "Richmond Concord, ..."
  },
  "items": [
    { "key": "doc_fee", "label": "DOC Fee", "qty_20": 1, "qty_40": 0,
      "rate_usd": "10.0000", "rate_bdt": null, "total_usd": "10.00" }
  ],
  "created_at": "2026-08-11T10:00:00.000000Z",
  "updated_at": "2026-08-11T10:00:00.000000Z"
}
```

**STORE / UPDATE:** accept the same nested payload; save header + `bank_details` + `items`
transactionally:

```php
public function store(StoreInvoiceRequest $request)
{
    return DB::transaction(function () use ($request) {
        $invoice = Invoice::create($request->only([
            'title', 'invoice_number', 'invoice_date', 'bl_number', 'customer_name',
            'vessel', 'voyage', 'registration_no', 'containers', 'exchange_rate',
            'amount_in_words',
        ]));

        $invoice->bankDetails()->updateOrCreate([], $request->input('bank_details', []));

        foreach ($request->input('items', []) as $item) {
            $invoice->items()->create($item);
        }

        return response()->json($invoice->load('bankDetails', 'items'), 201);
    });
}
```

---

## 7. Summary of Decisions

| Decision | Choice |
|---|---|
| Quantity columns | `qty_20` / `qty_40` |
| API payload | nested (`bank_details`, `items`) |
| Item `key` slug | stored (nullable, for stable grouping/ordering) |
| Derived line BDT | computed in accessor, never stored |
| Invoice totals (`total_usd`, `total_bdt`) | optional denormalized columns, kept in sync |