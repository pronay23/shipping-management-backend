<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drivers that enforce named CHECK constraints added via ALTER TABLE.
     * SQLite cannot add constraints after table creation, so it is skipped;
     * the service layer remains the first line of defense everywhere.
     *
     * @return list<string>
     */
    private function checkConstraintDrivers(): array
    {
        return ['mysql', 'mariadb'];
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->index(['status', 'entry_date']);
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreignId('reverses_journal_entry_id')
                ->nullable()
                ->after('source_id')
                ->constrained('journal_entries')
                ->nullOnDelete();
        });

        if (in_array(DB::connection()->getDriverName(), $this->checkConstraintDrivers(), true)) {
            DB::statement(<<<'SQL'
                ALTER TABLE journal_entry_lines ADD CONSTRAINT chk_line_side
                CHECK ((debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0))
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE journal_entries ADD CONSTRAINT chk_balanced
                CHECK (total_debit = total_credit AND total_debit > 0)
            SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (in_array(DB::connection()->getDriverName(), $this->checkConstraintDrivers(), true)) {
            DB::statement('ALTER TABLE journal_entry_lines DROP CONSTRAINT chk_line_side');
            DB::statement('ALTER TABLE journal_entries DROP CONSTRAINT chk_balanced');
        }

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reverses_journal_entry_id');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex(['status', 'entry_date']);
        });
    }
};
