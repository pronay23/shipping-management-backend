<?php

namespace Database\Factories;

use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'voucher_number' => fake()->unique()->numerify('JV-####-####'),
            'entry_date' => fake()->date(),
            'memo' => fake()->sentence(),
            'source_type' => null,
            'source_id' => null,
            'total_debit' => fake()->randomFloat(2, 1000, 100000),
            'total_credit' => fn (array $attributes) => $attributes['total_debit'],
            'status' => JournalEntry::STATUS_DRAFT,
        ];
    }
}