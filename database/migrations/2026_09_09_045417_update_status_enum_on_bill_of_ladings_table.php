<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE bill_of_ladings DROP CONSTRAINT IF EXISTS bill_of_ladings_status_check');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE bill_of_ladings ADD CONSTRAINT bill_of_ladings_status_check CHECK (status::text = ANY (ARRAY['draft'::character varying, 'confirmed'::character varying, 'shipped'::character varying, 'completed'::character varying, 'cancelled'::character varying]::text[]))");
    }
};
