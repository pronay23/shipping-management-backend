# Accounting Flow — Vendor Master to Financial Reports

## 1. Overview

This document describes the complete Accounts Payable (AP) accounting cycle in the Shipping Management system, from vendor master data through to financial reporting.

```
┌─────────────────┐
│  VENDOR MASTER   │  Vendor master data (name, code, category, country, currency)
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│   AP INVOICE     │  Record vendor invoices (bills received from vendors)
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│   AP PAYMENT     │  Record payments made to vendors
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ JOURNAL ENTRY    │  Double-entry bookkeeping (draft → approved → posted)
└────────┬────────┘
         │
         ▼
┌─────────────────────┐
│ JOURNAL ENTRY LINES  │  Individual debit/credit lines per account
└────────┬────────────┘
         │
         ▼
┌─────────────────┐
│ GENERAL LEDGER   │  Chronological per-account transaction history
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ TRIAL BALANCE    │  Summary of all account balances (debits = credits)
└────────┬────────┘
         │
    ┌────┴────┐
    ▼         ▼
┌────────┐ ┌──────────────┐
│P & L   │ │ BALANCE SHEET│
│Report  │ │   Report     │
└────────┘ └──────────────┘
```

---

## 2. Gap Analysis — Current vs Target

### What Exists (AR — Accounts Receivable)

| Component | Status | Files |
|-----------|--------|-------|
| Customer Invoice (AR) | ✅ Exists | `Invoice`, `InvoiceItem`, `InvoiceBankDetail` |
| Money Receipt (AR Payment) | ✅ Exists | `MoneyReceipt`, `MoneyReceiptInvoice` |
| Journal Entry | ✅ Exists | `JournalEntry`, `JournalEntryLine` |
| Journal Service | ✅ Exists | `JournalService.php` (493 lines) |
| General Ledger | ✅ Exists | `LedgerReportService::accountLedger()` |
| Trial Balance | ✅ Exists | `LedgerReportService::trialBalance()` |
| Profit & Loss | ✅ Exists | `LedgerReportService::profitAndLoss()` |
| AR Aging | ✅ Exists | `LedgerReportService::arAging()` |
| Chart of Accounts | ✅ Exists | `ChartOfAccount`, `AccountType` |

### What's Missing (AP — Accounts Payable)

| Component | Status | Action Required |
|-----------|--------|-----------------|
| Vendor Master | ✅ Exists | `Vendor` model created (just built) |
| AP Invoice | ❌ Does not exist | New module: `ApInvoice`, `ApInvoiceItem` |
| AP Payment | ❌ Does not exist | New module: `ApPayment`, `ApPaymentInvoice` |
| AP Journal Entries | ❌ Partially exists | Extend `JournalService` with AP source types |
| Vendor AP Sub-accounts | ❌ Does not exist | Extend `AccountService` with `ensureVendorApAccount()` |
| AP Aging Report | ❌ Does not exist | New method in `LedgerReportService` |
| Balance Sheet | ❌ Does not exist | New method in `LedgerReportService` |
| Expense Account Mapping | ❌ Does not exist | Define `EXPENSE_MAP` for AP invoice items |

### Existing Journal Entry Source Types

Current `source_type` values in `journal_entries`:
- `'invoice'` — Customer invoice (AR)
- `'money_receipt'` — Customer payment (AR)

**New source types needed:**
- `'ap_invoice'` — Vendor invoice (AP)
- `'ap_payment'` — Vendor payment (AP)

---

## 3. Entity Relationship Diagram

### Current Entities

