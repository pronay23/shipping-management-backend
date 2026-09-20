<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorController extends Controller
{
    /**
     * Display a listing of the vendors.
     */
    public function index(): JsonResponse
    {
        $vendors = Vendor::with(['vendorCategory:id,name', 'country:id,country_code,country_name', 'currency:id,currency_code,currency_name,symbol'])
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $vendors,
        ]);
    }

    /**
     * Store a newly created vendor in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor_code' => ['required', 'string', 'max:50', 'unique:vendors,vendor_code'],
            'vendor_name' => ['required', 'string', 'max:200'],
            'vendor_type' => ['nullable', 'string', 'max:50'],
            'vendor_category_id' => ['nullable', 'exists:vendor_categories,id'],
            'country_id' => ['nullable', 'exists:countries,id'],
            'currency_id' => ['nullable', 'exists:currencies,id'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'tin_number' => ['nullable', 'string', 'max:50'],
            'vat_registration_number' => ['nullable', 'string', 'max:50'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $validated['status'] = $validated['status'] ?? 'active';
        $validated['credit_limit'] = $validated['credit_limit'] ?? 0;

        $vendor = Vendor::create($validated);

        return response()->json([
            'message' => 'Vendor created successfully',
            'data' => $vendor,
        ], 201);
    }

    /**
     * Display the specified vendor.
     */
    public function show(Vendor $vendor): JsonResponse
    {
        $vendor->load(['vendorCategory', 'country', 'currency']);

        return response()->json([
            'data' => $vendor,
        ]);
    }

    /**
     * Update the specified vendor in storage.
     */
    public function update(Request $request, Vendor $vendor): JsonResponse
    {
        $validated = $request->validate([
            'vendor_code' => ['required', 'string', 'max:50', Rule::unique('vendors', 'vendor_code')->ignore($vendor->id)],
            'vendor_name' => ['required', 'string', 'max:200'],
            'vendor_type' => ['nullable', 'string', 'max:50'],
            'vendor_category_id' => ['nullable', 'exists:vendor_categories,id'],
            'country_id' => ['nullable', 'exists:countries,id'],
            'currency_id' => ['nullable', 'exists:currencies,id'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'tin_number' => ['nullable', 'string', 'max:50'],
            'vat_registration_number' => ['nullable', 'string', 'max:50'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $vendor->update($validated);

        return response()->json([
            'message' => 'Vendor updated successfully',
            'data' => $vendor,
        ]);
    }

    /**
     * Look up a vendor by code.
     */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate([
            'vendor_code' => 'required|string|max:50',
        ]);

        $vendor = Vendor::where('vendor_code', $request->query('vendor_code'))->first();

        if ($vendor === null) {
            return response()->json(['message' => 'Vendor not found'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $vendor->id,
                'vendor_code' => $vendor->vendor_code,
                'vendor_name' => $vendor->vendor_name,
                'email' => $vendor->email,
                'phone' => $vendor->phone,
            ],
        ]);
    }

    /**
     * Remove the specified vendor from storage.
     */
    public function destroy(Vendor $vendor): JsonResponse
    {
        $vendor->delete();

        return response()->json([
            'message' => 'Vendor deleted successfully',
        ]);
    }
}
