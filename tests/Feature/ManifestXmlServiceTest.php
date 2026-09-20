<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Voyage;
use App\Models\BillOfLading;
use App\Services\ManifestXmlService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ManifestXmlServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_igm_creates_valid_xml()
    {
        $voyage = Voyage::factory()->create([
            'vessel_name' => 'HR HERA',
            'voyage_number' => 'MHRA0106N'
        ]);
        
        $bol = BillOfLading::factory()->create([
            'voyage_id' => $voyage->id,
            'bill_number' => 'JHSNBO266379'
        ]);

        $service = new ManifestXmlService();
        $xml = $service->generateIgm($voyage);

        $this->assertStringContainsString('<Awmds>', $xml);
        $this->assertStringContainsString('<General_segment>', $xml);
        $this->assertStringContainsString('<Voyage_number>MHRA0106N</Voyage_number>', $xml);
        $this->assertStringContainsString('<Bol_reference>JHSNBO266379</Bol_reference>', $xml);
    }
}
