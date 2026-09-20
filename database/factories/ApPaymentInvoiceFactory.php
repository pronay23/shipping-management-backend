<?php

namespace Database\Factories;

use App\Models\ApInvoice;
use App\Models\ApPayment;
use App\Models\ApPaymentInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApPaymentInvoice>
 */
class ApPaymentInvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ap_payment_id' => ApPayment::factory(),
            'ap_invoice_id' => ApInvoice::factory(),
            'paid_amount' => fake()->randomFloat(2, 1000, 100000),
        ];
    }
}
