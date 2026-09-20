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
        Schema::create('bill_of_ladings', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number')->unique();
            $table->string('booking_number')->nullable()->index();
            $table->string('vessel_name')->nullable()->index();
            $table->string('vessel_number')->nullable();
            $table->string('place_of_receipt')->nullable();
            $table->string('port_of_loading')->nullable();
            $table->string('port_of_discharge')->nullable();
            $table->string('place_of_delivery')->nullable();
            $table->string('final_destination')->nullable();
            $table->date('shipped_on_board_date')->nullable()->index();
            $table->string('free_time_days')->nullable();
            $table->text('shipper_name')->nullable()->index();
            $table->text('consignee_name')->nullable()->index();
            $table->text('notify_party')->nullable();
            $table->text('carrier')->nullable()->index();
            $table->text('delivery_contact')->nullable();
            $table->string('product_name')->nullable();
            $table->string('manufacturer_name')->nullable();
            $table->string('country_of_origin')->nullable();
            $table->date('mfg_date')->nullable();
            $table->date('exp_date')->nullable();
            $table->string('hs_code_import')->nullable();
            $table->string('hs_code_export')->nullable();
            $table->decimal('net_weight_per_bag',10,2)->nullable();
            $table->decimal('total_net_weight_mt',10,3)->nullable();
            $table->decimal('total_gross_weight_mt',10,3)->nullable();
            $table->text('delivery_term_notes')->nullable();
            $table->string('proforma_invoice_no')->nullable();
            $table->date('proforma_invoice_date')->nullable();
            $table->string('doc_credit_no')->nullable();
            $table->date('doc_credit_date')->nullable();
            $table->string('irc_old_no')->nullable();
            $table->string('irc_new_no')->nullable();
            $table->string('importer_tin')->nullable();
            $table->string('importer_vat')->nullable();
            $table->string('freight_terms')->nullable();
            $table->string('freight_prepaid_at')->nullable();
            $table->string('freight_payable_at')->nullable();
            $table->decimal('total_local_currency',15,2)->nullable();
            $table->date('date_of_issue')->nullable();
            $table->string('place_of_issue')->nullable();
            $table->string('originals_issued')->nullable();
            $table->string('signed_by')->nullable();
            $table->enum('status', [
                'draft',
                'confirmed',
                'shipped',
                'completed',
                'cancelled'
            ])->default('draft');       
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_of_ladings');
    }
};
