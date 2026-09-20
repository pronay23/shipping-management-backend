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
        Schema::create('money_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable()->default('Money Receipt');
            $table->string('money_receipt_number')->unique();
            $table->date('money_receipt_date')->nullable();
            $table->string('bl_number')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('vessel')->nullable();
            $table->string('voyage')->nullable();
            $table->string('registration_no')->nullable();
            $table->string('containers')->nullable();
            $table->decimal('exchange_rate', 14, 4)->nullable()->default(110.0000);
            $table->text('amount_in_words')->nullable();
            $table->decimal('total_usd', 18, 2)->nullable();
            $table->decimal('total_bdt', 18, 2)->nullable();
            $table->string('payment_term')->nullable();
            $table->timestamps();
            $table->index('money_receipt_number');
            $table->index('money_receipt_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('money_receipts');
    }
};
