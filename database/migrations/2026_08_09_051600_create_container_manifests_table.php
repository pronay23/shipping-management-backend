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
        Schema::create('container_manifests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_of_lading_id')->constrained('bill_of_ladings')->cascadeOnDelete();
            $table->string('bill_number');
            $table->string('container_no');
            $table->string('seal_no');
            $table->integer('bags');
            $table->decimal('gross_weight_kgs', 10, 3);
            $table->decimal('measurement_m3', 10, 3);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('container_manifests');
    }
};
