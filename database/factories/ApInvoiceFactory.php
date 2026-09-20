<?php

namespace Database\Factories;

use App\Models\ApInvoice;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApInvoice>
 */
class ApInvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory(),
            'ap_invoice_number' => fake()->unique()->bothify('API-####-####'),
            'invoice_date' => fake()->date(),
            'due_date' => fake()->optional()->date(),
            'reference' => fake()->optional()->bothify('REF-???-###'),
            'description' => fake()->optional()->sentence(),
            'total_bdt' => fake()->randomFloat(2, 5000, 500000),
            'discount_amount' => 0,
            'tax_amount' => 0,
            'net_amount' => fake()->randomFloat(2, 5000, 500000),
            'paid_amount' => 0,
            'due_amount' => fake()->randomFloat(2, 5000, 500000),
            'status' => 'unpaid',
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