```
┌──────────────┐     ┌──────────────────┐     ┌─────────────────┐
│ AccountType  │────<│  ChartOfAccount   │────<│ JournalEntryLine │
│ (asset,      │     │ (code, name,      │     │ (debit, credit,  │
│  liability,  │     │  type, parent_id) │     │  account_id)     │
│  equity,     │     └──────────────────┘     └────────┬────────┘
│  revenue,    │                                       │
│  expense)    │                                       │
└──────────────┘                                       │
                                                       │
┌──────────────┐     ┌──────────────────┐              │
│   Employee   │────<│  JournalEntry     │─────────────┘
│              │     │ (voucher_number,  │
│              │     │  entry_date,      │
│              │     │  source_type,     │
│              │     │  source_id,       │
│              │     │  status)          │
└──────────────┘     └────────┬─────────┘
                              │
              ┌───────────────┼───────────────┐
              │               │               │
              ▼               ▼               ▼
       ┌────────────┐ ┌────────────┐ ┌────────────────┐
       │  Invoice   │ │  Money     │ │  AP Invoice    │
       │  (AR)      │ │  Receipt   │ │  (NEW)         │
       └────────────┘ └────────────┘ └────────────────┘
```

### New Entities Needed

```
┌──────────────┐
│   Vendor     │  (exists: vendor_code, vendor_name, vendor_category_id, country_id, currency_id)
└──────┬───────┘
       │
       ├──<┌─────────────────┐
       │   │   AP Invoice     │  (vendor_id, invoice_number, invoice_date, total_bdt, status)
       │   └────────┬────────┘
       │            │
       │            ├──<┌──────────────────┐
       │            │   │ AP Invoice Item   │  (ap_invoice_id, description, quantity, rate, total)
       │            │   └──────────────────┘
       │            │
       │            └──>┌──────────────────┐
       │                │ JournalEntry      │  (source_type = 'ap_invoice')
       │                └──────────────────┘
       │
       └──<┌─────────────────┐
           │   AP Payment     │  (vendor_id, payment_number, payment_date, amount, payment_method)
           └────────┬────────┘
                    │
                    └──>┌──────────────────┐
                        │ JournalEntry      │  (source_type = 'ap_payment')
                        └──────────────────┘
```

---

## 4. Current AR Flow (What Exists)

### 4.1 Customer Invoice → Journal Entry

When an invoice is created, `JournalService::createInvoiceEntry()` generates a **draft** journal entry:

```
Source: Invoice #INV-2026-0001 for customer "ACME Corp"
Items:
  - DOC Fee:        5,000 BDT  → Account 4001 (DOC Fee Income)
  - Admin Fee:      3,000 BDT  → Account 4002 (Admin Fee Income)
  - Cleaning:       2,000 BDT  → Account 4003 (Cleaning Income)

Journal Entry (Draft):
  Voucher: JV-2026-0042
  Date:    2026-08-15
  Memo:    Invoice INV-2026-0001 raised for ACME Corp

  Lines:
  ┌─────────────────────────────────┬──────────┬──────────┐
  │ Account                         │  Debit   │  Credit  │
  ├─────────────────────────────────┼──────────┼──────────┤
  │ 1100.001 - AR - ACME Corp       │ 10,000.00│          │
  │ 4001 - DOC Fee Income           │          │  5,000.00│
  │ 4002 - Admin Fee Income         │          │  3,000.00│
  │ 4003 - Cleaning Income          │          │  2,000.00│
  └─────────────────────────────────┴──────────┴──────────┘
  Total Debit: 10,000.00 = Total Credit: 10,000.00 ✓
```

### 4.2 Money Receipt → Journal Entry

When payment is received, `JournalService::createMoneyReceiptEntry()` generates a draft entry:

```
Source: Money Receipt #MR-2026-0015 from customer "ACME Corp"
Payment: Cheque, 8,000 BDT against Invoice INV-2026-0001

Journal Entry (Draft):
  Voucher: JV-2026-0043
  Date:    2026-08-20
  Memo:    Payment received from ACME Corp

  Lines:
  ┌─────────────────────────────────┬──────────┬──────────┐
  │ Account                         │  Debit   │  Credit  │
  ├─────────────────────────────────┼──────────┼──────────┤
  │ 1020 - Bank - One Bank PLC      │  8,000.00│          │
  │ 1100.001 - AR - ACME Corp       │          │  8,000.00│
  └─────────────────────────────────┴──────────┴──────────┘
```

### 4.3 Approval & Voiding

- **Draft** → `approve()` → **Approved** (posts to General Ledger)
- **Approved** → `void()` → **Voided** + auto-created **Reversing Entry** (debit/credit swapped)
- **Draft** → `void()` → Deleted (no ledger impact)

