<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_reports', function (Blueprint $table) {
            $table->string('report_type', 32)->default('board')->after('board_name');
            $table->index(['user_id', 'generated_at'], 'board_reports_user_generated_idx');
        });

        // Backfill one row at a time so MySQL never sorts/loads all JSON blobs at once.
        $ids = DB::table('board_reports')->orderBy('id')->pluck('id');
        foreach ($ids as $id) {
            DB::statement(
                "UPDATE board_reports SET report_type = COALESCE(
                    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(report_data, '$.report_type')), ''),
                    'board'
                ) WHERE id = ?",
                [$id]
            );
        }
    }

    public function down(): void
    {
        Schema::table('board_reports', function (Blueprint $table) {
            $table->dropIndex('board_reports_user_generated_idx');
            $table->dropColumn('report_type');
        });
    }
};
