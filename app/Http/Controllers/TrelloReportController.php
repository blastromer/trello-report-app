<?php

namespace App\Http\Controllers;

use App\Models\BoardReport;
use App\Support\ProfessionalismKpiReference;
use App\Services\AccountabilityDocxExportService;
use App\Services\AccountabilityReportService;
use App\Services\IndividualKpiDocxExportService;
use App\Services\IndividualKpiReportService;
use App\Services\TrelloService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrelloReportController extends Controller
{
    protected $trelloService;

    protected $accountabilityReportService;

    protected $individualKpiReportService;

    protected $individualKpiDocxExportService;

    protected $accountabilityDocxExportService;

    public function __construct(
        TrelloService $trelloService,
        AccountabilityReportService $accountabilityReportService,
        IndividualKpiReportService $individualKpiReportService,
        IndividualKpiDocxExportService $individualKpiDocxExportService,
        AccountabilityDocxExportService $accountabilityDocxExportService
    )
    {
        $this->trelloService = $trelloService;
        $this->accountabilityReportService = $accountabilityReportService;
        $this->individualKpiReportService = $individualKpiReportService;
        $this->individualKpiDocxExportService = $individualKpiDocxExportService;
        $this->accountabilityDocxExportService = $accountabilityDocxExportService;
    }

    /**
     * Display a listing of all Trello boards.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        try {
            $boards = $this->trelloService->getBoards();
            return view('trello.boards', compact('boards'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to fetch boards: ' . $e->getMessage());
        }
    }

    /**
     * Show filter form for generating a report.
     *
     * @param string $boardId
     * @return \Illuminate\View\View
     */
    public function showFilterForm($boardId)
    {
        try {
            $board = $this->trelloService->getBoard($boardId);
            $members = $this->trelloService->getBoardMembers($boardId);
            $lists = $this->trelloService->getBoardLists($boardId);

            return view('trello.filter', [
                'boardId' => $boardId,
                'boardName' => $board['name'] ?? 'Unknown Board',
                'members' => $members,
                'lists' => $lists,
            ]);
        } catch (\Exception $e) {
            return redirect()->route('trello.boards')->with('error', 'Failed to load board: ' . $e->getMessage());
        }
    }

    /**
     * Generate a report for a specific board.
     *
     * @param Request $request
     * @param string $boardId
     * @return \Illuminate\View\View
     */
    public function generateReport(Request $request, $boardId)
    {
        try {
            $hasDateRange = $request->filled('date_from') || $request->filled('date_to');
            // Strict Date Completed when a range is set; broad mode only if user checks "date_filter_broad".
            // (Avoid hidden+checkbox on the same name — that can submit both 0 and 1 and break boolean().)
            $filters = [
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'date_completed_only' => $hasDateRange && !$request->boolean('date_filter_broad'),
                'assignees' => $request->input('assignees', []),
                'lists' => $request->input('lists', []),
            ];

            $reportData = $this->trelloService->generateBoardReport($boardId, $filters);

            // Get board name from API
            $boardName = 'Unknown Board';
            try {
                $board = $this->trelloService->getBoard($boardId);
                $boardName = $board['name'] ?? 'Unknown Board';
            } catch (\Exception $e) {
                // Try to get from boards list as fallback
                try {
                    $boards = $this->trelloService->getBoards();
                    foreach ($boards as $board) {
                        if ($board['id'] === $boardId) {
                            $boardName = $board['name'];
                            break;
                        }
                    }
                } catch (\Exception $e2) {
                    // Use default if we can't fetch board name
                }
            }

            // Save report to database
            $boardReport = BoardReport::create([
                'user_id' => auth()->id(),
                'board_id' => $boardId,
                'board_name' => $boardName,
                'report_data' => $reportData,
                'generated_at' => now(),
            ]);

            return view('trello.report', [
                'report' => $reportData,
                'boardReport' => $boardReport,
            ]);
        } catch (\Exception $e) {
            return redirect()->route('trello.boards')->with('error', 'Failed to generate report: ' . $e->getMessage());
        }
    }

    /**
     * Export a board report as CSV.
     *
     * @param string $boardId
     * @return \Illuminate\Http\Response
     */
    public function export($boardId)
    {
        try {
            $boardReport = BoardReport::where('board_id', $boardId)
                ->where('user_id', auth()->id())
                ->latest()
                ->first();

            if (!$boardReport) {
                // Generate report if it doesn't exist
                $reportData = $this->trelloService->generateBoardReport($boardId);
                $boardName = 'Unknown Board';

                try {
                    $board = $this->trelloService->getBoard($boardId);
                    $boardName = $board['name'] ?? 'Unknown Board';
                } catch (\Exception $e) {
                    // Try to get from boards list as fallback
                    try {
                        $boards = $this->trelloService->getBoards();
                        foreach ($boards as $board) {
                            if ($board['id'] === $boardId) {
                                $boardName = $board['name'];
                                break;
                            }
                        }
                    } catch (\Exception $e2) {
                        // Use default
                    }
                }

                $boardReport = BoardReport::create([
                    'user_id' => auth()->id(),
                    'board_id' => $boardId,
                    'board_name' => $boardName,
                    'report_data' => $reportData,
                    'generated_at' => now(),
                ]);
            } else {
                $reportData = $boardReport->report_data;
            }

            // Generate CSV content
            $csvData = [];
            $csvData[] = ['Board Report: ' . $boardReport->board_name];
            $csvData[] = ['Generated At: ' . $boardReport->generated_at->format('Y-m-d H:i:s')];
            $csvData[] = [];
            $csvData[] = ['Summary'];
            $csvData[] = ['Total Cards', $reportData['total_cards']];
            $csvData[] = ['Total Lists', $reportData['total_lists']];
            $csvData[] = ['Total Points', $reportData['total_points'] ?? 0];
            $csvData[] = [];
            $csvData[] = ['Status Breakdown'];
            $csvData[] = ['Status', 'Count'];
            foreach ($reportData['status_breakdown'] as $status => $count) {
                if ($status !== 'total') {
                    $csvData[] = [ucfirst(str_replace('_', ' ', $status)), $count];
                }
            }
            $csvData[] = [];
            if (!empty($reportData['member_stats'])) {
                $csvData[] = ['Member Statistics'];
                $csvData[] = ['Member', 'Cards Assigned', 'Total Points', 'Average Points'];
                foreach ($reportData['member_stats'] as $memberId => $stats) {
                    $avgPoints = $stats['card_count'] > 0 ? number_format($stats['total_points'] / $stats['card_count'], 1) : 0;
                    $csvData[] = [
                        $stats['name'],
                        $stats['card_count'],
                        $stats['total_points'],
                        $avgPoints,
                    ];
                }
                $csvData[] = [];
            }
            $csvData[] = ['Cards by List'];
            $csvData[] = ['List Name', 'Card Count', 'Total Points'];
            foreach ($reportData['cards_by_list'] as $listName => $listData) {
                $csvData[] = [
                    $listData['list_name'],
                    $listData['card_count'],
                    $listData['total_points'] ?? 0,
                ];
            }
            $csvData[] = [];
            $csvData[] = ['Card Details'];
            $csvData[] = ['Card Name', 'List', 'Assignees', 'Points', 'Labels', 'Due Date', 'Date Completed', 'Status'];
            foreach ($reportData['cards'] as $card) {
                $members = !empty($card['members']) ? implode(', ', $card['members']) : 'Unassigned';
                $labels = !empty($card['labels']) ? implode(', ', array_column($card['labels'], 'name')) : '-';

                $normalizedListName = strtolower(trim($card['list_name']));
                $explicitCompletedLists = [
                    'for dev deployment/review (tiger/jan review)',
                    'for dev deployment/review',
                    'on dev environment',
                    'on staging / demo to po',
                    'on live',
                    'done / archive',
                    'done/archive',
                    'done/archived',
                    'done sprint',
                    'archive done',
                ];
                $explicitInProgressLists = [
                    'in dev',
                ];
                $explicitTodoLists = [
                    'current sprint',
                ];

                if (in_array($normalizedListName, $explicitCompletedLists, true) ||
                    // Handles variants like "On Live🎉"
                    str_starts_with($normalizedListName, 'on live') ||
                    str_contains($normalizedListName, 'for dev deployment/review') ||
                    str_contains($normalizedListName, 'for staging deployment/review') ||
                    strpos($normalizedListName, 'done') !== false ||
                    strpos($normalizedListName, 'complete') !== false) {
                    $status = 'Completed';
                } elseif (in_array($normalizedListName, $explicitInProgressLists, true) ||
                          strpos($normalizedListName, 'progress') !== false ||
                          strpos($normalizedListName, 'doing') !== false) {
                    $status = 'In Progress';
                } elseif (in_array($normalizedListName, $explicitTodoLists, true) ||
                          strpos($normalizedListName, 'todo') !== false ||
                          strpos($normalizedListName, 'backlog') !== false) {
                    $status = 'Todo';
                } else {
                    $status = 'Other';
                }
                $dueDate = $card['due_date'] ? \Carbon\Carbon::parse($card['due_date'])->format('Y-m-d') : '-';
                if ($card['due_complete'] ?? false) {
                    $dueDate .= ' (Completed)';
                }
                $dateCompleted = !empty($card['date_completed'])
                    ? \Carbon\Carbon::parse($card['date_completed'])->format('Y-m-d')
                    : '-';
                $csvData[] = [
                    $card['name'],
                    $card['list_name'],
                    $members,
                    $card['points'] ?? 0,
                    $labels,
                    $dueDate,
                    $dateCompleted,
                    $status,
                ];
            }

            // Convert to CSV string
            $output = fopen('php://temp', 'r+');
            foreach ($csvData as $row) {
                fputcsv($output, $row);
            }
            rewind($output);
            $csvContent = stream_get_contents($output);
            fclose($output);

            $filename = 'trello_report_' . $boardId . '_' . date('Y-m-d_His') . '.csv';

            return Response::make($csvContent, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Exception $e) {
            return redirect()->route('trello.boards')->with('error', 'Failed to export report: ' . $e->getMessage());
        }
    }

    /**
     * Form for Team Accountability Report (sprints + narrative + Trello-backed metrics).
     */
    public function showAccountabilityForm(string $boardId)
    {
        try {
            $board = $this->trelloService->getBoard($boardId);
            $members = $this->trelloService->getBoardMembers($boardId);

            return view('trello.accountability-form', [
                'boardId' => $boardId,
                'boardName' => $board['name'] ?? 'Unknown Board',
                'members' => $members,
            ]);
        } catch (\Exception $e) {
            return redirect()->route('trello.boards')->with('error', 'Failed to load board: ' . $e->getMessage());
        }
    }

    /**
     * Generate accountability document from sprints, optional narrative fields, and Trello data.
     */
    public function generateAccountabilityReport(Request $request, string $boardId)
    {
        $request->validate([
            'report_month' => 'required|date_format:Y-m',
            'expectation_threshold' => 'nullable|numeric|min:0|max:100',
            'assignees' => 'required|array|min:1',
            'assignees.*' => 'string',
            'key_accomplishments' => 'nullable|string',
            'ongoing_projects' => 'nullable|string',
            'challenges' => 'nullable|string',
            'plans_next_steps' => 'nullable|string',
            'context_interpretation' => 'nullable|string',
            'overall_outlook' => 'nullable|string',
            'sprints' => 'required|array|max:8',
            'sprints.*.label' => 'nullable|string|max:120',
            'sprints.*.date_from' => 'nullable|date',
            'sprints.*.date_to' => 'nullable|date',
        ]);

        $threshold = (float) ($request->input('expectation_threshold', 70));

        try {
            $assignees = array_values(array_filter((array) $request->input('assignees', [])));

            $sprintInputs = array_values(array_filter($request->input('sprints', []), function ($row) {
                return !empty($row['date_from']) && !empty($row['date_to']);
            }));

            if ($sprintInputs === []) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'Add at least one sprint with a start and end date.');
            }

            $boardName = 'Unknown Board';
            try {
                $board = $this->trelloService->getBoard($boardId);
                $boardName = $board['name'] ?? $boardName;
            } catch (\Exception $e) {
                // ignore
            }

            $sprintsOut = [];
            foreach ($sprintInputs as $i => $row) {
                $label = trim((string) ($row['label'] ?? '')) ?: 'Sprint ' . ($i + 1);
                $from = $row['date_from'];
                $to = $row['date_to'];
                $block = $this->accountabilityReportService->buildSprintBlock($boardId, $label, $from, $to, $assignees);
                $block['expectation_label'] = $this->accountabilityReportService->expectationLabel(
                    $block['overall_accomplishment_percent'],
                    $threshold
                );
                $sprintsOut[] = $block;
            }

            $month = Carbon::createFromFormat('Y-m', $request->input('report_month'))->startOfMonth();
            $monthStart = $month->format('Y-m-d');
            $monthEnd = $month->copy()->endOfMonth()->format('Y-m-d');
            $monthLabel = $month->format('F Y');

            $narrativeReport = $this->trelloService->generateBoardReport($boardId, [
                'date_from' => $monthStart,
                'date_to' => $monthEnd,
                'assignees' => $assignees,
                'lists' => [],
                'date_completed_only' => true,
            ]);

            $keyAccomplishmentsText = trim((string) $request->input('key_accomplishments', ''));
            if ($keyAccomplishmentsText === '') {
                $bullets = $this->accountabilityReportService->completedCardTitles($narrativeReport, 80);
                $keyAccomplishmentsText = $bullets === []
                    ? '(No completed cards matched the month range and filters. Add narrative manually or adjust dates.)'
                    : implode("\n", array_map(function ($t) {
                        return $t;
                    }, $bullets));
            }

            $ongoingText = trim((string) $request->input('ongoing_projects', ''));
            if ($ongoingText === '') {
                $ongoingTitles = $this->accountabilityReportService->ongoingCardTitles($narrativeReport, 60);
                $ongoingText = $ongoingTitles === []
                    ? ''
                    : implode("\n", $ongoingTitles);
            }

            $challengesText = trim((string) $request->input('challenges', ''));
            if ($challengesText === '') {
                $challengesText = $this->accountabilityReportService->suggestChallenges($sprintsOut, $threshold, $narrativeReport);
            }

            $plansText = trim((string) $request->input('plans_next_steps', ''));
            if ($plansText === '') {
                $plansText = $this->accountabilityReportService->suggestPlansNextSteps($narrativeReport);
            }

            $contextText = trim((string) $request->input('context_interpretation', ''));
            if ($contextText === '') {
                $contextText = $this->accountabilityReportService->suggestContextInterpretation($sprintsOut, $threshold, $monthLabel);
            }

            $outlookText = trim((string) $request->input('overall_outlook', ''));
            if ($outlookText === '') {
                $outlookText = $this->accountabilityReportService->suggestOverallOutlook($sprintsOut, $narrativeReport, $threshold);
            }

            $document = [
                'report_type' => 'accountability',
                'report_month' => $request->input('report_month'),
                'month_label' => $monthLabel,
                'board_id' => $boardId,
                'board_name' => $boardName,
                'expectation_threshold' => $threshold,
                'assignees_filter' => $assignees,
                'sprints' => $sprintsOut,
                'narrative' => [
                    'key_accomplishments' => $keyAccomplishmentsText,
                    'ongoing_projects' => $ongoingText,
                    'challenges' => $challengesText,
                    'plans_next_steps' => $plansText,
                    'context_interpretation' => $contextText,
                    'overall_outlook' => $outlookText,
                ],
                'narrative_period' => [
                    'from' => $monthStart,
                    'to' => $monthEnd,
                    'date_completed_only' => true,
                ],
            ];

            $boardReport = BoardReport::create([
                'user_id' => auth()->id(),
                'board_id' => $boardId,
                'board_name' => $boardName,
                'report_data' => $document,
                'generated_at' => now(),
            ]);

            return view('trello.accountability-report', [
                'document' => $document,
                'boardReport' => $boardReport,
            ]);
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to generate accountability report: ' . $e->getMessage());
        }
    }

    /**
     * Download Team Accountability report as .docx.
     */
    public function exportAccountabilityDocx(BoardReport $boardReport): StreamedResponse
    {
        if ((int) $boardReport->user_id !== (int) auth()->id()) {
            abort(403);
        }
        if (($boardReport->report_data['report_type'] ?? '') !== 'accountability') {
            abort(404);
        }

        $document = $boardReport->report_data;
        $slug = Str::slug($boardReport->board_name ?: 'accountability', '_');
        $filename = 'accountability_' . $slug . '_' . $boardReport->generated_at->format('Y-m-d_His') . '.docx';

        return response()->streamDownload(function () use ($document, $boardReport) {
            $this->accountabilityDocxExportService->writeDocxToOutput(
                $document,
                $boardReport->generated_at
            );
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    /**
     * Form for Individual KPI (severity-weighted productivity + efficiency).
     */
    public function showIndividualKpiForm(string $boardId)
    {
        try {
            $board = $this->trelloService->getBoard($boardId);
            $members = $this->trelloService->getBoardMembers($boardId);

            return view('trello.kpi-form', [
                'boardId' => $boardId,
                'boardName' => $board['name'] ?? 'Unknown Board',
                'members' => $members,
            ]);
        } catch (\Exception $e) {
            return redirect()->route('trello.boards')->with('error', 'Failed to load board: ' . $e->getMessage());
        }
    }

    /**
     * Generate Individual KPI report from sprint windows.
     */
    public function generateIndividualKpiReport(Request $request, string $boardId)
    {
        $request->validate([
            'date_coverage' => 'required|in:month,q1,q2,q3,q4',
            'report_month' => 'required_if:date_coverage,month|nullable|date_format:Y-m',
            'coverage_year' => 'required_unless:date_coverage,month|nullable|integer|min:2000|max:2100',
            'assignees' => 'required|array|min:1',
            'assignees.*' => 'string',
            'required_points' => 'required|array',
            'required_points.*' => 'nullable|numeric|min:0',
            'quality_percent' => 'nullable|array',
            'quality_percent.*' => 'nullable|numeric|min:0|max:100',
            'collaboration_percent' => 'nullable|array',
            'collaboration_percent.*' => 'nullable|numeric|min:0|max:100',
            'sprints' => 'required|array|max:8',
            'sprints.*.label' => 'nullable|string|max:120',
            'sprints.*.date_from' => 'nullable|date',
            'sprints.*.date_to' => 'nullable|date',
        ]);

        try {
            $assignees = array_values(array_filter((array) $request->input('assignees', [])));
            if ($assignees === []) {
                return redirect()->back()->withInput()->with('error', 'Select at least one team member.');
            }

            $proRules = [];
            foreach ($assignees as $mid) {
                foreach (ProfessionalismKpiReference::dimensionKeys() as $k) {
                    $proRules['professionalism.' . $mid . '.' . $k] = 'required|integer|min:0|max:5';
                }
            }
            $request->validate($proRules);

            $professionalismByMember = $this->individualKpiReportService->buildProfessionalismFromRequest($request, $assignees);

            $requiredPoints = (array) $request->input('required_points', []);
            $qualityPercent = (array) $request->input('quality_percent', []);
            $collabPercent = (array) $request->input('collaboration_percent', []);

            $requiredByMember = [];
            $qualityByMember = [];
            $collabByMember = [];
            foreach ($assignees as $mid) {
                $requiredByMember[$mid] = (float) ($requiredPoints[$mid] ?? 0);
                $qualityByMember[$mid] = $qualityPercent[$mid] ?? null;
                $collabByMember[$mid] = $collabPercent[$mid] ?? null;
            }

            $sprintInputs = array_values(array_filter($request->input('sprints', []), function ($row) {
                return !empty($row['date_from']) && !empty($row['date_to']);
            }));
            if ($sprintInputs === []) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'Add at least one sprint with a start and end date.');
            }

            $boardName = 'Unknown Board';
            try {
                $board = $this->trelloService->getBoard($boardId);
                $boardName = $board['name'] ?? $boardName;
            } catch (\Exception $e) {
                // ignore
            }

            $coverageMode = (string) $request->input('date_coverage', 'month');
            $coverageMeta = $this->resolveKpiDateCoverage($request, $coverageMode);

            $monthLabel = $coverageMeta['period_label'];
            $reportMonthStored = $coverageMeta['report_month'];

            $sprintsOut = [];
            foreach ($sprintInputs as $i => $row) {
                $label = trim((string) ($row['label'] ?? '')) ?: 'Sprint ' . ($i + 1);
                $from = $row['date_from'];
                $to = $row['date_to'];
                $sprint = $this->individualKpiReportService->buildSprintKpi(
                    $boardId,
                    $label,
                    $from,
                    $to,
                    $assignees,
                    $requiredByMember,
                    $qualityByMember,
                    $collabByMember
                );
                $sprintsOut[] = $this->individualKpiReportService->applyProfessionalismToSprint($sprint, $professionalismByMember);
            }

            $document = [
                'report_type' => 'individual_kpi',
                'date_coverage' => $coverageMeta,
                'report_month' => $reportMonthStored,
                'month_label' => $monthLabel,
                'board_id' => $boardId,
                'board_name' => $boardName,
                'assignees_filter' => $assignees,
                'required_points' => $requiredByMember,
                'quality_percent' => $qualityByMember,
                'collaboration_percent' => $collabByMember,
                'professionalism' => [
                    'members' => $professionalismByMember,
                    'dimension_labels' => ProfessionalismKpiReference::dimensionShortLabels(),
                ],
                'severity_multipliers' => [
                    'P1' => 1.3,
                    'P2' => 1.2,
                    'P3' => 1.1,
                    'P4' => 1.0,
                ],
                'sprints' => $sprintsOut,
            ];

            $boardReport = BoardReport::create([
                'user_id' => auth()->id(),
                'board_id' => $boardId,
                'board_name' => $boardName,
                'report_data' => $document,
                'generated_at' => now(),
            ]);

            return view('trello.kpi-report', [
                'document' => $document,
                'boardReport' => $boardReport,
            ]);
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to generate KPI report: ' . $e->getMessage());
        }
    }

    /**
     * Download Individual KPI report as HTML (same Blade as the on-screen report; best fidelity vs Word).
     */
    public function exportIndividualKpiHtml(BoardReport $boardReport): \Illuminate\Http\Response
    {
        if ((int) $boardReport->user_id !== (int) auth()->id()) {
            abort(403);
        }
        if (($boardReport->report_data['report_type'] ?? '') !== 'individual_kpi') {
            abort(404);
        }

        $document = $boardReport->report_data;
        $slug = Str::slug($boardReport->board_name ?: 'kpi', '_');
        $filename = 'individual_kpi_' . $slug . '_' . $boardReport->generated_at->format('Y-m-d_His') . '.html';

        return response()
            ->view('trello.kpi-report', [
                'document' => $document,
                'boardReport' => $boardReport,
                'standaloneExport' => true,
            ], 200)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Download Individual KPI report as .docx (PHPWord; layout may differ from the browser).
     */
    public function exportIndividualKpiDocx(BoardReport $boardReport): StreamedResponse
    {
        if ((int) $boardReport->user_id !== (int) auth()->id()) {
            abort(403);
        }
        if (($boardReport->report_data['report_type'] ?? '') !== 'individual_kpi') {
            abort(404);
        }

        $document = $boardReport->report_data;
        $slug = Str::slug($boardReport->board_name ?: 'kpi', '_');
        $filename = 'individual_kpi_' . $slug . '_' . $boardReport->generated_at->format('Y-m-d_His') . '.docx';

        return response()->streamDownload(function () use ($document, $boardReport) {
            $this->individualKpiDocxExportService->writeDocxToOutput(
                $document,
                $boardReport->generated_at
            );
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    /**
     * Build period label and calendar range for KPI date coverage (month or Q1–Q4).
     *
     * @return array{mode: string, year: int|null, quarter: int|null, date_from: string, date_to: string, period_label: string, report_month: string}
     */
    protected function resolveKpiDateCoverage(Request $request, string $mode): array
    {
        if ($mode === 'month') {
            $month = Carbon::createFromFormat('Y-m', $request->input('report_month'))->startOfMonth();
            $from = $month->copy()->startOfMonth()->format('Y-m-d');
            $to = $month->copy()->endOfMonth()->format('Y-m-d');

            return [
                'mode' => 'month',
                'year' => (int) $month->format('Y'),
                'quarter' => null,
                'date_from' => $from,
                'date_to' => $to,
                'period_label' => $month->format('F Y'),
                'report_month' => $month->format('Y-m'),
            ];
        }

        $year = (int) $request->input('coverage_year', now()->year);
        $q = (int) str_replace('q', '', strtolower($mode));
        if ($q < 1 || $q > 4) {
            $q = 1;
        }

        $startMonth = ($q - 1) * 3 + 1;
        $start = Carbon::create($year, $startMonth, 1)->startOfDay();
        $end = $start->copy()->addMonths(2)->endOfMonth();

        return [
            'mode' => 'q' . $q,
            'year' => $year,
            'quarter' => $q,
            'date_from' => $start->format('Y-m-d'),
            'date_to' => $end->format('Y-m-d'),
            'period_label' => sprintf(
                'Quarter %d · %d (%s – %s)',
                $q,
                $year,
                $start->format('M j'),
                $end->format('M j, Y')
            ),
            'report_month' => $start->format('Y-m'),
        ];
    }
}