---

## 5. Target AP Flow (What Needs to Be Built)

### 5.1 AP Invoice → Journal Entry

When a vendor invoice is received, the system creates a journal entry:

```
Source: AP Invoice #API-2026-0001 from vendor "Global Shipping Lines"
Items:
  - Ocean Freight:    50,000 BDT  → Expense account
  - Terminal Handling: 15,000 BDT  → Expense account

Journal Entry (Draft):
  Voucher: JV-2026-0100
  Date:    2026-08-25
  Memo:    AP Invoice API-2026-0001 from Global Shipping Lines

  Lines:
  ┌──────────────────────────────────────────┬──────────┬──────────┐
  │ Account                                  │  Debit   │  Credit  │
  ├──────────────────────────────────────────┼──────────┼──────────┤
  │ 5001 - Ocean Freight Expense             │ 50,000.00│          │
  │ 5002 - Terminal Handling Expense          │ 15,000.00│          │
  │ 2100.001 - AP - Global Shipping Lines    │          │ 65,000.00│
  └──────────────────────────────────────────┴──────────┴──────────┘
```

**Key difference from AR:**
- AR Invoice: Debit AR (asset), Credit Revenue
- **AP Invoice: Debit Expense, Credit AP (liability)**

### 5.2 AP Payment → Journal Entry

When payment is made to a vendor:

```
Source: AP Payment #APP-2026-0005 to vendor "Global Shipping Lines"
Payment: Bank Transfer, 65,000 BDT against AP Invoice API-2026-0001

Journal Entry (Draft):
  Voucher: JV-2026-0101
  Date:    2026-09-05
  Memo:    Payment to Global Shipping Lines

  Lines:
  ┌──────────────────────────────────────────┬──────────┬──────────┐
  │ Account                                  │  Debit   │  Credit  │
  ├──────────────────────────────────────────┼──────────┼──────────┤
  │ 2100.001 - AP - Global Shipping Lines    │ 65,000.00│          │
  │ 1020 - Bank - One Bank PLC               │          │ 65,000.00│
  └──────────────────────────────────────────┴──────────┴──────────┘
```

**Key difference from AR:**
- AR Payment: Debit Cash/Bank (asset), Credit AR (asset)
- **AP Payment: Debit AP (liability), Credit Cash/Bank (asset)**

---

## 6. Journal Entry Patterns — Complete Reference

### 6.1 All Transaction Types

| Transaction | Source Type | Debit | Credit |
|-------------|-------------|-------|--------|
| Customer Invoice | `invoice` | AR sub-account (customer) | Revenue accounts (per item) |
| Customer Payment | `money_receipt` | Cash/Bank account | AR sub-account (customer) |
| **Vendor Invoice** | `ap_invoice` | Expense accounts (per item) | AP sub-account (vendor) |
| **Vendor Payment** | `ap_payment` | AP sub-account (vendor) | Cash/Bank account |

### 6.2 Account Code Conventions

```
1000 - Assets
├── 1010 - Cash in Hand
├── 1020 - Bank - One Bank PLC
├── 1100 - Accounts Receivable (control)
│   ├── 1100.001 - AR - ACME Corp
│   ├── 1100.002 - AR - Global Lines
│   └── 1100.NNN - AR - [Customer Name]
│
2000 - Liabilities
├── 2100 - Accounts Payable (control)        ← NEW
│   ├── 2100.001 - AP - Global Shipping Lines ← NEW
│   ├── 2100.002 - AP - Pacific Overseas      ← NEW
│   └── 2100.NNN - AP - [Vendor Name]         ← NEW
│
3000 - Equity
├── 3000 - Retained Earnings
│
4000 - Revenue
├── 4001 - DOC Fee Income
├── 4002 - Admin Fee Income
├── 4003 - Cleaning Income
├── 4004 - Survey Income
├── 4005 - Lift-On Income
├── 4006 - Detention Income
├── 4007 - FCL DG Income
├── 4008 - Misc. Income
│
5000 - Expenses                                              ← NEW
├── 5001 - Ocean Freight Expense                             ← NEW
├── 5002 - Terminal Handling Expense                          ← NEW
├── 5003 - Port Charges Expense                              ← NEW
├── 5004 - C&F Commission Expense                            ← NEW
├── 5005 - Customs Duty Expense                              ← NEW
├── 5006 - Transport Expense                                 ← NEW
├── 5007 - Warehouse Expense                                 ← NEW
├── 5008 - Insurance Expense                                 ← NEW
├── 5009 - Office Supplies Expense                           ← NEW
└── 5099 - Miscellaneous Expense                             ← NEW
```

