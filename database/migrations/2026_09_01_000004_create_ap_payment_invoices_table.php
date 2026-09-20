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
        Schema::create('ap_payment_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ap_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ap_invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->timestamps();

            $table->index('ap_payment_id');
            $table->index('ap_invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ap_payment_invoices');
    }
};
