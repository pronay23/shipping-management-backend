<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{
    /**
     * Display a listing of the items.
     */
    public function index(): JsonResponse
    {
        $items = Item::with(['uom:id,uom_name', 'createdBy:id,name'])
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $items,
        ]);
    }

    /**
     * Store a newly created item in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_code' => ['required', 'string', 'max:50', 'unique:items,item_code'],
            'item_name' => ['required', 'string', 'max:200'],
            'item_type' => ['nullable', 'string', 'max:50'],
            'uom_id' => ['nullable', 'exists:uoms,id'],
            'item_prices' => ['nullable', 'numeric', 'min:0'],
            'item_taxes' => ['nullable', 'numeric', 'min:0'],
        ]);

        $validated['item_prices'] = $validated['item_prices'] ?? 0;
        $validated['item_taxes'] = $validated['item_taxes'] ?? 0;
        $validated['created_by'] = auth('sanctum')->id();

        $item = Item::create($validated);

        return response()->json([
            'message' => 'Item created successfully',
            'data' => $item,
        ], 201);
    }

    /**
     * Display the specified item.
     */
    public function show(Item $item): JsonResponse
    {
        $item->load(['uom', 'createdBy:id,name', 'updatedBy:id,name']);

        return response()->json([
            'data' => $item,
        ]);
    }

    /**
     * Update the specified item in storage.
     */
    public function update(Request $request, Item $item): JsonResponse
    {
        $validated = $request->validate([
            'item_code' => ['required', 'string', 'max:50', Rule::unique('items', 'item_code')->ignore($item->id)],
            'item_name' => ['required', 'string', 'max:200'],
            'item_type' => ['nullable', 'string', 'max:50'],
            'uom_id' => ['nullable', 'exists:uoms,id'],
            'item_prices' => ['nullable', 'numeric', 'min:0'],
            'item_taxes' => ['nullable', 'numeric', 'min:0'],
        ]);

        $validated['updated_by'] = auth('sanctum')->id();

        $item->update($validated);

        return response()->json([
            'message' => 'Item updated successfully',
            'data' => $item,
        ]);
    }

    /**
     * Look up an item by code.
     */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate([
            'item_code' => 'required|string|max:50',
        ]);

        $item = Item::where('item_code', $request->query('item_code'))->first();

        if ($item === null) {
            return response()->json(['message' => 'Item not found'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $item->id,
                'item_code' => $item->item_code,
                'item_name' => $item->item_name,
                'item_type' => $item->item_type,
                'item_prices' => $item->item_prices,
                'item_taxes' => $item->item_taxes,
            ],
        ]);
    }

    /**
     * Remove the specified item from storage.
     */
    public function destroy(Item $item): JsonResponse
    {
        $item->delete();

        return response()->json([
            'message' => 'Item deleted successfully',
        ]);
    }
}
