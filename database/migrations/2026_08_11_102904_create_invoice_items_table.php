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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('key')->nullable();
            $table->string('label')->nullable();
            $table->unsignedInteger('qty_20')->default(0);
            $table->unsignedInteger('qty_40')->default(0);
            $table->decimal('rate_usd', 14, 4)->nullable();
            $table->decimal('rate_bdt', 14, 4)->nullable();
            $table->decimal('total_usd', 18, 2)->nullable();
            $table->timestamps();
            $table->index('invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};