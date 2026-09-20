<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'key' => fake()->optional()->randomElement(['doc_fee', 'admin_fee', 'cleaning', 'porterage']),
            'label' => fake()->optional()->text(30),
            'qty_20' => fake()->numberBetween(0, 5),
            'qty_40' => fake()->numberBetween(0, 5),
            'rate_usd' => fake()->optional()->randomFloat(4, 1, 1000),
            'rate_bdt' => fake()->optional()->randomFloat(4, 100, 20000),
            'total_usd' => fake()->optional()->randomFloat(2, 1, 10000),
        ];
    }
}