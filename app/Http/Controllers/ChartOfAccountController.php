<?php

namespace App\Http\Controllers;

use App\Models\AccountType;
use App\Models\ChartOfAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChartOfAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ChartOfAccount::with(['parent:id,code,name', 'accountType:id,slug,name']);

        if ($request->filled('type')) {
            $typeParam = $request->query('type');
            $query->where(function ($q) use ($typeParam) {
                $q->where('type', $typeParam)
                    ->orWhereHas('accountType', fn ($sq) => $sq->where('slug', $typeParam));
            });
        }

        if ($request->filled('account_type_id')) {
            $query->where('account_type_id', $request->query('account_type_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $accounts = $query->orderBy('code')->get();

        return response()->json([
            'data' => $accounts,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:chart_of_accounts,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:50'],
            'account_type_id' => ['nullable', 'exists:account_types,id'],
            'parent_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'is_active' => ['boolean'],
        ]);

        if (empty($validated['account_type_id']) && !empty($validated['type'])) {
            $accType = AccountType::where('slug', $validated['type'])->first();
            if ($accType) {
                $validated['account_type_id'] = $accType->id;
            }
        } elseif (!empty($validated['account_type_id']) && empty($validated['type'])) {
            $accType = AccountType::find($validated['account_type_id']);
            if ($accType) {
                $validated['type'] = $accType->slug;
            }
        }

        $validated['is_system'] = false;
        $validated['is_active'] = $validated['is_active'] ?? true;
        if ($request->user()) {
            $validated['created_by'] = $request->user()->id;
        }

        $account = ChartOfAccount::create($validated);
        $account->load(['parent:id,code,name', 'accountType:id,slug,name']);

        return response()->json([
            'message' => 'Chart of Account created successfully',
            'data' => $account,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(ChartOfAccount $chartOfAccount): JsonResponse
    {
        $chartOfAccount->load(['parent:id,code,name', 'children:id,code,name,type', 'accountType:id,slug,name']);

        return response()->json([
            'data' => $chartOfAccount,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ChartOfAccount $chartOfAccount): JsonResponse
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('chart_of_accounts', 'code')->ignore($chartOfAccount->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:50'],
            'account_type_id' => ['nullable', 'exists:account_types,id'],
            'parent_id' => [
                'nullable',
                'exists:chart_of_accounts,id',
                Rule::notIn([$chartOfAccount->id]),
            ],
            'is_active' => ['boolean'],
        ]);

        if (!empty($validated['account_type_id'])) {
            $accType = AccountType::find($validated['account_type_id']);
            if ($accType) {
                $validated['type'] = $accType->slug;
            }
        } elseif (!empty($validated['type'])) {
            $accType = AccountType::where('slug', $validated['type'])->first();
            if ($accType) {
                $validated['account_type_id'] = $accType->id;
            }
        }

        $chartOfAccount->update($validated);
        $chartOfAccount->load(['parent:id,code,name', 'accountType:id,slug,name']);

        return response()->json([
            'message' => 'Chart of Account updated successfully',
            'data' => $chartOfAccount,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ChartOfAccount $chartOfAccount): JsonResponse
    {
        if ($chartOfAccount->is_system) {
            return response()->json([
                'message' => 'System accounts cannot be deleted.',
            ], 422);
        }

        if ($chartOfAccount->children()->exists()) {
            return response()->json([
                'message' => 'Cannot delete an account that has child accounts.',
            ], 422);
        }

        if ($chartOfAccount->lines()->exists()) {
            return response()->json([
                'message' => 'Cannot delete an account that has journal transactions attached.',
            ], 422);
        }

        $chartOfAccount->delete();

        return response()->json([
            'message' => 'Chart of Account deleted successfully',
        ]);
    }
}
