<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => fake()->unique()->numerify('EMP-####'),
            'name' => fake()->name(),
            'department' => fake()->randomElement(['Operations', 'Accounts', 'HR', 'IT']),
            'designation' => fake()->jobTitle(),
            'official_mobile' => fake()->numerify('01#########'),
            'personal_mobile' => fake()->numerify('01#########'),
            'email' => fake()->unique()->safeEmail(),
            'nid' => fake()->unique()->numerify('##########'),
            'joining_date' => fake()->date(),
            'bank_account_number' => fake()->numerify('##########'),
            'birthday' => fake()->date(),
            'present_address' => fake()->address(),
            'personal_address' => fake()->address(),
            'emergency_person_mobile' => fake()->numerify('01#########'),
            'relationship_with_emergency_person' => fake()->randomElement(['Father', 'Mother', 'Spouse', 'Friend']),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'status' => 'active',
        ];
    }

    /**
     * Indicate that the employee account is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}