<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->foreignId('account_type_id')->nullable()->after('type')->constrained('account_types')->nullOnDelete();
        });

        // Backfill account_type_id from existing string type column
        $accountTypes = DB::table('account_types')->get()->keyBy('slug');

        $chartAccounts = DB::table('chart_of_accounts')->get();
        foreach ($chartAccounts as $account) {
            if (isset($accountTypes[$account->type])) {
                DB::table('chart_of_accounts')
                    ->where('id', $account->id)
                    ->update(['account_type_id' => $accountTypes[$account->type]->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropForeign(['account_type_id']);
            $table->dropColumn('account_type_id');
        });
    }
};
