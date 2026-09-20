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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id', 50)->unique()->index();
            $table->string('name');
            $table->string('department', 100)->nullable()->index();
            $table->string('designation', 100)->nullable()->index();
            $table->string('official_mobile', 20)->nullable();
            $table->string('personal_mobile', 20)->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('image')->nullable();
            $table->string('nid', 50)->nullable()->unique();
            $table->date('joining_date')->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->date('birthday')->nullable();
            $table->text('present_address')->nullable();
            $table->text('personal_address')->nullable();
            $table->string('emergency_person_mobile', 20)->nullable();
            $table->string('relationship_with_emergency_person', 100)->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};