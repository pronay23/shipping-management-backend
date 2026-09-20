<?php

namespace Database\Factories;

use App\Models\MoneyReceipt;
use App\Models\MoneyReceiptItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MoneyReceiptItem>
 */
class MoneyReceiptItemFactory extends Factory
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
            'key' => fake()->randomElement(['doc_fee', 'customs', 'transport']),
            'label' => fake()->words(2, true),
            'qty_20' => fake()->numberBetween(0, 5),
            'qty_40' => fake()->numberBetween(0, 5),
            'rate_usd' => fake()->randomFloat(4, 5, 500),
            'rate_bdt' => fake()->randomFloat(4, 500, 50000),
            'total_usd' => fake()->randomFloat(2, 10, 10000),
        ];
    }
}