---

## 7. Data Flow — Step by Step

### 7.1 AP Invoice Creation Flow

```
User fills AP Invoice form
    │
    ▼
POST /api/ap-invoices
    │
    ├── 1. Validate request data
    ├── 2. Create ApInvoice record (vendor_id, invoice_number, totals)
    ├── 3. Create ApInvoiceItem records (description, qty, rate, amount)
    │
    ▼
JournalService::createApInvoiceEntry($apInvoice)
    │
    ├── 4. Ensure vendor AP sub-account exists (2100.NNN)
    │       └── AccountService::ensureVendorApAccount($vendorName)
    │
    ├── 5. Map each ApInvoiceItem to an expense account (EXPENSE_MAP)
    │
    ├── 6. Build journal entry lines:
    │       DEBIT: Expense account (per item) → item total
    │       CREDIT: AP sub-account (vendor)   → invoice total
    │
    ├── 7. Create JournalEntry (status = 'draft')
    │       ├── source_type = 'ap_invoice'
    │       ├── source_id = ap_invoice.id
    │       └── voucher_number = JV-YYYY-NNNN
    │
    └── 8. Create JournalEntryLine records
```

### 7.2 AP Payment Creation Flow

```
User fills AP Payment form
    │
    ▼
POST /api/ap-payments
    │
    ├── 1. Validate request data
    ├── 2. Create ApPayment record (vendor_id, payment_number, amount)
    ├── 3. Create ApPaymentInvoice records (ap_invoice_id, paid_amount)
    │
    ▼
JournalService::createApPaymentEntry($apPayment)
    │
    ├── 4. Resolve bank/cash account from payment_method
    │       └── config('journal.payment_methods.{method}')
    │
    ├── 5. Get vendor AP sub-account (2100.NNN)
    │
    ├── 6. Build journal entry lines:
    │       DEBIT: AP sub-account (vendor)  → sum of paid_amounts
    │       CREDIT: Cash/Bank account       → total payment
    │
    ├── 7. Create JournalEntry (status = 'draft')
    │       ├── source_type = 'ap_payment'
    │       ├── source_id = ap_payment.id
    │       └── voucher_number = JV-YYYY-NNNN
    │
    └── 8. Create JournalEntryLine records
```

### 7.3 Approval Flow (Same as AR)

```
Draft Journal Entry
    │
    ▼
POST /api/journal-entries/{id}/approve
    │
    ├── 1. Check status = 'draft'
    ├── 2. Check period not locked
    ├── 3. Row-level lock (prevent concurrent approval)
    ├── 4. Update status → 'approved'
    │
    ▼
Entry now visible in General Ledger, Trial Balance, P&L
```

### 7.4 Voiding Flow (Same as AR)

```
Approved Journal Entry
    │
    ▼
POST /api/journal-entries/{id}/void
    │
    ├── 1. Create REVERSING entry:
    │       ├── All debit/credit amounts SWAPPED
    │       ├── status = 'approved' (auto-approved)
    │       ├── reverses_journal_entry_id = original.id
    │       └── voucher_number = new sequential number
    │
    ├── 2. Mark original as 'voided'
    │       ├── voided_at = now()
    │       └── voided_by = employee_id
    │
    ▼
Both entries (original + reversal) appear in ledger, net to zero
```

---

## 8. General Ledger → Trial Balance → Reports

### 8.1 General Ledger

The General Ledger is a per-account chronological view of all posted transactions:

