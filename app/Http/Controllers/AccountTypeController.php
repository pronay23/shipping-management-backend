<?php

namespace App\Http\Controllers;

use App\Models\AccountType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccountTypeController extends Controller
{
    /**
     * Display a listing of the account types.
     */
    public function index(): JsonResponse
    {
        $types = AccountType::withCount('accounts')->orderBy('id')->get();

        return response()->json([
            'data' => $types,
        ]);
    }

    /**
     * Store a newly created account type in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        // Check unique slug
        if (AccountType::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $validated['slug'] . '-' . time();
        }

        $validated['is_system'] = false;

        $accountType = AccountType::create($validated);

        return response()->json([
            'message' => 'Account type created successfully',
            'data' => $accountType,
        ], 201);
    }

    /**
     * Display the specified account type.
     */
    public function show(AccountType $accountType): JsonResponse
    {
        $accountType->load('accounts:id,code,name,account_type_id,is_active');

        return response()->json([
            'data' => $accountType,
        ]);
    }

    /**
     * Update the specified account type in storage.
     */
    public function update(Request $request, AccountType $accountType): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        if (!$accountType->is_system) {
            $slug = Str::slug($validated['name']);
            if (AccountType::where('slug', $slug)->where('id', '!=', $accountType->id)->exists()) {
                $slug = $slug . '-' . time();
            }
            $validated['slug'] = $slug;
        }

        $accountType->update($validated);

        return response()->json([
            'message' => 'Account type updated successfully',
            'data' => $accountType,
        ]);
    }

    /**
     * Remove the specified account type from storage.
     */
    public function destroy(AccountType $accountType): JsonResponse
    {
        if ($accountType->is_system) {
            return response()->json([
                'message' => 'System account types cannot be deleted.',
            ], 422);
        }

        if ($accountType->accounts()->exists()) {
            return response()->json([
                'message' => 'Cannot delete an account type that has chart of accounts assigned to it.',
            ], 422);
        }

        $accountType->delete();

        return response()->json([
            'message' => 'Account type deleted successfully',
        ]);
    }
}
