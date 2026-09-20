<?php

namespace App\Http\Controllers;

use App\Models\ContainerManifest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContainerManifestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ContainerManifest::query();

        if ($request->has('bill_of_lading_id')) {
            $query->where('bill_of_lading_id', $request->query('bill_of_lading_id'));
        }

        return response()->json($query->get());
    }
}
