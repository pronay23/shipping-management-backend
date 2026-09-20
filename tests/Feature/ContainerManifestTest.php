<?php

use App\Models\BillOfLading;
use App\Models\ContainerManifest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a container manifest belongs to a bill of lading', function () {
    $billOfLading = BillOfLading::factory()->create([
        'bill_number' => 'BL-123456',
    ]);

    $container = ContainerManifest::factory()->create([
        'bill_of_lading_id' => $billOfLading->id,
        'bill_number' => $billOfLading->bill_number,
        'container_no' => 'CONT-987654',
        'seal_no' => 'SEAL-111111',
        'bags' => 150,
        'gross_weight_kgs' => 1250.500,
        'measurement_m3' => 30.150,
    ]);

    expect($container->billOfLading)->toBeInstanceOf(BillOfLading::class);
    expect($container->billOfLading->id)->toBe($billOfLading->id);
    expect($container->bill_number)->toBe('BL-123456');
    expect($container->container_no)->toBe('CONT-987654');
    expect($container->seal_no)->toBe('SEAL-111111');
    expect($container->bags)->toBe(150);
    expect($container->gross_weight_kgs)->toBe(1250.5);
    expect($container->measurement_m3)->toBe(30.15);
});

test('a bill of lading can have many container manifests', function () {
    $billOfLading = BillOfLading::factory()->create([
        'bill_number' => 'BL-789012',
    ]);

    $container1 = ContainerManifest::factory()->create([
        'bill_of_lading_id' => $billOfLading->id,
        'bill_number' => $billOfLading->bill_number,
    ]);

    $container2 = ContainerManifest::factory()->create([
        'bill_of_lading_id' => $billOfLading->id,
        'bill_number' => $billOfLading->bill_number,
    ]);

    expect($billOfLading->containers)->toHaveCount(2);
    expect($billOfLading->containers->pluck('id'))->toContain($container1->id, $container2->id);
});

test('can retrieve all container manifests via API', function () {
    ContainerManifest::factory()->count(3)->create();

    $response = $this->getJson('/api/container-manifests');

    $response->assertStatus(200)
        ->assertJsonCount(3);
});

test('can filter container manifests by bill of lading ID via API', function () {
    $bl1 = BillOfLading::factory()->create();
    $bl2 = BillOfLading::factory()->create();

    ContainerManifest::factory()->create(['bill_of_lading_id' => $bl1->id]);
    ContainerManifest::factory()->create(['bill_of_lading_id' => $bl2->id]);

    $response = $this->getJson("/api/container-manifests?bill_of_lading_id={$bl1->id}");

    $response->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonPath('0.bill_of_lading_id', $bl1->id);
});
