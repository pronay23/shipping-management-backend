<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntryLine>
 */
class JournalEntryLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'journal_entry_id' => JournalEntry::factory(),
            'account_id' => ChartOfAccount::factory(),
            'reference' => fake()->bothify('REF-####'),
            'debit' => fake()->randomFloat(2, 1000, 100000),
            'credit' => 0,
            'note' => fake()->sentence(),
        ];
    }
}