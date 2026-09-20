<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_code' => fake()->unique()->bothify('VND-####'),
            'vendor_name' => fake()->company(),
            'vendor_type' => fake()->randomElement(['Supplier', 'Service Provider', 'Agent', 'Contractor']),
            'contact_person' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'tin_number' => fake()->bothify('###########'),
            'vat_registration_number' => fake()->bothify('VAT-#####'),
            'payment_terms' => fake()->randomElement(['Net 30', 'Net 60', 'Net 90', 'COD', 'Advance']),
            'credit_limit' => fake()->randomFloat(2, 0, 50000),
            'status' => 'active',
        ];
    }
}
