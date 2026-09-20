<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ChartOfAccountSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            ['code' => '1010', 'name' => 'Cash in Hand', 'type' => 'asset', 'is_system' => true],
            ['code' => '1020', 'name' => 'Bank – One Bank PLC (RD 0021020013801)', 'type' => 'asset', 'is_system' => true],
            ['code' => '1100', 'name' => 'Accounts Receivable (control)', 'type' => 'asset', 'is_system' => true],
            ['code' => '2100', 'name' => 'Accounts Payable (control)', 'type' => 'liability', 'is_system' => true],
            ['code' => '3000', 'name' => 'Retained Earnings', 'type' => 'equity', 'is_system' => true],
            ['code' => '4001', 'name' => 'DOC Fee Income', 'type' => 'revenue', 'is_system' => false],
            ['code' => '4002', 'name' => 'Admin Fee Income', 'type' => 'revenue', 'is_system' => false],
            ['code' => '4003', 'name' => 'Cleaning Income', 'type' => 'revenue', 'is_system' => false],
            ['code' => '4004', 'name' => 'Survey Income', 'type' => 'revenue', 'is_system' => false],
            ['code' => '4005', 'name' => 'Lift-On Income', 'type' => 'revenue', 'is_system' => false],
            ['code' => '4006', 'name' => 'Detention Income', 'type' => 'revenue', 'is_system' => false],
            ['code' => '4007', 'name' => 'FCL DG Income', 'type' => 'revenue', 'is_system' => false],
            ['code' => '4008', 'name' => 'Misc. Income', 'type' => 'revenue', 'is_system' => false],
            ['code' => '4100', 'name' => 'Service Revenue (fallback)', 'type' => 'revenue', 'is_system' => false],
            ['code' => '5001', 'name' => 'Ocean Freight Expense', 'type' => 'expense', 'is_system' => false],
            ['code' => '5002', 'name' => 'Terminal Handling Expense', 'type' => 'expense', 'is_system' => false],
            ['code' => '5003', 'name' => 'Port Charges Expense', 'type' => 'expense', 'is_system' => false],
            ['code' => '5004', 'name' => 'C&F Commission Expense', 'type' => 'expense', 'is_system' => false],
            ['code' => '5005', 'name' => 'Customs Duty Expense', 'type' => 'expense', 'is_system' => false],
            ['code' => '5006', 'name' => 'Transport Expense', 'type' => 'expense', 'is_system' => false],
            ['code' => '5007', 'name' => 'Warehouse Expense', 'type' => 'expense', 'is_system' => false],
            ['code' => '5008', 'name' => 'Insurance Expense', 'type' => 'expense', 'is_system' => false],
            ['code' => '5009', 'name' => 'Office Supplies Expense', 'type' => 'expense', 'is_system' => false],
            ['code' => '5099', 'name' => 'Miscellaneous Expense', 'type' => 'expense', 'is_system' => false],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::firstOrCreate(
                ['code' => $account['code']],
                $account
            );
        }
    }
}
