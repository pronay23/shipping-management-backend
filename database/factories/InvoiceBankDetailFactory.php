<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceBankDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceBankDetail>
 */
class InvoiceBankDetailFactory extends Factory
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
            'account_name' => fake()->company(),
            'rd_account_no' => fake()->numerify('###########'),
            'bank_name' => fake()->company(),
            'branch_name' => fake()->city().' Branch',
            'swift_code' => fake()->bothify('??????###'),
            'routing_no' => fake()->numerify('#########'),
            'address' => fake()->address(),
        ];
    }
}