<?php

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\MoneyReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can store a money receipt with items and invoice links via API', function () {
    $invoice = Invoice::factory()->create(['total_bdt' => 100000.00]);

    $response = $this->postJson('/api/money-receipts', [
        'money_receipt_number' => 'MR/001',
        'money_receipt_date' => '2026-08-17',
        'customer_name' => 'Acme Trading',
        'total_bdt' => 50000.00,
        'payment_term' => 'Cash',
        'items' => [
            [
                'key' => 'doc_fee',
                'label' => 'DOC Fee',
                'qty_20' => 1,
                'total_usd' => 500.00,
            ],
        ],
        'invoices' => [
            [
                'invoice_id' => $invoice->id,
                'paid_amount' => 50000.00,
            ],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('money_receipt_number', 'MR/001')
        ->assertJsonCount(1, 'items')
        ->assertJsonCount(1, 'invoices');

    $this->assertDatabaseHas('money_receipts', ['money_receipt_number' => 'MR/001']);
    $this->assertDatabaseCount('money_receipt_items', 1);
    $this->assertDatabaseCount('money_receipt_invoice', 1);

    expect(MoneyReceipt::first()->invoiceLinks)->toHaveCount(1);
});

test('money receipt store marks an invoice as paid when fully covered', function () {
    $invoice = Invoice::factory()->create(['total_bdt' => 100000.00]);

    $this->postJson('/api/money-receipts', [
        'money_receipt_number' => 'MR/002',
        'payment_term' => 'Cash',
        'total_bdt' => 100000.00,
        'invoices' => [
            ['invoice_id' => $invoice->id, 'paid_amount' => 100000.00],
        ],
    ])->assertCreated();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

test('money receipt store marks an invoice as partial when only partly covered', function () {
    $invoice = Invoice::factory()->create(['total_bdt' => 100000.00]);

    $this->postJson('/api/money-receipts', [
        'money_receipt_number' => 'MR/003',
        'payment_term' => 'Cash',
        'total_bdt' => 50000.00,
        'invoices' => [
            ['invoice_id' => $invoice->id, 'paid_amount' => 40000.00],
        ],
    ])->assertCreated();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Partial);
});

test('money receipt store persists received and due on the invoice', function () {
    $invoice = Invoice::factory()->create(['total_bdt' => 100000.00]);

    $this->postJson('/api/money-receipts', [
        'money_receipt_number' => 'MR/004',
        'payment_term' => 'Cash',
        'total_bdt' => 50000.00,
        'invoices' => [
            ['invoice_id' => $invoice->id, 'paid_amount' => 40000.00],
        ],
    ])->assertCreated();

    $fresh = $invoice->fresh();

    expect($fresh->received)->toBe('40000.00');
    expect($fresh->due)->toBe('60000.00');
    expect($fresh->status)->toBe(InvoiceStatus::Partial);
});

test('deleting a money receipt recomputes the invoice received and due', function () {
    $invoice = Invoice::factory()->create(['total_bdt' => 100000.00]);

    $this->postJson('/api/money-receipts', [
        'money_receipt_number' => 'MR/005',
        'payment_term' => 'Cash',
        'total_bdt' => 50000.00,
        'invoices' => [
            ['invoice_id' => $invoice->id, 'paid_amount' => 40000.00],
        ],
    ])->assertCreated();

    $receipt = MoneyReceipt::where('money_receipt_number', 'MR/005')->firstOrFail();

    $this->deleteJson("/api/money-receipts/{$receipt->id}")->assertNoContent();

    $fresh = $invoice->fresh();

    expect($fresh->received)->toBe('0.00');
    expect($fresh->due)->toBe('100000.00');
    expect($fresh->status)->toBe(InvoiceStatus::Unpaid);
});