```
Account: 2100.001 - AP - Global Shipping Lines
─────────────────────────────────────────────────────────────────────────────
Date       │ Voucher      │ Reference     │ Debit      │ Credit     │ Balance
───────────┼──────────────┼───────────────┼────────────┼────────────┼──────────
2026-08-25 │ JV-2026-0100 │ API-2026-0001 │            │ 65,000.00  │ -65,000.00
2026-09-05 │ JV-2026-0101 │ APP-2026-0005 │ 65,000.00  │            │       0.00
───────────┴──────────────┴───────────────┴────────────┴────────────┴──────────
Opening: 0.00    Period Dr: 65,000.00    Period Cr: 65,000.00    Closing: 0.00
```

### 8.2 Trial Balance

Aggregates all account balances from approved journal entry lines:

```
Trial Balance (2026-08-01 to 2026-08-31)
─────────────────────────────────────────────────────────────────────────────
Code   │ Account Name                    │ Debit        │ Credit       │ Balance
───────┼─────────────────────────────────┼──────────────┼──────────────┼──────────
1010   │ Cash in Hand                    │  50,000.00   │              │  50,000.00
1020   │ Bank - One Bank PLC             │ 200,000.00   │  65,000.00   │ 135,000.00
1100.1 │ AR - ACME Corp                  │  10,000.00   │   8,000.00   │   2,000.00
2100.1 │ AP - Global Shipping Lines      │  65,000.00   │  65,000.00   │       0.00
4001   │ DOC Fee Income                  │              │  15,000.00   │ -15,000.00
4002   │ Admin Fee Income                │              │  10,000.00   │ -10,000.00
5001   │ Ocean Freight Expense           │  50,000.00   │              │  50,000.00
5002   │ Terminal Handling Expense        │  15,000.00   │              │  15,000.00
───────┴─────────────────────────────────┴──────────────┴──────────────┴──────────
TOTAL                                 │ 390,000.00   │ 223,000.00   │
                                      │        IS BALANCED ✓
```

> **Note:** Negative balances on revenue/equity accounts indicate credit balances (normal).

### 8.3 Profit & Loss Report

Revenue and expense accounts only, sign-normalized:

```
Profit & Loss (2026-08-01 to 2026-08-31)
═══════════════════════════════════════════════════════════════════════════════

REVENUE
  4001 - DOC Fee Income                       15,000.00
  4002 - Admin Fee Income                     10,000.00
  4003 - Cleaning Income                       5,000.00
  4004 - Survey Income                         3,000.00
  ─────────────────────────────────────────────────────
  Total Revenue                                           33,000.00

EXPENSES
  5001 - Ocean Freight Expense                50,000.00
  5002 - Terminal Handling Expense             15,000.00
  5003 - Port Charges Expense                  8,000.00
  5004 - C&F Commission Expense                5,000.00
  ─────────────────────────────────────────────────────
  Total Expense                                           78,000.00

═══════════════════════════════════════════════════════════════════════════════
NET PROFIT / (LOSS)                                    (45,000.00)
═══════════════════════════════════════════════════════════════════════════════
```

### 8.4 Balance Sheet (To Be Built)

```
Balance Sheet (as of 2026-08-31)
═══════════════════════════════════════════════════════════════════════════════

ASSETS
  Current Assets
    1010 - Cash in Hand                          50,000.00
    1020 - Bank - One Bank PLC                  135,000.00
    1100 - Accounts Receivable                    2,000.00
  ─────────────────────────────────────────────────────────
  Total Assets                                             187,000.00

LIABILITIES
  Current Liabilities
    2100 - Accounts Payable                       10,000.00
  ─────────────────────────────────────────────────────────
  Total Liabilities                                         10,000.00

EQUITY
    3000 - Retained Earnings                     122,000.00
    Current Period Net Profit / (Loss)           (45,000.00) ← from P&L
  ─────────────────────────────────────────────────────────
  Total Equity                                             177,000.00

═══════════════════════════════════════════════════════════════════════════════
TOTAL LIABILITIES + EQUITY                          187,000.00
═══════════════════════════════════════════════════════════════════════════════
```

---

## 9. Database Schema — New Tables

### 9.1 `ap_invoices`

