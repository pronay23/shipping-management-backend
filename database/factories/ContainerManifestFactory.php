<?php

namespace Database\Factories;

use App\Models\BillOfLading;
use App\Models\ContainerManifest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContainerManifest>
 */
class ContainerManifestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bill_of_lading_id' => BillOfLading::factory(),
            'bill_number' => function (array $attributes) {
                return BillOfLading::find($attributes['bill_of_lading_id'])->bill_number ?? fake()->unique()->word();
            },
            'container_no' => fake()->bothify('????######'),
            'seal_no' => fake()->bothify('???######'),
            'bags' => fake()->numberBetween(10, 1000),
            'gross_weight_kgs' => fake()->randomFloat(3, 100, 25000),
            'measurement_m3' => fake()->randomFloat(3, 1, 100),
        ];
    }
}
