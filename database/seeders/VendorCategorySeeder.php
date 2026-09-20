<?php

namespace Database\Seeders;

use App\Models\VendorCategory;
use Illuminate\Database\Seeder;

class VendorCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'C&F Agent', 'description' => 'Clearing and forwarding agents responsible for customs clearance and cargo handling.'],
            ['name' => 'Oversea Agent', 'description' => 'Agents operating internationally for shipping and logistics coordination.'],
            ['name' => 'Office Supplier', 'description' => 'Vendors providing office stationery, equipment, and supplies.'],
            ['name' => 'Shipping Line', 'description' => 'Companies operating shipping vessels for cargo transport.'],
            ['name' => 'Customs Broker', 'description' => 'Licensed professionals handling customs documentation and compliance.'],
            ['name' => 'Transporter', 'description' => 'Land transport providers for cargo movement within the country.'],
            ['name' => 'Warehouse Provider', 'description' => 'Vendors offering warehousing and storage facilities.'],
            ['name' => 'Insurance Provider', 'description' => 'Companies providing cargo and marine insurance services.'],
        ];

        foreach ($categories as $category) {
            VendorCategory::updateOrCreate(
                ['name' => $category['name']],
                ['description' => $category['description']]
            );
        }
    }
}
