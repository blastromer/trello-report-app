<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_team_groups', function (Blueprint $table) {
            $table->string('performance_mode', 16)->default('month')->after('member_ids');
            $table->string('performance_month', 7)->nullable()->after('performance_mode');
            $table->unsignedSmallInteger('performance_year')->nullable()->after('performance_month');
            $table->string('sprint_label', 128)->nullable()->after('performance_year');
            $table->date('sprint_date_from')->nullable()->after('sprint_label');
            $table->date('sprint_date_to')->nullable()->after('sprint_date_from');
        });
    }

    public function down(): void
    {
        Schema::table('board_team_groups', function (Blueprint $table) {
            $table->dropColumn([
                'performance_mode',
                'performance_month',
                'performance_year',
                'sprint_label',
                'sprint_date_from',
                'sprint_date_to',
            ]);
        });
    }
};
