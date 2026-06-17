<?php

namespace App\Http\Controllers;

use App\Models\BoardReport;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $recentReports = collect();
        if (auth()->check() && Schema::hasColumn('board_reports', 'report_type')) {
            // Step 1: fetch only ids via index (user_id, generated_at) — never sort huge report_data.
            $ids = BoardReport::query()
                ->where('user_id', auth()->id())
                ->orderByDesc('generated_at')
                ->limit(8)
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                $recentReports = BoardReport::query()
                    ->whereIn('id', $ids)
                    ->select(['id', 'board_id', 'board_name', 'report_type', 'generated_at'])
                    ->orderByDesc('generated_at')
                    ->get();
            }
        }

        return view('dashboard', [
            'recentReports' => $recentReports,
        ]);
    }
}
