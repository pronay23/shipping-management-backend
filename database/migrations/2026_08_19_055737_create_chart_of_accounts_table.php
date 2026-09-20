<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('type', 20);
            $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();
        });

        $this->seedSystemAccounts();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }

    /**
     * Seed the base system chart of accounts so journal posting always has
     * somewhere to land even before the database seeder runs.
     */
    private function seedSystemAccounts(): void
    {
        $accounts = [
            ['1010', 'Cash in Hand', 'asset', true],
            ['1020', 'Bank – One Bank PLC (RD 0021020013801)', 'asset', true],
            ['1100', 'Accounts Receivable (control)', 'asset', true],
            ['3000', 'Retained Earnings', 'equity', true],
            ['4001', 'DOC Fee Income', 'revenue', false],
            ['4002', 'Admin Fee Income', 'revenue', false],
            ['4003', 'Cleaning Income', 'revenue', false],
            ['4004', 'Survey Income', 'revenue', false],
            ['4005', 'Lift-On Income', 'revenue', false],
            ['4006', 'Detention Income', 'revenue', false],
            ['4007', 'FCL DG Income', 'revenue', false],
            ['4008', 'Misc. Income', 'revenue', false],
            ['4100', 'Service Revenue (fallback)', 'revenue', false],
        ];

        $now = now();

        DB::table('chart_of_accounts')->insert(
            array_map(
                fn (array $account) => [
                    'code' => $account[0],
                    'name' => $account[1],
                    'type' => $account[2],
                    'is_system' => $account[3],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $accounts
            )
        );
    }
};
