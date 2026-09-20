<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            ['country_code' => 'BD', 'country_name' => 'Bangladesh'],
            ['country_code' => 'IN', 'country_name' => 'India'],
            ['country_code' => 'CN', 'country_name' => 'China'],
            ['country_code' => 'US', 'country_name' => 'United States'],
            ['country_code' => 'GB', 'country_name' => 'United Kingdom'],
            ['country_code' => 'DE', 'country_name' => 'Germany'],
            ['country_code' => 'FR', 'country_name' => 'France'],
            ['country_code' => 'JP', 'country_name' => 'Japan'],
            ['country_code' => 'SG', 'country_name' => 'Singapore'],
            ['country_code' => 'AE', 'country_name' => 'United Arab Emirates'],
            ['country_code' => 'SA', 'country_name' => 'Saudi Arabia'],
            ['country_code' => 'MY', 'country_name' => 'Malaysia'],
            ['country_code' => 'TH', 'country_name' => 'Thailand'],
            ['country_code' => 'VN', 'country_name' => 'Vietnam'],
            ['country_code' => 'KR', 'country_name' => 'South Korea'],
            ['country_code' => 'AU', 'country_name' => 'Australia'],
            ['country_code' => 'CA', 'country_name' => 'Canada'],
            ['country_code' => 'NL', 'country_name' => 'Netherlands'],
            ['country_code' => 'HK', 'country_name' => 'Hong Kong'],
            ['country_code' => 'LK', 'country_name' => 'Sri Lanka'],
        ];

        foreach ($countries as $country) {
            Country::updateOrCreate(
                ['country_code' => $country['country_code']],
                ['country_name' => $country['country_name']]
            );
        }
    }
}