```sql
CREATE TABLE ap_invoices (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_id       BIGINT UNSIGNED NOT NULL,
    ap_invoice_number VARCHAR(50) NOT NULL UNIQUE,
    invoice_date    DATE NOT NULL,
    due_date        DATE NULL,
    reference       VARCHAR(100) NULL,
    description     TEXT NULL,
    total_bdt       DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    tax_amount      DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    net_amount      DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    paid_amount     DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    due_amount      DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    status          VARCHAR(20) NOT NULL DEFAULT 'unpaid',  -- unpaid|partial|paid|voided
    notes           TEXT NULL,
    created_by      BIGINT UNSIGNED NULL,
    updated_by      BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE RESTRICT,
    INDEX idx_ap_invoices_vendor (vendor_id),
    INDEX idx_ap_invoices_status (status),
    INDEX idx_ap_invoices_date (invoice_date)
);
```

### 9.2 `ap_invoice_items`

```sql
CREATE TABLE ap_invoice_items (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ap_invoice_id   BIGINT UNSIGNED NOT NULL,
    account_id      BIGINT UNSIGNED NOT NULL,  -- Expense account to debit
    description     VARCHAR(255) NOT NULL,
    quantity        DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price      DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    total_bdt       DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    notes           TEXT NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    FOREIGN KEY (ap_invoice_id) REFERENCES ap_invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id) ON DELETE RESTRICT,
    INDEX idx_ap_invoice_items_invoice (ap_invoice_id)
);
```

### 9.3 `ap_payments`

```sql
CREATE TABLE ap_payments (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_id           BIGINT UNSIGNED NOT NULL,
    ap_payment_number   VARCHAR(50) NOT NULL UNIQUE,
    payment_date        DATE NOT NULL,
    payment_method      VARCHAR(50) NOT NULL,  -- cash|cheque|tt|bank_transfer
    bank_account_id     BIGINT UNSIGNED NULL,  -- Which bank account paid
    reference_number    VARCHAR(100) NULL,
    amount              DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    notes               TEXT NULL,
    created_by          BIGINT UNSIGNED NULL,
    updated_by          BIGINT UNSIGNED NULL,
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,

    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE RESTRICT,
    FOREIGN KEY (bank_account_id) REFERENCES chart_of_accounts(id) ON DELETE SET NULL,
    INDEX idx_ap_payments_vendor (vendor_id),
    INDEX idx_ap_payments_date (payment_date)
);
```

### 9.4 `ap_payment_invoices` (Pivot)

```sql
CREATE TABLE ap_payment_invoices (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ap_payment_id   BIGINT UNSIGNED NOT NULL,
    ap_invoice_id   BIGINT UNSIGNED NOT NULL,
    paid_amount     DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    FOREIGN KEY (ap_payment_id) REFERENCES ap_payments(id) ON DELETE CASCADE,
    FOREIGN KEY (ap_invoice_id) REFERENCES ap_invoices(id) ON DELETE RESTRICT,
    INDEX idx_ap_payment_invoices_payment (ap_payment_id),
    INDEX idx_ap_payment_invoices_invoice (ap_invoice_id)
);
```

### 9.5 Changes to `journal_entries`

```sql
-- No schema change needed, but source_type enum expands:
-- Existing: 'invoice', 'money_receipt'
-- New:     'ap_invoice', 'ap_payment'
```

### 9.6 Changes to `journal_entry_lines`

```sql
-- Optional: Add ap_invoice_id for AP line tracing (like existing invoice_id)
ALTER TABLE journal_entry_lines
    ADD COLUMN ap_invoice_id BIGINT UNSIGNED NULL AFTER invoice_id,
    ADD FOREIGN KEY (ap_invoice_id) REFERENCES ap_invoices(id) ON DELETE SET NULL;
```

---

## 10. Implementation Roadmap

### Phase 1: Chart of Accounts Setup
- [ ] Seed expense accounts (5001-5099) in `ChartOfAccountSeeder`
- [ ] Seed AP control account (2100) as system account
- [ ] Add `ensureVendorApAccount()` method to `AccountService`

