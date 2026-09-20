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
        Schema::create('money_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('money_receipt_id')->constrained()->cascadeOnDelete();
            $table->string('key')->nullable();
            $table->string('label')->nullable();
            $table->integer('qty_20')->nullable();
            $table->integer('qty_40')->nullable();
            $table->decimal('rate_usd', 14, 4)->nullable();
            $table->decimal('rate_bdt', 14, 4)->nullable();
            $table->decimal('total_usd', 18, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('money_receipt_items');
    }
};