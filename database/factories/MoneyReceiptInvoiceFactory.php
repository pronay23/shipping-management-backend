<?php

namespace Database\Factories;

use App\Models\BillOfLading;
use App\Models\Invoice;
use App\Models\MoneyReceipt;
use App\Models\MoneyReceiptInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MoneyReceiptInvoice>
 */
class MoneyReceiptInvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'money_receipt_id' => MoneyReceipt::factory(),
            'bill_of_lading_id' => BillOfLading::factory(),
            'invoice_id' => Invoice::factory(),
            'paid_amount' => fake()->randomFloat(2, 100, 100000),
        ];
    }
}