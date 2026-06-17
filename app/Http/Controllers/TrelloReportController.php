<?php

namespace App\Http\Controllers;

use App\Models\BoardExpectationThreshold;
use App\Models\BoardReport;
use App\Models\BoardTeamGroup;
use App\Support\PerformanceStandards;
use App\Support\ProfessionalismKpiReference;
use App\Support\TeamAccomplishmentLists;
use App\Services\AccountabilityDocxExportService;
use App\Services\AccountabilityReportService;
use App\Services\IndividualKpiDocxExportService;
use App\Services\IndividualKpiReportService;
use App\Services\TeamPerformanceService;
use App\Services\TrelloService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrelloReportController extends Controller
{
    protected $trelloService;

    protected $accountabilityReportService;

    protected $individualKpiReportService;

    protected $individualKpiDocxExportService;

    protected $accountabilityDocxExportService;

    protected $teamPerformanceService;

    public function __construct(
        TrelloService $trelloService,
        AccountabilityReportService $accountabilityReportService,
        IndividualKpiReportService $individualKpiReportService,
        IndividualKpiDocxExportService $individualKpiDocxExportService,
        AccountabilityDocxExportService $accountabilityDocxExportService,
        TeamPerformanceService $teamPerformanceService
    )
    {
        $this->trelloService = $trelloService;
        $this->accountabilityReportService = $accountabilityReportService;
        $this->individualKpiReportService = $individualKpiReportService;
        $this->individualKpiDocxExportService = $individualKpiDocxExportService;
        $this->accountabilityDocxExportService = $accountabilityDocxExportService;
        $this->teamPerformanceService = $teamPerformanceService;
    }

    /**
     * @return string[]
     */
    protected function savedTeamMemberIds(string $boardId): array
    {
        return BoardTeamGroup::memberIdsFor((int) auth()->id(), $boardId);
    }

    /**
     * Display a listing of all Trello boards.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        try {
            $allBoards = $this->trelloService->getBoards();
            $user = auth()->user();
            $boards = $user->filterVisibleBoards($allBoards);
            $totalBoardCount = count($allBoards);
            $isFiltered = $user->hasBoardFilter();

            return view('trello.boards', compact('boards', 'totalBoardCount', 'isFiltered'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to fetch boards: ' . $e->getMessage());
        }
    }

    /**
     * Choose which Trello boards appear on the boards page.
     */
    public function showBoardSettings()
    {
        try {
            $allBoards = $this->trelloService->getBoards();
            $user = auth()->user();
            $selectedIds = $user->visible_board_ids;
            if ($selectedIds === null) {
                $selectedIds = array_column($allBoards, 'id');
            }
            $showAll = !$user->hasBoardFilter();

            return view('trello.board-settings', [
                'allBoards' => $allBoards,
                'selectedIds' => $selectedIds,
                'showAll' => $showAll,
            ]);
        } catch (\Exception $e) {
            return redirect()->route('trello.boards')->with('error', 'Failed to load boards: ' . $e->getMessage());
        }
    }

    /**
     * Save visible board selection for the authenticated user.
     */
    public function saveBoardSettings(Request $request)
    {
        $request->validate([
            'show_all' => 'nullable|boolean',
            'board_ids' => 'nullable|array',
            'board_ids.*' => 'string|max:64',
        ]);

        if ($request->boolean('show_all')) {
            auth()->user()->update(['visible_board_ids' => null]);

            return redirect()->route('trello.boards')->with('success', 'Showing all Trello boards.');
        }

        $ids = array_values(array_unique(array_filter((array) $request->input('board_ids', []))));
        if ($ids === []) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Select at least one board, or check “Show all boards”.');
        }

        auth()->user()->update(['visible_board_ids' => $ids]);

        return redirect()->route('trello.boards')->with('success', 'Board selection saved.');
    }

    /**
     * Board hub: reports, recent history, and shortcuts.
     */
    public function showBoardDashboard(string $boardId)
    {
        try {
            $board = $this->trelloService->getBoard($boardId);
            $boardName = $board['name'] ?? 'Board';
            $trelloUrl = $board['url'] ?? $board['shortUrl'] ?? null;

            $recentReports = collect();
            if (Schema::hasColumn('board_reports', 'report_type')) {
                $ids = BoardReport::query()
                    ->where('user_id', auth()->id())
                    ->where('board_id', $boardId)
                    ->orderByDesc('generated_at')
                    ->limit(10)
                    ->pluck('id');

                if ($ids->isNotEmpty()) {
                    $recentReports = BoardReport::query()
                        ->whereIn('id', $ids)
                        ->select(['id', 'board_id', 'board_name', 'report_type', 'generated_at'])
                        ->orderByDesc('generated_at')
                        ->get();
                }
            }

            $user = auth()->user();
            $isVisibleOnBoardsPage = !$user->hasBoardFilter()
                || in_array($boardId, $user->visible_board_ids ?? [], true);
            $performanceStandards = BoardExpectationThreshold::currentStandardsFor((int) $user->id, $boardId);
            $teamMemberIds = $this->savedTeamMemberIds($boardId);

            return view('trello.board-dashboard', [
                'board' => $board,
                'boardId' => $boardId,
                'boardName' => $boardName,
                'recentReports' => $recentReports,
                'trelloUrl' => $trelloUrl,
                'isVisibleOnBoardsPage' => $isVisibleOnBoardsPage,
                'performanceStandards' => $performanceStandards,
                'teamMemberIds' => $teamMemberIds,
            ]);
        } catch (\Exception $e) {
            return redirect()->route('trello.boards')->with('error', 'Failed to load board: ' . $e->getMessage());
        }
    }

    /**
     * Save team members for this board (used across all reports).
     */
    public function saveTeamGroup(Request $request, string $boardId)
    {
        $request->validate([
            'member_ids' => 'required|array|min:1',
            'member_ids.*' => 'string|max:64',
        ]);

        $ids = array_values(array_unique(array_filter((array) $request->input('member_ids', []))));
        BoardTeamGroup::saveFor((int) auth()->id(), $boardId, $ids);

        return redirect()
            ->route('trello.team', ['boardId' => $boardId])
            ->with('success', count($ids) . ' team member(s) saved for this board.');
    }

    /**
     * Team group management + performance monitoring.
     */
    public function showTeamPage(Request $request, string $boardId)
    {
        try {
            $board = $this->trelloService->getBoard($boardId);
            $members = $this->trelloService->getBoardMembers($boardId);
            $userId = (int) auth()->id();
            $teamMemberIds = $this->savedTeamMemberIds($boardId);
            $standards = BoardExpectationThreshold::currentStandardsFor($userId, $boardId);
            $boardName = $board['name'] ?? 'Board';

            $savedPrefs = BoardTeamGroup::performancePrefsFor($userId, $boardId);
            $mode = $request->input('mode', $savedPrefs['mode']);
            $filterMonth = $request->input('month', $savedPrefs['month']);
            $filterYear = (int) $request->input('year', $savedPrefs['year']);
            $filterDateFrom = (string) $request->input('date_from', $savedPrefs['sprint_date_from']);
            $filterDateTo = (string) $request->input('date_to', $savedPrefs['sprint_date_to']);
            $filterSprintLabel = (string) $request->input('sprint_label', $savedPrefs['sprint_label']);

            if ($request->boolean('apply_filters')) {
                BoardTeamGroup::savePerformancePrefs($userId, $boardId, [
                    'mode' => $mode,
                    'month' => $filterMonth,
                    'year' => $filterYear,
                    'sprint_label' => $filterSprintLabel,
                    'sprint_date_from' => $filterDateFrom,
                    'sprint_date_to' => $filterDateTo,
                ]);
            } elseif ($request->has('mode') && $mode !== $savedPrefs['mode']) {
                BoardTeamGroup::savePerformancePrefs($userId, $boardId, array_merge($savedPrefs, [
                    'mode' => $mode,
                ]));
            }

            $performance = null;
            $yearData = null;
            $performanceError = null;

            if ($teamMemberIds !== []) {
                try {
                    if ($mode === 'year') {
                        $yearData = $this->teamPerformanceService->buildYearlyView(
                            $boardId,
                            $teamMemberIds,
                            $filterYear,
                            $userId
                        );
                    } elseif ($mode === 'sprint') {
                        if ($filterDateFrom !== '' && $filterDateTo !== '') {
                            $performance = $this->teamPerformanceService->buildSprintView(
                                $boardId,
                                $teamMemberIds,
                                $filterDateFrom,
                                $filterDateTo,
                                $userId,
                                $filterSprintLabel
                            );
                        }
                    } else {
                        $performance = $this->teamPerformanceService->buildMonthlyView(
                            $boardId,
                            $teamMemberIds,
                            $filterMonth,
                            $userId
                        );
                    }
                } catch (\Exception $e) {
                    $performanceError = $e->getMessage();
                }
            }

            if ($request->boolean('apply_filters')) {
                $hasReportData = $performance !== null || $yearData !== null;
                if ($request->boolean('save_report') && $hasReportData) {
                    $document = $this->buildTeamPerformanceDocument(
                        $boardId,
                        $boardName,
                        $mode,
                        $teamMemberIds,
                        $performance,
                        $yearData,
                        $standards,
                        $filterMonth,
                        $filterYear,
                        $filterDateFrom,
                        $filterDateTo,
                        $filterSprintLabel
                    );
                    $boardReport = BoardReport::create([
                        'user_id' => $userId,
                        'board_id' => $boardId,
                        'board_name' => $boardName,
                        'report_type' => 'team_performance',
                        'report_data' => $document,
                        'generated_at' => now(),
                    ]);

                    return redirect()
                        ->route('trello.team.show', $boardReport)
                        ->with('success', 'Team performance report saved to your library.');
                }

                if ($request->boolean('save_report') && !$hasReportData) {
                    session()->flash('error', 'Nothing to save — choose a period with chart data first (set sprint dates if using Sprint / custom).');
                } else {
                    session()->flash('success', 'Performance filters saved for this board.');
                }
            }

            return view('trello.team', [
                'boardId' => $boardId,
                'boardName' => $boardName,
                'members' => $members,
                'teamMemberIds' => $teamMemberIds,
                'standards' => $standards,
                'mode' => $mode,
                'performance' => $performance,
                'yearData' => $yearData,
                'performanceError' => $performanceError,
                'filterMonth' => $filterMonth,
                'filterYear' => $filterYear,
                'filterDateFrom' => $filterDateFrom,
                'filterDateTo' => $filterDateTo,
                'filterSprintLabel' => $filterSprintLabel,
            ]);
        } catch (\Exception $e) {
            return redirect()
                ->route('trello.board.dashboard', $boardId)
                ->with('error', 'Failed to load team page: ' . $e->getMessage());
        }
    }

    /**
     * View a saved team performance report snapshot.
     */
    public function showTeamPerformanceReport(BoardReport $boardReport)
    {
        $this->authorizeBoardReport($boardReport, 'team_performance');
        $document = $boardReport->report_data;
        if (($document['report_type'] ?? '') !== 'team_performance') {
            abort(404);
        }

        return view('trello.team-performance-report', [
            'boardReport' => $boardReport,
            'document' => $document,
        ]);
    }

    /**
     * @param array<string, mixed>|null $performance
     * @param array<string, mixed>|null $yearData
     */
    protected function buildTeamPerformanceDocument(
        string $boardId,
        string $boardName,
        string $mode,
        array $teamMemberIds,
        ?array $performance,
        ?array $yearData,
        \App\Support\PerformanceStandards $standards,
        string $filterMonth,
        int $filterYear,
        string $filterDateFrom,
        string $filterDateTo,
        string $filterSprintLabel
    ): array {
        $periodLabel = match ($mode) {
            'year' => (string) $filterYear,
            'sprint' => $filterSprintLabel !== ''
                ? $filterSprintLabel
                : trim($filterDateFrom . ' — ' . $filterDateTo),
            default => $filterMonth,
        };

        return [
            'report_type' => 'team_performance',
            'board_id' => $boardId,
            'board_name' => $boardName,
            'mode' => $mode,
            'period_label' => $periodLabel,
            'filter_month' => $filterMonth,
            'filter_year' => $filterYear,
            'filter_date_from' => $filterDateFrom,
            'filter_date_to' => $filterDateTo,
            'filter_sprint_label' => $filterSprintLabel,
            'team_member_ids' => $teamMemberIds,
            'performance' => $performance,
            'year_data' => $yearData,
            'performance_standards' => $standards->toArray(),
            'standards_summary' => $standards->summaryLine(),
        ];
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
                'defaultAssignees' => $this->savedTeamMemberIds($boardId),
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

            $boardReport = $this->persistBoardReportIfRequested($request, [
                'board_id' => $boardId,
                'board_name' => $boardName,
                'report_type' => 'board',
                'report_data' => $reportData,
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
    /**
     * List saved reports for the authenticated user.
     */
    public function indexSavedReports()
    {
        $reports = collect();
        if (Schema::hasColumn('board_reports', 'report_type')) {
            $ids = BoardReport::query()
                ->where('user_id', auth()->id())
                ->orderByDesc('generated_at')
                ->limit(50)
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                $reports = BoardReport::query()
                    ->whereIn('id', $ids)
                    ->select(['id', 'board_id', 'board_name', 'report_type', 'generated_at'])
                    ->orderByDesc('generated_at')
                    ->get();
            }
        }

        return view('trello.saved-reports', compact('reports'));
    }

    /**
     * View a saved board report in the browser.
     */
    public function showBoardReport(BoardReport $boardReport)
    {
        $this->authorizeBoardReport($boardReport, 'board');

        return view('trello.report', [
            'report' => $boardReport->report_data,
            'boardReport' => $boardReport,
        ]);
    }

    /**
     * Export a specific saved board report as CSV.
     */
    public function exportBoardReportCsv(BoardReport $boardReport)
    {
        $this->authorizeBoardReport($boardReport, 'board');

        return $this->csvResponseForBoardReport($boardReport);
    }

    /**
     * Export the latest saved board report for a board (legacy route).
     */
    public function export($boardId)
    {
        try {
            $boardReport = BoardReport::query()
                ->where('board_id', $boardId)
                ->where('user_id', auth()->id())
                ->where('report_type', 'board')
                ->latest('generated_at')
                ->first();

            if (!$boardReport) {
                return redirect()
                    ->route('trello.report.filter', $boardId)
                    ->with('error', 'No saved board report found. Generate and save a report first.');
            }

            return $this->csvResponseForBoardReport($boardReport);
        } catch (\Exception $e) {
            return redirect()->route('trello.boards')->with('error', 'Failed to export report: ' . $e->getMessage());
        }
    }

    /**
     * View a saved accountability report in the browser.
     */
    public function showAccountabilityReport(BoardReport $boardReport)
    {
        $this->authorizeBoardReport($boardReport, 'accountability');

        return view('trello.accountability-report', [
            'document' => $boardReport->report_data,
            'boardReport' => $boardReport,
        ]);
    }

    /**
     * View a saved Individual KPI report in the browser.
     */
    public function showKpiReport(BoardReport $boardReport)
    {
        $this->authorizeBoardReport($boardReport, 'individual_kpi');

        return view('trello.kpi-report', [
            'document' => $boardReport->report_data,
            'boardReport' => $boardReport,
        ]);
    }

    /**
     * Form for Team Accountability Report (sprints + narrative + Trello-backed metrics).
     */
    public function showAccountabilityForm(string $boardId)
    {
        try {
            $board = $this->trelloService->getBoard($boardId);
            $members = $this->trelloService->getBoardMembers($boardId);
            $userId = (int) auth()->id();
            $currentStandards = BoardExpectationThreshold::currentStandardsFor($userId, $boardId);
            $thresholdHistory = BoardExpectationThreshold::historyFor($userId, $boardId);

            return view('trello.accountability-form', [
                'boardId' => $boardId,
                'boardName' => $board['name'] ?? 'Unknown Board',
                'members' => $members,
                'currentStandards' => $currentStandards,
                'thresholdHistory' => $thresholdHistory,
                'defaultAssignees' => $this->savedTeamMemberIds($boardId),
            ]);
        } catch (\Exception $e) {
            return redirect()->route('trello.boards')->with('error', 'Failed to load board: ' . $e->getMessage());
        }
    }

    /**
     * Manually save expectation threshold for a board (append-only history).
     */
    public function saveExpectationThreshold(Request $request, string $boardId)
    {
        $request->validate(PerformanceStandards::validationRules('standards'));

        $standards = PerformanceStandards::fromArray((array) $request->input('standards', []));
        $record = BoardExpectationThreshold::recordIfChanged((int) auth()->id(), $boardId, $standards);

        $message = $record !== null
            ? 'Performance standards saved. ' . $standards->summaryLine()
            : 'Standards unchanged. ' . $standards->summaryLine();

        return redirect()
            ->route('trello.accountability.form', $boardId)
            ->with($record !== null ? 'success' : 'info', $message);
    }

    /**
     * Generate accountability document from sprints, optional narrative fields, and Trello data.
     */
    public function generateAccountabilityReport(Request $request, string $boardId)
    {
        $request->validate(array_merge([
            'report_month' => 'required|date_format:Y-m',
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
        ], PerformanceStandards::validationRules('standards')));

        $standards = $request->has('standards')
            ? PerformanceStandards::fromArray((array) $request->input('standards', []))
            : BoardExpectationThreshold::currentStandardsFor((int) auth()->id(), $boardId);

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
                    $standards
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
                $challengesText = $this->accountabilityReportService->suggestChallenges($sprintsOut, $standards, $narrativeReport);
            }

            $plansText = trim((string) $request->input('plans_next_steps', ''));
            if ($plansText === '') {
                $plansText = $this->accountabilityReportService->suggestPlansNextSteps($narrativeReport);
            }

            $contextText = trim((string) $request->input('context_interpretation', ''));
            if ($contextText === '') {
                $contextText = $this->accountabilityReportService->suggestContextInterpretation($sprintsOut, $standards, $monthLabel);
            }

            $outlookText = trim((string) $request->input('overall_outlook', ''));
            if ($outlookText === '') {
                $outlookText = $this->accountabilityReportService->suggestOverallOutlook($sprintsOut, $narrativeReport, $standards);
            }

            $document = [
                'report_type' => 'accountability',
                'report_month' => $request->input('report_month'),
                'month_label' => $monthLabel,
                'board_id' => $boardId,
                'board_name' => $boardName,
                'expectation_threshold' => $standards->baselineFloor(),
                'performance_standards' => $standards->toArray(),
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

            $boardReport = $this->persistBoardReportIfRequested($request, [
                'board_id' => $boardId,
                'board_name' => $boardName,
                'report_type' => 'accountability',
                'report_data' => $document,
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
                'defaultAssignees' => $this->savedTeamMemberIds($boardId),
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

            $boardReport = $this->persistBoardReportIfRequested($request, [
                'board_id' => $boardId,
                'board_name' => $boardName,
                'report_type' => 'individual_kpi',
                'report_data' => $document,
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
    /**
     * Save report when the user checked "Save to my library" on the form.
     */
    protected function persistBoardReportIfRequested(Request $request, array $attributes): BoardReport
    {
        $report = new BoardReport(array_merge($attributes, [
            'user_id' => auth()->id(),
            'generated_at' => now(),
        ]));

        if ($request->has('save_report')) {
            $report->save();
        }

        return $report;
    }

    protected function authorizeBoardReport(BoardReport $boardReport, ?string $expectedType = null): void
    {
        if (!$boardReport->exists || (int) $boardReport->user_id !== (int) auth()->id()) {
            abort(403);
        }

        if ($expectedType !== null && $boardReport->resolvedType() !== $expectedType) {
            abort(404);
        }
    }

    /**
     * @return \Illuminate\Http\Response
     */
    protected function csvResponseForBoardReport(BoardReport $boardReport)
    {
        $reportData = $boardReport->report_data;
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
            foreach ($reportData['member_stats'] as $stats) {
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
        foreach ($reportData['cards_by_list'] as $listData) {
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
            $explicitCompletedLists = array_merge([
                'for dev deployment/review (tiger/jan review)',
                'for dev deployment/review',
                'on dev environment',
                'on staging / demo to po',
                'on live',
                'done / archive',
                'done/archive',
                'done/archived',
                'archive done',
            ], TeamAccomplishmentLists::completedSprintNormalizedNames());
            $explicitInProgressLists = ['in dev'];
            $explicitTodoLists = ['current sprint'];

            if (in_array($normalizedListName, $explicitCompletedLists, true) ||
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
            $dueDate = $card['due_date'] ? Carbon::parse($card['due_date'])->format('Y-m-d') : '-';
            if ($card['due_complete'] ?? false) {
                $dueDate .= ' (Completed)';
            }
            $dateCompleted = !empty($card['date_completed'])
                ? Carbon::parse($card['date_completed'])->format('Y-m-d')
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

        $output = fopen('php://temp', 'r+');
        foreach ($csvData as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        $slug = Str::slug($boardReport->board_name ?: 'board', '_');
        $filename = 'board_report_' . $slug . '_' . $boardReport->generated_at->format('Y-m-d_His') . '.csv';

        return Response::make($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

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
