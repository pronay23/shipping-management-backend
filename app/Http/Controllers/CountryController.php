<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CountryController extends Controller
{
    /**
     * Display a listing of the countries.
     */
    public function index(): JsonResponse
    {
        $countries = Country::orderBy('id')->get();

        return response()->json([
            'data' => $countries,
        ]);
    }

    /**
     * Store a newly created country in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country_code' => ['required', 'string', 'max:10', 'unique:countries,country_code'],
            'country_name' => ['required', 'string', 'max:150'],
        ]);

        $country = Country::create($validated);

        return response()->json([
            'message' => 'Country created successfully',
            'data' => $country,
        ], 201);
    }

    /**
     * Display the specified country.
     */
    public function show(Country $country): JsonResponse
    {
        return response()->json([
            'data' => $country,
        ]);
    }

    /**
     * Update the specified country in storage.
     */
    public function update(Request $request, Country $country): JsonResponse
    {
        $validated = $request->validate([
            'country_code' => ['required', 'string', 'max:10', Rule::unique('countries', 'country_code')->ignore($country->id)],
            'country_name' => ['required', 'string', 'max:150'],
        ]);

        $country->update($validated);

        return response()->json([
            'message' => 'Country updated successfully',
            'data' => $country,
        ]);
    }

    /**
     * Remove the specified country from storage.
     */
    public function destroy(Country $country): JsonResponse
    {
        $country->delete();

        return response()->json([
            'message' => 'Country deleted successfully',
        ]);
    }
}
