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
        Schema::create('account_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        $now = now();
        $types = [
            ['slug' => 'asset', 'name' => 'Asset', 'description' => 'Resources owned by the business that have economic value.', 'is_system' => true],
            ['slug' => 'liability', 'name' => 'Liability', 'description' => 'Debts or obligations owed by the business to external parties.', 'is_system' => true],
            ['slug' => 'equity', 'name' => 'Equity', 'description' => 'Owner or shareholder residual interest in the assets.', 'is_system' => true],
            ['slug' => 'revenue', 'name' => 'Revenue', 'description' => 'Income generated from standard business operations.', 'is_system' => true],
            ['slug' => 'expense', 'name' => 'Expense', 'description' => 'Costs incurred in operating and running the business.', 'is_system' => true],
        ];

        foreach ($types as $t) {
            DB::table('account_types')->insert([
                'slug' => $t['slug'],
                'name' => $t['name'],
                'description' => $t['description'],
                'is_system' => $t['is_system'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_types');
    }
};
