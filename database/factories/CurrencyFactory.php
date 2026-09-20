<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'currency_code' => fake()->unique()->currencyCode(),
            'currency_name' => fake()->currency(),
            'symbol' => fake()->currencySymbol(),
            'exchange_rate' => fake()->randomFloat(6, 0.5, 150),
            'is_base_currency' => false,
            'is_active' => true,
        ];
    }
}
