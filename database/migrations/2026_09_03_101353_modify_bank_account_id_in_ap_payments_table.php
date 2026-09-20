<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ap_payments', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
            $table->string('bank_account_id', 20)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ap_payments', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete()->change();
        });
    }
};
