<?php

namespace Database\Seeders;

use App\Models\Uom;
use Illuminate\Database\Seeder;

class UomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $uoms = [
            ['uom_name' => 'Piece', 'uom_description' => 'Individual unit'],
            ['uom_name' => 'Kilogram', 'uom_description' => 'Weight in kilograms'],
            ['uom_name' => 'Meter', 'uom_description' => 'Length in meters'],
            ['uom_name' => 'Liter', 'uom_description' => 'Volume in liters'],
            ['uom_name' => 'Box', 'uom_description' => 'Standard box container'],
            ['uom_name' => 'Carton', 'uom_description' => 'Carton packaging'],
            ['uom_name' => 'Set', 'uom_description' => 'Complete set of items'],
            ['uom_name' => 'Pair', 'uom_description' => 'Two items together'],
            ['uom_name' => 'Dozen', 'uom_description' => 'Group of twelve'],
            ['uom_name' => 'Ton', 'uom_description' => 'Weight in metric tons'],
            ['uom_name' => 'Square Meter', 'uom_description' => 'Area in square meters'],
            ['uom_name' => 'Container', 'uom_description' => 'Shipping container unit'],
        ];

        foreach ($uoms as $uom) {
            Uom::updateOrCreate(
                ['uom_name' => $uom['uom_name']],
                ['uom_description' => $uom['uom_description']]
            );
        }
    }
}
