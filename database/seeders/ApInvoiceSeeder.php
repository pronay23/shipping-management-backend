<?php

namespace Database\Seeders;

use App\Models\ApInvoice;
use App\Models\ApInvoiceItem;
use App\Models\ChartOfAccount;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class ApInvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vendor = Vendor::where('vendor_name', 'Global Shipping Lines Pte Ltd')->first()
            ?? Vendor::first();

        if ($vendor === null) {
            return;
        }

        $oceanFreight = ChartOfAccount::where('code', '5001')->first();
        $terminalHandling = ChartOfAccount::where('code', '5002')->first();

        $apInvoice = ApInvoice::updateOrCreate(
            ['ap_invoice_number' => 'API-2026-0001'],
            [
                'vendor_id' => $vendor->id,
                'invoice_date' => '2026-08-25',
                'due_date' => '2026-09-24',
                'reference' => 'REF-GL-001',
                'description' => 'Ocean freight and terminal handling charges',
                'total_bdt' => 65000,
                'net_amount' => 65000,
                'due_amount' => 65000,
                'status' => 'unpaid',
            ]
        );

        if ($apInvoice->items()->count() === 0) {
            ApInvoiceItem::insert([
                [
                    'ap_invoice_id' => $apInvoice->id,
                    'account_id' => $oceanFreight?->id,
                    'description' => 'Ocean Freight',
                    'quantity' => 1,
                    'unit_price' => 50000,
                    'total_bdt' => 50000,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'ap_invoice_id' => $apInvoice->id,
                    'account_id' => $terminalHandling?->id,
                    'description' => 'Terminal Handling',
                    'quantity' => 1,
                    'unit_price' => 15000,
                    'total_bdt' => 15000,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        $vendor2 = Vendor::where('vendor_name', 'Pacific Overseas Agencies')->first();

        if ($vendor2 !== null) {
            $portCharges = ChartOfAccount::where('code', '5003')->first();

            $apInvoice2 = ApInvoice::updateOrCreate(
                ['ap_invoice_number' => 'API-2026-0002'],
                [
                    'vendor_id' => $vendor2->id,
                    'invoice_date' => '2026-08-28',
                    'due_date' => '2026-09-27',
                    'reference' => 'REF-PO-001',
                    'description' => 'Port charges for shipment',
                    'total_bdt' => 35000,
                    'net_amount' => 35000,
                    'due_amount' => 35000,
                    'status' => 'unpaid',
                ]
            );

            if ($apInvoice2->items()->count() === 0) {
                ApInvoiceItem::insert([
                    [
                        'ap_invoice_id' => $apInvoice2->id,
                        'account_id' => $portCharges?->id,
                        'description' => 'Port Charges',
                        'quantity' => 1,
                        'unit_price' => 35000,
                        'total_bdt' => 35000,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ]);
            }
        }
    }
}