### Phase 2: AP Invoice Module
- [ ] Create `ApInvoice` model with relationships
- [ ] Create `ApInvoiceItem` model
- [ ] Create migration for `ap_invoices` table
- [ ] Create migration for `ap_invoice_items` table
- [ ] Create `ApInvoiceController` (CRUD)
- [ ] Create `ApInvoiceSeeder` with sample data
- [ ] Add API route: `Route::apiResource('ap-invoices', ...)`

### Phase 3: AP Payment Module
- [ ] Create `ApPayment` model with relationships
- [ ] Create `ApPaymentInvoice` pivot model
- [ ] Create migration for `ap_payments` table
- [ ] Create migration for `ap_payment_invoices` table
- [ ] Create `ApPaymentController` (CRUD)
- [ ] Create `ApPaymentSeeder` with sample data
- [ ] Add API route: `Route::apiResource('ap-payments', ...)`

### Phase 4: Extend JournalService
- [ ] Add `createApInvoiceEntry()` method
- [ ] Add `createApPaymentEntry()` method
- [ ] Add `apInvoiceEntryLines()` private method
- [ ] Add `apPaymentEntryLines()` private method
- [ ] Add `ensureVendorApAccount()` to `AccountService`
- [ ] Add `EXPENSE_MAP` constant for item key → account code mapping
- [ ] Extend `sourceFor()` to handle `'ap_invoice'` and `'ap_payment'` types
- [ ] Extend `memoFor()` for AP sources

### Phase 5: Extend LedgerReportService
- [ ] Add `apAging()` method (mirrors `arAging()` for vendors)
- [ ] Add `balanceSheet()` method (Assets = Liabilities + Equity)
- [ ] Add API endpoints for AP aging and Balance Sheet

### Phase 6: Frontend Pages
- [ ] AP Invoice: Create page, List page, View page
- [ ] AP Payment: Create page, List page, View page
- [ ] Balance Sheet report page
- [ ] AP Aging report page
- [ ] Update AppShell navigation with AP module links

---

## 11. API Endpoints — Proposed

### AP Invoices
| Method | URI | Description |
|--------|-----|-------------|
| GET | `/api/ap-invoices` | List all AP invoices |
| POST | `/api/ap-invoices` | Create new AP invoice |
| GET | `/api/ap-invoices/{id}` | Get AP invoice with items |
| PUT | `/api/ap-invoices/{id}` | Update AP invoice |
| DELETE | `/api/ap-invoices/{id}` | Delete AP invoice |

### AP Payments
| Method | URI | Description |
|--------|-----|-------------|
| GET | `/api/ap-payments` | List all AP payments |
| POST | `/api/ap-payments` | Create new AP payment |
| GET | `/api/ap-payments/{id}` | Get AP payment with links |
| PUT | `/api/ap-payments/{id}` | Update AP payment |
| DELETE | `/api/ap-payments/{id}` | Delete AP payment |

### Reports (New)
| Method | URI | Description |
|--------|-----|-------------|
| GET | `/api/reports/ap-aging` | AP aging report (by vendor) |
| GET | `/api/reports/balance-sheet` | Balance Sheet report |

---

## 12. Key Design Principles

1. **Double-Entry Bookkeeping**: Every transaction has equal debits and credits. Enforced at app level (`assertBalanced()`) and DB level (CHECK constraints).

2. **No Float Math**: All monetary calculations use `bcmath` functions (`bcadd`, `bccomp`, etc.) for precision.

3. **Draft → Approved → Posted**: Journal entries start as drafts (editable), then are approved (immutable), then appear in reports.

4. **Void = Reverse**: Voiding an approved entry creates a reversing entry (debit/credit swapped) that nets to zero in the ledger.

5. **Polymorphic Source Tracking**: `journal_entries.source_type` + `source_id` links entries back to their source documents (invoice, money_receipt, ap_invoice, ap_payment).

6. **Sub-Account Pattern**: Customer AR (1100.NNN) and Vendor AP (2100.NNN) accounts are auto-created per entity, enabling per-customer and per-vendor ledger views.

7. **Period Locking**: A configurable lock date prevents backdating entries into closed periods.

8. **Row-Level Locking**: `lockForUpdate()` prevents race conditions on concurrent approvals and voucher number generation.
