<?php

namespace Database\Factories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_code' => fake()->unique()->bothify('ITM-####'),
            'item_name' => fake()->words(3, true),
            'item_type' => fake()->randomElement(['Service', 'Product', 'Consumable', 'Fixed Asset']),
            'item_prices' => fake()->randomFloat(2, 10, 50000),
            'item_taxes' => fake()->randomFloat(2, 0, 5000),
        ];
    }
}
