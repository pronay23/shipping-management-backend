<?php

namespace Database\Factories;

use App\Models\ApInvoice;
use App\Models\ApInvoiceItem;
use App\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApInvoiceItem>
 */
class ApInvoiceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ap_invoice_id' => ApInvoice::factory(),
            'account_id' => ChartOfAccount::factory(),
            'description' => fake()->words(3, true),
            'quantity' => fake()->randomFloat(2, 1, 10),
            'unit_price' => fake()->randomFloat(2, 1000, 50000),
            'total_bdt' => fake()->randomFloat(2, 1000, 100000),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
