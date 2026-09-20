<?php

namespace App\Http\Controllers;

use App\Models\Uom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UomController extends Controller
{
    /**
     * Display a listing of the UOMs.
     */
    public function index(): JsonResponse
    {
        $uoms = Uom::withCount('items')->orderBy('id')->get();

        return response()->json([
            'data' => $uoms,
        ]);
    }

    /**
     * Store a newly created UOM in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uom_name' => ['required', 'string', 'max:100', 'unique:uoms,uom_name'],
            'uom_description' => ['nullable', 'string'],
        ]);

        $uom = Uom::create($validated);

        return response()->json([
            'message' => 'UOM created successfully',
            'data' => $uom,
        ], 201);
    }

    /**
     * Display the specified UOM.
     */
    public function show(Uom $uom): JsonResponse
    {
        return response()->json([
            'data' => $uom,
        ]);
    }

    /**
     * Update the specified UOM in storage.
     */
    public function update(Request $request, Uom $uom): JsonResponse
    {
        $validated = $request->validate([
            'uom_name' => ['required', 'string', 'max:100', Rule::unique('uoms', 'uom_name')->ignore($uom->id)],
            'uom_description' => ['nullable', 'string'],
        ]);

        $uom->update($validated);

        return response()->json([
            'message' => 'UOM updated successfully',
            'data' => $uom,
        ]);
    }

    /**
     * Remove the specified UOM from storage.
     */
    public function destroy(Uom $uom): JsonResponse
    {
        if ($uom->items()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a UOM that has items assigned to it.',
            ], 422);
        }

        $uom->delete();

        return response()->json([
            'message' => 'UOM deleted successfully',
        ]);
    }
}
