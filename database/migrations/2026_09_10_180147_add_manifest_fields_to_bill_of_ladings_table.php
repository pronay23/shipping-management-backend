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
        Schema::table('bill_of_ladings', function (Blueprint $table) {
            $table->string('bol_nature')->nullable();
            $table->string('bol_type_code')->nullable();
            $table->string('consolidated_cargo')->nullable();
            $table->string('shipping_agent_code')->nullable();
            $table->string('shipping_agent_name')->nullable();
            $table->string('notify_code')->nullable();
            $table->string('notify_address')->nullable();
            $table->string('consignee_code')->nullable();
            $table->string('consignee_address')->nullable();
            $table->string('exporter_name')->nullable();
            $table->string('exporter_address')->nullable();
            $table->string('package_type_code')->nullable();
            $table->string('shipping_marks')->nullable();
            $table->string('volume_in_cubic_meters')->nullable();
            $table->string('freight_value')->nullable();
            $table->string('freight_currency')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bill_of_ladings', function (Blueprint $table) {
            $table->dropColumn([
                'bol_nature',
                'bol_type_code',
                'consolidated_cargo',
                'shipping_agent_code',
                'shipping_agent_name',
                'notify_code',
                'notify_address',
                'consignee_code',
                'consignee_address',
                'exporter_name',
                'exporter_address',
                'package_type_code',
                'shipping_marks',
                'volume_in_cubic_meters',
                'freight_value',
                'freight_currency'
            ]);
        });
    }
};
