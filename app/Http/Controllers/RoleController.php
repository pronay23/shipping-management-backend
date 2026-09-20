<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')->get();

        return response()->json($roles);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'] ?? null,
            'description' => $validated['description'] ?? null,
            'guard_name' => 'api',
        ]);

        $role->syncPermissions($validated['permissions']);

        return response()->json([
            'message' => 'Role created successfully',
            'role' => $role->load('permissions'),
        ], 201);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json($role->load('permissions'));
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $systemRoles = ['super-admin', 'admin', 'manager'];

        if (in_array($role->name, $systemRoles)) {
            $role->update([
                'display_name' => $request->input('display_name', $role->display_name),
                'description' => $request->input('description', $role->description),
            ]);
        } else {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255|unique:roles,name,' . $role->id,
                'display_name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
            ]);

            $role->update($validated);
        }

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => $role->load('permissions'),
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        $systemRoles = ['super-admin', 'admin', 'manager'];

        if (in_array($role->name, $systemRoles)) {
            return response()->json([
                'message' => 'System roles cannot be deleted',
            ], 403);
        }

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully',
        ]);
    }
}
