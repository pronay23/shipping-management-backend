<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'employee_id',
    'name',
    'department',
    'designation',
    'official_mobile',
    'personal_mobile',
    'email',
    'image',
    'nid',
    'joining_date',
    'bank_account_number',
    'birthday',
    'present_address',
    'personal_address',
    'emergency_person_mobile',
    'relationship_with_emergency_person',
    'password',
    'status',
    'role',
])]
#[Hidden(['password', 'remember_token'])]
class Employee extends Authenticatable
{
    /** @use HasFactory<EmployeeFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * Get the auth identifier used by the guard for login.
     */
    public function getAuthIdentifierName(): string
    {
        return 'employee_id';
    }

    /**
     * Get the guard name for Spatie Permission.
     */
    protected $guard_name = 'employees';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'birthday' => 'date',
            'password' => 'hashed',
        ];
    }
}