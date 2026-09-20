<?php

namespace App\Http\Controllers;

use App\Models\BillOfLading;
use Illuminate\Http\Request;

class BillOfLadingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(BillOfLading::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // $validated = $request->validate([
        //     'bill_number' => 'required|string|unique:bill_of_ladings,bill_number',
        //     'booking_number' => 'nullable|string',
        //     'vessel_name' => 'nullable|string',
        //     'vessel_number' => 'nullable|string',
        //     'place_of_receipt' => 'nullable|string',
        //     'port_of_loading' => 'nullable|string',
        //     'port_of_discharge' => 'nullable|string',
        //     'place_of_delivery' => 'nullable|string',
        //     'final_destination' => 'nullable|string',
        //     'shipped_on_board_date' => 'nullable|date',
        //     'free_time_days' => 'nullable|string',
        //     'shipper_name' => 'nullable|string',
        //     'consignee_name' => 'nullable|string',
        //     'notify_party' => 'nullable|string',
        //     'carrier' => 'nullable|string',
        //     'delivery_contact' => 'nullable|string',
        //     'product_name' => 'nullable|string',
        //     'manufacturer_name' => 'nullable|string',
        //     'country_of_origin' => 'nullable|string',
        //     'mfg_date' => 'nullable|date',
        //     'exp_date' => 'nullable|date',
        //     'hs_code_import' => 'nullable|string',
        //     'hs_code_export' => 'nullable|string',
        //     'net_weight_per_bag' => 'nullable|numeric',
        //     'total_net_weight_mt' => 'nullable|numeric',
        //     'total_gross_weight_mt' => 'nullable|numeric',
        //     'delivery_term_notes' => 'nullable|string',
        //     'proforma_invoice_no' => 'nullable|string',
        //     'proforma_invoice_date' => 'nullable|date',
        //     'doc_credit_no' => 'nullable|string',
        //     'doc_credit_date' => 'nullable|date',
        //     'irc_old_no' => 'nullable|string',
        //     'irc_new_no' => 'nullable|string',
        //     'importer_tin' => 'nullable|string',
        //     'importer_vat' => 'nullable|string',
        //     'freight_terms' => 'nullable|string',
        //     'freight_prepaid_at' => 'nullable|string',
        //     'freight_payable_at' => 'nullable|string',
        //     'total_local_currency' => 'nullable|numeric',
        //     'date_of_issue' => 'nullable|date',
        //     'place_of_issue' => 'nullable|string',
        //     'originals_issued' => 'nullable|string',
        //     'signed_by' => 'nullable|string',
        //     'status' => 'nullable|string',
        // ]);

        // $billOfLading = BillOfLading::create($request->all());

        // return response()->json($billOfLading, 201);

        $data = $request->except('containers');
        $billOfLading = BillOfLading::create($data);

        $containers = collect($request->input('containers', []))
            ->map(function ($item) use ($billOfLading) {
                return [
                    'bill_number' => $billOfLading->bill_number,
                    'container_no' => $item['container_no'],
                    'seal_no' => $item['seal_no'],
                    'bags' => $item['bags'],
                    'gross_weight_kgs' => $item['gross_kg'],
                    'measurement_m3' => $item['measure_m3'],
                ];
            })
            ->toArray();

        if (!empty($containers)) {
            $billOfLading->containers()->createMany($containers);
        }

        return response()->json($billOfLading->load('containers'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(BillOfLading $billOfLading)
    {
        return response()->json($billOfLading->load('containers'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BillOfLading $billOfLading)
    {
        $validated = $request->validate([
            'bill_number' => 'sometimes|required|string|unique:bill_of_ladings,bill_number,' . $billOfLading->id,
            'shipper' => 'nullable|string',
            'consignee' => 'nullable|string',
            'port_of_loading' => 'nullable|string',
            'port_of_discharge' => 'nullable|string',
            'issue_date' => 'nullable|date',
        ]);

        $billOfLading->update($validated);

        return response()->json($billOfLading);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BillOfLading $billOfLading)
    {
        $billOfLading->delete();

        return response()->json(null, 204);
    }
}
