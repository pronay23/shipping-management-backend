<?php

namespace Database\Seeders;

use App\Models\Vendor;
use App\Models\VendorCategory;
use App\Models\Country;
use App\Models\Currency;
use Illuminate\Database\Seeder;

class VendorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cfAgent = VendorCategory::where('name', 'C&F Agent')->first();
        $overseaAgent = VendorCategory::where('name', 'Oversea Agent')->first();
        $supplier = VendorCategory::where('name', 'Office Supplier')->first();
        $shippingLine = VendorCategory::where('name', 'Shipping Line')->first();

        $bd = Country::where('country_code', 'BD')->first();
        $sg = Country::where('country_code', 'SG')->first();
        $us = Country::where('country_code', 'US')->first();

        $bdt = Currency::where('currency_code', 'BDT')->first();
        $usd = Currency::where('currency_code', 'USD')->first();
        $sgd = Currency::where('currency_code', 'SGD')->first();

        $vendors = [
            [
                'vendor_code' => 'VND-0001',
                'vendor_name' => 'ABC Clearing & Forwarding Co.',
                'vendor_type' => 'Agent',
                'vendor_category_id' => $cfAgent?->id,
                'country_id' => $bd?->id,
                'currency_id' => $bdt?->id,
                'contact_person' => 'Mohammad Rahman',
                'email' => 'info@abccf.com',
                'phone' => '+880-1711-123456',
                'address' => 'Chattogram, Bangladesh',
                'tin_number' => '1234567890123',
                'vat_registration_number' => 'VAT-10001',
                'payment_terms' => 'Net 30',
                'credit_limit' => 500000,
                'status' => 'active',
            ],
            [
                'vendor_code' => 'VND-0002',
                'vendor_name' => 'Global Shipping Lines Pte Ltd',
                'vendor_type' => 'Service Provider',
                'vendor_category_id' => $shippingLine?->id,
                'country_id' => $sg?->id,
                'currency_id' => $usd?->id,
                'contact_person' => 'James Tan',
                'email' => 'contact@globallines.sg',
                'phone' => '+65-9123-4567',
                'address' => 'Singapore',
                'tin_number' => 'SG987654321',
                'vat_registration_number' => 'GST-20001',
                'payment_terms' => 'Net 60',
                'credit_limit' => 100000,
                'status' => 'active',
            ],
            [
                'vendor_code' => 'VND-0003',
                'vendor_name' => 'Pacific Overseas Agencies',
                'vendor_type' => 'Agent',
                'vendor_category_id' => $overseaAgent?->id,
                'country_id' => $sg?->id,
                'currency_id' => $sgd?->id,
                'contact_person' => 'David Lee',
                'email' => 'info@pacificoverseas.com',
                'phone' => '+65-8765-4321',
                'address' => '25 Boat Quay, Singapore',
                'tin_number' => 'SG112233445',
                'vat_registration_number' => 'GST-30001',
                'payment_terms' => 'Net 30',
                'credit_limit' => 75000,
                'status' => 'active',
            ],
            [
                'vendor_code' => 'VND-0004',
                'vendor_name' => 'Office World Bangladesh',
                'vendor_type' => 'Supplier',
                'vendor_category_id' => $supplier?->id,
                'country_id' => $bd?->id,
                'currency_id' => $bdt?->id,
                'contact_person' => 'Fatima Khan',
                'email' => 'sales@officeworldbd.com',
                'phone' => '+880-1812-654321',
                'address' => 'Gulshan, Dhaka, Bangladesh',
                'tin_number' => '9876543210123',
                'vat_registration_number' => 'VAT-40001',
                'payment_terms' => 'COD',
                'credit_limit' => 0,
                'status' => 'active',
            ],
        ];

        foreach ($vendors as $vendor) {
            Vendor::updateOrCreate(
                ['vendor_code' => $vendor['vendor_code']],
                $vendor
            );
        }
    }
}
