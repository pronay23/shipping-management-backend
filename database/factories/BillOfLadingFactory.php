<?php

namespace Database\Factories;

use App\Models\BillOfLading;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillOfLading>
 */
class BillOfLadingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bill_number' => fake()->unique()->bothify('BL-######'),
        ];
    }
}
