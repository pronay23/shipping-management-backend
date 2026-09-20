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
        Schema::table('container_manifests', function (Blueprint $table) {
            $table->string('type_of_container')->nullable();
            $table->string('commodity_code')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('container_manifests', function (Blueprint $table) {
            $table->dropColumn([
                'type_of_container',
                'commodity_code'
            ]);
        });
    }
};
