<?php

namespace Database\Factories;

use App\Models\Uom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Uom>
 */
class UomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uom_name' => fake()->unique()->randomElement(['Piece', 'Kilogram', 'Meter', 'Liter', 'Box', 'Carton', 'Set', 'Pair', 'Dozen', 'Ton']),
            'uom_description' => fake()->sentence(),
        ];
    }
}
