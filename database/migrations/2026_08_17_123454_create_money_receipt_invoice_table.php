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
        Schema::create('money_receipt_invoice', function (Blueprint $table) {
            $table->id();
            $table->foreignId('money_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_of_lading_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('paid_amount', 18, 2)->nullable();
            $table->timestamps();
            $table->index('money_receipt_id');
            $table->index('invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('money_receipt_invoice');
    }
};