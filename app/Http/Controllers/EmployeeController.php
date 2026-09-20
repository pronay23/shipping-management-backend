<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Employee::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
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
     * Display the specified resource.
     */
    public function show(Employee $employee)
    {
        return response()->json($employee);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'employee_id' => ['sometimes', 'required', 'string', 'max:50', 'unique:employees,employee_id,' . $employee->id],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:100'],
            'designation' => ['nullable', 'string', 'max:100'],
            'official_mobile' => ['nullable', 'string', 'max:20'],
            'personal_mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'unique:employees,email,' . $employee->id],
            'image' => ['nullable', 'string', 'max:255'],
            'nid' => ['nullable', 'string', 'max:50', 'unique:employees,nid,' . $employee->id],
            'joining_date' => ['nullable', 'date'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'birthday' => ['nullable', 'date'],
            'present_address' => ['nullable', 'string'],
            'personal_address' => ['nullable', 'string'],
            'emergency_person_mobile' => ['nullable', 'string', 'max:20'],
            'relationship_with_emergency_person' => ['nullable', 'string', 'max:100'],
            'password' => ['sometimes', 'required', 'string', 'min:8'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $employee->update($validated);

        return response()->json($employee);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee)
    {
        $employee->delete();

        return response()->json(null, 204);
    }

    /**
     * Assign a role to an employee.
     */
    public function assignRole(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        $employee = Employee::findOrFail($id);
        $employee->syncRoles([$validated['role']]);
        $employee->update(['role' => $validated['role']]);

        return response()->json([
            'message' => 'Role assigned successfully',
            'employee' => $employee->load('roles'),
        ]);
    }
}