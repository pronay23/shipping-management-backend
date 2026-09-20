# Money Receipt (MR) Feature

Money receipts are generated from selected invoices. A money receipt can cover
one or more invoices, and an invoice can be paid across multiple money receipts
over time.

## Workflow (Frontend)

1. User opens the **Invoice List** page.
2. User selects one or more invoices via checkboxes.
3. User clicks the **"Generate Money Receipt"** button.
4. The app opens the **Money Receipt** page, pre-filled with the selected
   invoices (invoice number, customer, vessel, voyage, amounts).
5. User fills in the remaining receipt details (receipt date, payment term, etc.)
   and submits.
6. The receipt is stored; linked invoices' payment status should be updated
   (paid / partial / unpaid) based on paid amounts.

## Database Schema

### Table: `money_receipts`

| Column               | Type          | Notes                         |
| -------------------- | ------------- | ----------------------------- |
| id                   | bigint (PK)   |                               |
| title                | string        | nullable                      |
| money_receipt_number | string        | unique                        |
| money_receipt_date   | date          | nullable                      |
| bl_number            | string        | nullable                      |
| customer_name        | string        | nullable                      |
| vessel               | string        | nullable                      |
| voyage               | string        | nullable                      |
| registration_no      | string        | nullable                      |
| containers           | string        | nullable                      |
| exchange_rate        | decimal(14,4) | nullable, default 110.0000    |
| amount_in_words      | text          | nullable                      |
| total_usd            | decimal(18,2) | nullable                      |
| total_bdt            | decimal(18,2) | nullable                      |
| payment_term         | string        | nullable                      |
| created_at / updated_at | timestamps |                               |

### Table: `money_receipt_items`

| Column           | Type          | Notes                       |
| ---------------- | ------------- | --------------------------- |
| id               | bigint (PK)   |                             |
| money_receipt_id | bigint (FK)   | references `money_receipts` |
| key              | string        | nullable                    |
| label            | string        | nullable                    |
| qty_20           | integer       | nullable                    |
| qty_40           | integer       | nullable                    |
| rate_usd         | decimal(14,4) | nullable                    |
| rate_bdt         | decimal(14,4) | nullable                    |
| total_usd        | decimal(18,2) | nullable                    |

### Table: `money_receipt_invoice`

| Column               | Type          | Notes                                 |
| -------------------- | ------------- | ------------------------------------- |
| id                   | bigint (PK)   |                                       |
| money_receipt_id     | bigint (FK)   | references `money_receipts`           |
| bill_of_lading_id    | bigint (FK)   | nullable, references `bill_of_ladings` |
| invoice_id           | bigint (FK)   | nullable, references `invoices`       |
| paid_amount          | decimal(18,2) | amount allocated to this invoice      |

## Models

- `App\Models\MoneyReceipt` — hasMany `MoneyReceiptItem`, hasMany/belongsToMany
  `MoneyReceiptInvoice`.
- `App\Models\MoneyReceiptItem` — belongsTo `MoneyReceipt`.
- `App\Models\MoneyReceiptInvoice` — belongsTo `MoneyReceipt`, belongsTo
  `Invoice`, belongsTo `BillOfLading`.

## Relationships

- MoneyReceipt `1 ── *` MoneyReceiptItem
- MoneyReceipt `1 ── *` MoneyReceiptInvoice
- MoneyReceiptInvoice `* ── 1` Invoice
- MoneyReceiptInvoice `* ── 1` BillOfLading (optional)

## Notes

- `money_receipt_invoice.paid_amount` drives the linked invoice payment status.
- Total received per invoice = sum of `paid_amount` across its money receipts.
