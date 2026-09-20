<?php

namespace Database\Factories;

use App\Models\MoneyReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MoneyReceipt>
 */
class MoneyReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Money Receipt',
            'money_receipt_number' => fake()->unique()->numerify('MR/####'),
            'money_receipt_date' => fake()->date(),
            'bl_number' => fake()->bothify('???#######'),
            'customer_name' => fake()->company(),
            'vessel' => fake()->company(),
            'voyage' => fake()->bothify('V-##'),
            'registration_no' => fake()->numerify('######'),
            'containers' => fake()->bothify('TCNU#######'),
            'exchange_rate' => fake()->randomFloat(4, 100, 120),
            'amount_in_words' => fake()->sentence(),
            'total_usd' => fake()->randomFloat(2, 100, 10000),
            'total_bdt' => fake()->randomFloat(2, 10000, 1000000),
            'payment_term' => fake()->randomElement(['Cash', 'Bank Draft', 'LC', 'TT']),
        ];
    }
}