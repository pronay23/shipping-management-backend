<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Voyage;
use App\Services\ManifestXmlService;
use Illuminate\Http\Response;

class ManifestXmlController extends Controller
{
    public function generateIgm(Voyage $voyage, ManifestXmlService $xmlService): Response
    {
        $xml = $xmlService->generateIgm($voyage);
        $filename = 'IGM_' . $voyage->voyage_number . '_' . date('YmdHis') . '.xml';

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function generateEgm(Voyage $voyage, ManifestXmlService $xmlService): Response
    {
        $xml = $xmlService->generateEgm($voyage);
        $filename = 'EGM_' . $voyage->voyage_number . '_' . date('YmdHis') . '.xml';

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
