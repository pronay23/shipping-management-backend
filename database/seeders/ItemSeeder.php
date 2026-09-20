<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\Uom;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $piece = Uom::where('uom_name', 'Piece')->first();
        $kg = Uom::where('uom_name', 'Kilogram')->first();
        $carton = Uom::where('uom_name', 'Carton')->first();
        $container = Uom::where('uom_name', 'Container')->first();
        $set = Uom::where('uom_name', 'Set')->first();

        $items = [
            [
                'item_code' => 'ITM-0001',
                'item_name' => 'Shipping Container Seal',
                'item_type' => 'Product',
                'uom_id' => $piece?->id,
                'item_prices' => 150,
                'item_taxes' => 22.50,
            ],
            [
                'item_code' => 'ITM-0002',
                'item_name' => 'Cargo Handling Service',
                'item_type' => 'Service',
                'uom_id' => $container?->id,
                'item_prices' => 25000,
                'item_taxes' => 0,
            ],
            [
                'item_code' => 'ITM-0003',
                'item_name' => 'Packing Tape',
                'item_type' => 'Consumable',
                'uom_id' => $piece?->id,
                'item_prices' => 80,
                'item_taxes' => 12,
            ],
            [
                'item_code' => 'ITM-0004',
                'item_name' => 'Warehouse Storage',
                'item_type' => 'Service',
                'uom_id' => $carton?->id,
                'item_prices' => 500,
                'item_taxes' => 75,
            ],
            [
                'item_code' => 'ITM-0005',
                'item_name' => 'Container Loading Kit',
                'item_type' => 'Product',
                'uom_id' => $set?->id,
                'item_prices' => 3500,
                'item_taxes' => 525,
            ],
        ];

        foreach ($items as $item) {
            Item::updateOrCreate(
                ['item_code' => $item['item_code']],
                $item
            );
        }
    }
}
