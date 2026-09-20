<?php

use App\Models\Invoice;
use App\Models\InvoiceBankDetail;
use App\Models\InvoiceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an invoice has one bank detail and many items', function () {
    $invoice = Invoice::factory()->create([
        'exchange_rate' => 110.0000,
    ]);

    InvoiceBankDetail::factory()->create(['invoice_id' => $invoice->id]);
    InvoiceItem::factory()->count(2)->create(['invoice_id' => $invoice->id]);

    expect($invoice->bankDetails)->toBeInstanceOf(InvoiceBankDetail::class);
    expect($invoice->items)->toHaveCount(2);
});

test('an invoice item derives its BDT total without storing it', function () {
    $invoice = Invoice::factory()->create(['exchange_rate' => 110.0000]);

    $item = InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'qty_20' => 1,
        'qty_40' => 0,
        'rate_usd' => 10.0000,
        'rate_bdt' => null,
        'total_usd' => null,
    ]);

    expect($item->total_bdt)->toBe(1100.0);
});

test('can store an invoice with nested bank details and items via API', function () {
    $payload = [
        'title' => 'Invoice for Import Shipment',
        'invoice_number' => 'MR/IMP/BCLL/26',
        'invoice_date' => '2026-08-11',
        'exchange_rate' => 110.0000,
        'bank_details' => [
            'account_name' => 'Bangladesh Container Lines Limited',
            'rd_account_no' => '0021020013801',
            'bank_name' => 'One Bank PLC',
            'branch_name' => 'Gulshan-1 Branch',
            'swift_code' => 'ONEBDDH003',
            'routing_no' => '165261726',
            'address' => 'Richmond Concord, Dhaka',
        ],
        'items' => [
            [
                'key' => 'doc_fee',
                'label' => 'DOC Fee',
                'qty_20' => 1,
                'qty_40' => 0,
                'rate_usd' => 10.0000,
                'total_usd' => 10.00,
            ],
        ],
    ];

    $response = $this->postJson('/api/invoices', $payload);

    $response->assertCreated()
        ->assertJsonPath('invoice_number', 'MR/IMP/BCLL/26')
        ->assertJsonPath('bank_details.account_name', 'Bangladesh Container Lines Limited')
        ->assertJsonCount(1, 'items');

    $this->assertDatabaseHas('invoices', ['invoice_number' => 'MR/IMP/BCLL/26']);
    $this->assertDatabaseCount('invoice_bank_details', 1);
    $this->assertDatabaseCount('invoice_items', 1);
});

test('can retrieve an invoice with nested relations via API', function () {
    $invoice = Invoice::factory()->create(['invoice_number' => 'MR/IMP/BCLL/99']);
    InvoiceBankDetail::factory()->create(['invoice_id' => $invoice->id, 'bank_name' => 'One Bank PLC']);
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'key' => 'doc_fee']);

    $response = $this->getJson("/api/invoices/{$invoice->id}");

    $response->assertOk()
        ->assertJsonPath('id', $invoice->id)
        ->assertJsonPath('bank_details.bank_name', 'One Bank PLC')
        ->assertJsonCount(1, 'items');
});

test('can store an invoice with received and due amounts via API', function () {
    $payload = [
        'invoice_number' => 'MR/IMP/BCLL/27',
        'total_bdt' => 135740.00,
        'received' => 50000.00,
        'due' => 85740.00,
    ];

    $response = $this->postJson('/api/invoices', $payload);

    $response->assertCreated()
        ->assertJsonPath('invoice_number', 'MR/IMP/BCLL/27')
        ->assertJsonPath('received', '50000.00')
        ->assertJsonPath('due', '85740.00');

    $this->assertDatabaseHas('invoices', [
        'invoice_number' => 'MR/IMP/BCLL/27',
        'received' => 50000.00,
        'due' => 85740.00,
    ]);
});

test('requires a unique invoice number when storing', function () {
    Invoice::factory()->create(['invoice_number' => 'MR/IMP/BCLL/26']);

    $this->postJson('/api/invoices', [
        'invoice_number' => 'MR/IMP/BCLL/26',
    ])->assertUnprocessable();
});