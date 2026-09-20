<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = [
            ['currency_code' => 'BDT', 'currency_name' => 'Bangladeshi Taka', 'symbol' => '৳', 'exchange_rate' => 1, 'is_base_currency' => true, 'is_active' => true],
            ['currency_code' => 'USD', 'currency_name' => 'US Dollar', 'symbol' => '$', 'exchange_rate' => 110.50, 'is_base_currency' => false, 'is_active' => true],
            ['currency_code' => 'EUR', 'currency_name' => 'Euro', 'symbol' => '€', 'exchange_rate' => 120.75, 'is_base_currency' => false, 'is_active' => true],
            ['currency_code' => 'GBP', 'currency_name' => 'British Pound', 'symbol' => '£', 'exchange_rate' => 140.25, 'is_base_currency' => false, 'is_active' => true],
            ['currency_code' => 'JPY', 'currency_name' => 'Japanese Yen', 'symbol' => '¥', 'exchange_rate' => 0.74, 'is_base_currency' => false, 'is_active' => true],
            ['currency_code' => 'INR', 'currency_name' => 'Indian Rupee', 'symbol' => '₹', 'exchange_rate' => 1.33, 'is_base_currency' => false, 'is_active' => true],
            ['currency_code' => 'SGD', 'currency_name' => 'Singapore Dollar', 'symbol' => 'S$', 'exchange_rate' => 82.50, 'is_base_currency' => false, 'is_active' => true],
            ['currency_code' => 'AED', 'currency_name' => 'UAE Dirham', 'symbol' => 'د.إ', 'exchange_rate' => 30.08, 'is_base_currency' => false, 'is_active' => true],
            ['currency_code' => 'SAR', 'currency_name' => 'Saudi Riyal', 'symbol' => '﷼', 'exchange_rate' => 29.47, 'is_base_currency' => false, 'is_active' => true],
            ['currency_code' => 'CNY', 'currency_name' => 'Chinese Yuan', 'symbol' => '¥', 'exchange_rate' => 15.20, 'is_base_currency' => false, 'is_active' => true],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(
                ['currency_code' => $currency['currency_code']],
                $currency
            );
        }
    }
}
