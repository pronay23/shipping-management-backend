<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CurrencyController extends Controller
{
    /**
     * Display a listing of the currencies.
     */
    public function index(): JsonResponse
    {
        $currencies = Currency::orderBy('id')->get();

        return response()->json([
            'data' => $currencies,
        ]);
    }

    /**
     * Store a newly created currency in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'currency_code' => ['required', 'string', 'max:10', 'unique:currencies,currency_code'],
            'currency_name' => ['required', 'string', 'max:100'],
            'symbol' => ['required', 'string', 'max:10'],
            'exchange_rate' => ['required', 'numeric', 'min:0'],
            'is_base_currency' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        if (!isset($validated['is_base_currency'])) {
            $validated['is_base_currency'] = false;
        }
        if (!isset($validated['is_active'])) {
            $validated['is_active'] = true;
        }

        $currency = Currency::create($validated);

        return response()->json([
            'message' => 'Currency created successfully',
            'data' => $currency,
        ], 201);
    }

    /**
     * Display the specified currency.
     */
    public function show(Currency $currency): JsonResponse
    {
        return response()->json([
            'data' => $currency,
        ]);
    }

    /**
     * Update the specified currency in storage.
     */
    public function update(Request $request, Currency $currency): JsonResponse
    {
        $validated = $request->validate([
            'currency_code' => ['required', 'string', 'max:10', Rule::unique('currencies', 'currency_code')->ignore($currency->id)],
            'currency_name' => ['required', 'string', 'max:100'],
            'symbol' => ['required', 'string', 'max:10'],
            'exchange_rate' => ['required', 'numeric', 'min:0'],
            'is_base_currency' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $currency->update($validated);

        return response()->json([
            'message' => 'Currency updated successfully',
            'data' => $currency,
        ]);
    }

    /**
     * Remove the specified currency from storage.
     */
    public function destroy(Currency $currency): JsonResponse
    {
        $currency->delete();

        return response()->json([
            'message' => 'Currency deleted successfully',
        ]);
    }
}
