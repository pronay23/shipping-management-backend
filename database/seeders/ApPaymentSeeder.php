<?php

namespace Database\Seeders;

use App\Models\ApInvoice;
use App\Models\ApPayment;
use App\Models\ApPaymentInvoice;
use App\Models\ChartOfAccount;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class ApPaymentSeeder extends Seeder
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

        $apInvoice = ApInvoice::where('ap_invoice_number', 'API-2026-0001')->first();

        if ($apInvoice === null) {
            return;
        }

        $bankAccount = ChartOfAccount::where('code', '1020')->first();

        $apPayment = ApPayment::updateOrCreate(
            ['ap_payment_number' => 'APP-2026-0001'],
            [
                'vendor_id' => $vendor->id,
                'payment_date' => '2026-09-05',
                'payment_method' => 'Bank Transfer',
                'bank_account_id' => $bankAccount?->id,
                'reference_number' => 'TT-2026-001',
                'amount' => 65000,
            ]
        );

        if ($apPayment->invoiceLinks()->count() === 0) {
            ApPaymentInvoice::create([
                'ap_payment_id' => $apPayment->id,
                'ap_invoice_id' => $apInvoice->id,
                'paid_amount' => 65000,
            ]);

            $apInvoice->update([
                'paid_amount' => 65000,
                'due_amount' => 0,
                'status' => 'paid',
            ]);
        }
    }
}
