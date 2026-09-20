<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Voyage;

class VoyageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Voyage::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vessel_name' => 'required|string',
            'voyage_number' => 'required|string',
            'port_of_loading' => 'nullable|string',
            'port_of_discharge' => 'nullable|string',
            'arrival_date' => 'nullable|date',
            'departure_date' => 'nullable|date',
            'status' => 'nullable|string',
            'customs_office_code' => 'nullable|string',
            'carrier_code' => 'nullable|string',
            'carrier_name' => 'nullable|string',
            'carrier_address' => 'nullable|string',
            'mode_of_transport_code' => 'nullable|string',
            'nationality_of_transporter_code' => 'nullable|string',
            'bols' => 'nullable|array',
            'bols.*.bill_number' => 'required_with:bols|string',
            // Allow other BOL fields freely, as we'll pass them in
        ]);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $validated) {
            $voyage = Voyage::create(\Illuminate\Support\Arr::except($validated, ['bols']));

            if ($request->has('bols')) {
                foreach ($request->input('bols') as $bolData) {
                    $containersData = $bolData['containers'] ?? [];
                    unset($bolData['containers']);

                    $bol = $voyage->billOfLadings()->create($bolData);

                    if (!empty($containersData)) {
                        $bol->containers()->createMany($containersData);
                    }
                }
            }

            return $voyage->load('billOfLadings.containers');
        });
    }

    public function show(Voyage $voyage)
    {
        return $voyage->load('billOfLadings');
    }

    public function update(Request $request, Voyage $voyage)
    {
        $voyage->update($request->all());
        return $voyage;
    }

    public function destroy(Voyage $voyage)
    {
        $voyage->delete();
        return response()->noContent();
    }
}
