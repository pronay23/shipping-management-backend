<?php

namespace App\Http\Controllers;

use App\Models\VendorCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorCategoryController extends Controller
{
    /**
     * Display a listing of the vendor categories.
     */
    public function index(): JsonResponse
    {
        $categories = VendorCategory::orderBy('id')->get();

        return response()->json([
            'data' => $categories,
        ]);
    }

    /**
     * Store a newly created vendor category in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:vendor_categories,name'],
            'description' => ['nullable', 'string'],
        ]);

        $category = VendorCategory::create($validated);

        return response()->json([
            'message' => 'Vendor category created successfully',
            'data' => $category,
        ], 201);
    }

    /**
     * Display the specified vendor category.
     */
    public function show(VendorCategory $vendorCategory): JsonResponse
    {
        return response()->json([
            'data' => $vendorCategory,
        ]);
    }

    /**
     * Update the specified vendor category in storage.
     */
    public function update(Request $request, VendorCategory $vendorCategory): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('vendor_categories', 'name')->ignore($vendorCategory->id)],
            'description' => ['nullable', 'string'],
        ]);

        $vendorCategory->update($validated);

        return response()->json([
            'message' => 'Vendor category updated successfully',
            'data' => $vendorCategory,
        ]);
    }

    /**
     * Remove the specified vendor category from storage.
     */
    public function destroy(VendorCategory $vendorCategory): JsonResponse
    {
        $vendorCategory->delete();

        return response()->json([
            'message' => 'Vendor category deleted successfully',
        ]);
    }
}
