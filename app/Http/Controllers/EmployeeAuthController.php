<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class EmployeeAuthController extends Controller
{
    /**
     * Register a new employee.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'string', 'max:50', 'unique:employees,employee_id'],
            'name' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:100'],
            'designation' => ['nullable', 'string', 'max:100'],
            'official_mobile' => ['nullable', 'string', 'max:20'],
            'personal_mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'unique:employees,email'],
            'image' => ['nullable', 'string', 'max:255'],
            'nid' => ['nullable', 'string', 'max:50', 'unique:employees,nid'],
            'joining_date' => ['nullable', 'date'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'birthday' => ['nullable', 'date'],
            'present_address' => ['nullable', 'string'],
            'personal_address' => ['nullable', 'string'],
            'emergency_person_mobile' => ['nullable', 'string', 'max:20'],
            'relationship_with_emergency_person' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $employee = Employee::create($validated);

        return response()->json($employee, 201);
    }

    /**
     * Log an employee in and return a Sanctum token.
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $employee = Employee::where('employee_id', $validated['employee_id'])->first();

        if (! $employee || ! Hash::check($validated['password'], $employee->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if ($employee->status === 'inactive') {
            return response()->json(['message' => 'This account is inactive.'], 403);
        }

        $token = $employee->createToken('employee-token')->plainTextToken;

        $role = $employee->roles->first();
        $permissions = $employee->getAllPermissions()->pluck('name');

        return response()->json([
            'token' => $token,
            'employee' => $employee,
            'role' => $role ? [
                'name' => $role->name,
                'display_name' => $role->display_name,
                'description' => $role->description,
            ] : null,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Revoke the current employee token.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.'], 204);
    }

    /**
     * Get the authenticated employee.
     */
    public function me(Request $request)
    {
        $employee = $request->user();
        $role = $employee->roles->first();
        $permissions = $employee->getAllPermissions()->pluck('name');

        return response()->json([
            'employee' => $employee,
            'role' => $role ? [
                'name' => $role->name,
                'display_name' => $role->display_name,
                'description' => $role->description,
            ] : null,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Return the auth guard used for employees.
     */
    protected function guard(): \Illuminate\Contracts\Auth\Guard
    {
        return Auth::guard('employees');
    }
}