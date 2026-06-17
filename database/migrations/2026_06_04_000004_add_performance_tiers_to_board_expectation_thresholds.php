<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_expectation_thresholds', function (Blueprint $table) {
            $table->decimal('baseline_min', 5, 2)->nullable()->after('threshold');
            $table->decimal('baseline_max', 5, 2)->nullable()->after('baseline_min');
            $table->decimal('tier_2_min', 5, 2)->nullable()->after('baseline_max');
            $table->decimal('tier_2_max', 5, 2)->nullable()->after('tier_2_min');
            $table->decimal('tier_4_min', 5, 2)->nullable()->after('tier_2_max');
            $table->decimal('tier_4_max', 5, 2)->nullable()->after('tier_4_min');
            $table->decimal('tier_6_min', 5, 2)->nullable()->after('tier_4_max');
            $table->decimal('tier_6_max', 5, 2)->nullable()->after('tier_6_min');
        });

        DB::table('board_expectation_thresholds')->update([
            'baseline_min' => 80,
            'baseline_max' => 90,
            'tier_2_min' => 91,
            'tier_2_max' => 93,
            'tier_4_min' => 94,
            'tier_4_max' => 97,
            'tier_6_min' => 98,
            'tier_6_max' => 100,
        ]);
    }

    public function down(): void
    {
        Schema::table('board_expectation_thresholds', function (Blueprint $table) {
            $table->dropColumn([
                'baseline_min',
                'baseline_max',
                'tier_2_min',
                'tier_2_max',
                'tier_4_min',
                'tier_4_max',
                'tier_6_min',
                'tier_6_max',
            ]);
        });
    }
};
