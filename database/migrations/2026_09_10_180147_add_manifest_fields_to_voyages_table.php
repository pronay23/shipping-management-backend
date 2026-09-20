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
        Schema::table('voyages', function (Blueprint $table) {
            $table->string('customs_office_code')->nullable();
            $table->string('carrier_code')->nullable();
            $table->string('carrier_name')->nullable();
            $table->string('carrier_address')->nullable();
            $table->string('mode_of_transport_code')->nullable();
            $table->string('nationality_of_transporter_code')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voyages', function (Blueprint $table) {
            $table->dropColumn([
                'customs_office_code',
                'carrier_code',
                'carrier_name',
                'carrier_address',
                'mode_of_transport_code',
                'nationality_of_transporter_code'
            ]);
        });
    }
};
