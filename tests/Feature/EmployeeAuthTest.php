<?php

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an employee can be created via API', function () {
    $response = $this->postJson('/api/employee/register', [
        'employee_id' => 'EMP-001',
        'name' => 'Rakib Hasan',
        'department' => 'Operations',
        'designation' => 'Operations Manager',
        'official_mobile' => '01711111111',
        'personal_mobile' => '01811111111',
        'email' => 'rakib@example.com',
        'nid' => '1234567890',
        'joining_date' => '2026-01-15',
        'bank_account_number' => '123456789',
        'birthday' => '1995-05-20',
        'present_address' => 'Dhaka',
        'personal_address' => 'Dhaka',
        'emergency_person_mobile' => '01911111111',
        'relationship_with_emergency_person' => 'Brother',
        'password' => 'secret123',
        'status' => 'active',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('employee_id', 'EMP-001')
        ->assertJsonPath('name', 'Rakib Hasan')
        ->assertJsonMissing([
            'password' => 'secret123',
        ]);

    $this->assertDatabaseHas('employees', [
        'employee_id' => 'EMP-001',
        'email' => 'rakib@example.com',
    ]);
});

test('employee registration fails on duplicate employee id', function () {
    Employee::factory()->create(['employee_id' => 'EMP-001']);

    $response = $this->postJson('/api/employee/register', [
        'employee_id' => 'EMP-001',
        'name' => 'Another Employee',
        'password' => 'secret123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('employee_id');
});

test('an employee can log in with employee id and password', function () {
    Employee::factory()->create([
        'employee_id' => 'EMP-002',
        'password' => 'secret123',
        'status' => 'active',
    ]);

    $response = $this->postJson('/api/employee/login', [
        'employee_id' => 'EMP-002',
        'password' => 'secret123',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('employee.employee_id', 'EMP-002')
        ->assertJsonStructure(['token']);
});

test('login fails with invalid credentials', function () {
    Employee::factory()->create([
        'employee_id' => 'EMP-003',
        'password' => 'secret123',
    ]);

    $response = $this->postJson('/api/employee/login', [
        'employee_id' => 'EMP-003',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(401);
});

test('inactive employee cannot log in', function () {
    Employee::factory()->inactive()->create([
        'employee_id' => 'EMP-004',
        'password' => 'secret123',
    ]);

    $response = $this->postJson('/api/employee/login', [
        'employee_id' => 'EMP-004',
        'password' => 'secret123',
    ]);

    $response->assertStatus(403);
});

test('authenticated employee can access own profile', function () {
    $employee = Employee::factory()->create([
        'employee_id' => 'EMP-005',
        'password' => 'secret123',
    ]);

    $token = $employee->createToken('test-token')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/employee/me');

    $response->assertStatus(200)
        ->assertJsonPath('employee_id', 'EMP-005');
});

test('employee can log out and token is revoked', function () {
    $employee = Employee::factory()->create([
        'employee_id' => 'EMP-006',
        'password' => 'secret123',
    ]);

    $token = $employee->createToken('test-token')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/employee/logout');

    $response->assertStatus(204);

    $this->assertDatabaseCount('personal_access_tokens', 0);
});