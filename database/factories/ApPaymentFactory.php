<?php

namespace Database\Factories;

use App\Models\ApPayment;
use App\Models\ChartOfAccount;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApPayment>
 */
class ApPaymentFactory extends Factory
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
            'ap_payment_number' => fake()->unique()->bothify('APP-####-####'),
            'payment_date' => fake()->date(),
            'payment_method' => fake()->randomElement(['Cash', 'Cheque', 'Bank Transfer', 'TT / Wire Transfer']),
            'bank_account_id' => ChartOfAccount::where('code', '1020')->first()?->id,
            'reference_number' => fake()->optional()->bothify('REF-###-####'),
            'amount' => fake()->randomFloat(2, 5000, 500000),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
