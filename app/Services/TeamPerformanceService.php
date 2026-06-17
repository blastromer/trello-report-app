<?php

namespace App\Services;

use App\Models\BoardExpectationThreshold;
use App\Support\PerformanceStandards;
use App\Support\TeamAccomplishmentLists;
use Carbon\Carbon;

class TeamPerformanceService
{
    protected TrelloService $trelloService;

    protected AccountabilityReportService $accountabilityReportService;

    public function __construct(TrelloService $trelloService, AccountabilityReportService $accountabilityReportService)
    {
        $this->trelloService = $trelloService;
        $this->accountabilityReportService = $accountabilityReportService;
    }

    /**
     * @param string[] $memberIds
     * @return array<string, mixed>
     */
    public function snapshotForPeriod(
        string $boardId,
        array $memberIds,
        string $dateFrom,
        string $dateTo,
        int $userId
    ): array {
        $listMatch = TeamAccomplishmentLists::resolveFromBoardLists(
            $this->trelloService->getBoardLists($boardId)
        );
        $accomplishmentListIds = $listMatch['ids'];

        $report = ['cards' => [], 'total_cards' => 0, 'total_points' => 0, 'status_breakdown' => []];
        $membersOut = [];
        $overall = null;
        $overallTier = 'No measurable points in this sprint window';

        if ($accomplishmentListIds !== []) {
            $report = $this->trelloService->generateBoardReport($boardId, [
                'assignees' => $memberIds,
                'lists' => $accomplishmentListIds,
            ]);

            $report = $this->narrowReportToPeriod($report, $dateFrom, $dateTo);

            $byMember = $this->accountabilityReportService->computeMemberAccomplishment($report, $memberIds);
            $members = $this->accountabilityReportService->membersOnlyForTeam($byMember, $memberIds, $report);
            $standards = BoardExpectationThreshold::currentStandardsFor($userId, $boardId);

            foreach ($members as $row) {
                $rate = $row['accomplishment_rate'] ?? null;
                $memberCards = $this->memberCardsInReport($report, (string) $row['member_id']);
                $membersOut[] = array_merge($row, [
                    'tier_label' => $standards->labelForPercent($rate),
                    'cards' => $memberCards,
                    'card_summary' => $this->summarizeMemberCards($memberCards),
                ]);
            }

            $overall = $this->accountabilityReportService->overallSprintAccomplishmentPercent($members);
            $overallTier = $standards->labelForPercent($overall);
        }

        // WIP on other lists (outside accomplishment scope).
        $currentReport = $this->trelloService->generateBoardReport($boardId, [
            'assignees' => $memberIds,
            'lists' => [],
        ]);
        $currentPipelineByMember = $this->accountabilityReportService->computeMemberPipelinePoints(
            $currentReport,
            $memberIds
        );
        $this->stripAccomplishmentListCards($currentPipelineByMember);
        $currentPipelineMembers = $this->accountabilityReportService->membersOnlyForTeamPipeline(
            $currentPipelineByMember,
            $memberIds
        );

        return [
            'label' => $dateFrom . ' — ' . $dateTo,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'members' => $membersOut,
            'overall_percent' => $overall,
            'overall_tier_label' => $overallTier,
            'total_cards' => (int) ($report['total_cards'] ?? 0),
            'total_points' => (float) ($report['total_points'] ?? 0),
            'status_breakdown' => $report['status_breakdown'] ?? [],
            'accomplishment_lists' => $listMatch['labels'],
            'accomplishment_lists_missing' => $listMatch['missing'],
            'current_pipeline_members' => $currentPipelineMembers,
            'current_pipeline_points_total' => array_sum(array_column($currentPipelineMembers, 'points')),
        ];
    }

    /**
     * @param array<string, array> $pipelineByMember
     */
    protected function stripAccomplishmentListCards(array &$pipelineByMember): void
    {
        $allowed = array_flip(TeamAccomplishmentLists::normalizedNames());

        foreach ($pipelineByMember as &$row) {
            $cards = [];
            $points = 0.0;
            foreach ($row['cards'] ?? [] as $card) {
                $listName = strtolower(trim((string) ($card['list_name'] ?? '')));
                if (isset($allowed[$listName])) {
                    continue;
                }
                $cards[] = $card;
                $points += (float) ($card['points'] ?? 0);
            }
            $row['cards'] = $cards;
            $row['card_count'] = count($cards);
            $row['points'] = $points;
        }
        unset($row);
    }

    /**
     * Keep only cards relevant to the selected period within accomplishment lists.
     *
     * Done Sprint / Temp for Checking and Review: Date Completed must fall in range (excludes old backlog in those columns).
     * In Development / Blocked: last activity (or due / completed date) in range.
     *
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    protected function narrowReportToPeriod(array $report, string $dateFrom, string $dateTo): array
    {
        $fromDay = substr($dateFrom, 0, 10);
        $toDay = substr($dateTo, 0, 10);
        $fromTs = strtotime($fromDay . ' 00:00:00');
        $toTs = strtotime($toDay . ' 23:59:59');

        $filtered = array_values(array_filter(
            $report['cards'] ?? [],
            fn (array $card) => $this->cardCountsInAccomplishmentPeriod($card, $fromDay, $toDay, $fromTs, $toTs)
        ));

        $report['cards'] = $filtered;
        $report['total_cards'] = count($filtered);
        $report['total_points'] = (float) array_sum(array_column($filtered, 'points'));

        return $report;
    }

    protected function cardCountsInAccomplishmentPeriod(
        array $card,
        string $fromDay,
        string $toDay,
        int $fromTs,
        int $toTs
    ): bool {
        $status = (string) ($card['status_key'] ?? 'other');

        if ($status === 'completed') {
            $completedDay = (string) ($card['date_completed'] ?? '');
            if ($completedDay === '') {
                return false;
            }

            return $completedDay >= $fromDay && $completedDay <= $toDay;
        }

        foreach ($this->cardActivityTimestamps($card) as $ts) {
            if ($ts >= $fromTs && $ts <= $toTs) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return int[]
     */
    protected function cardActivityTimestamps(array $card): array
    {
        $timestamps = [];

        if (!empty($card['date_last_activity'])) {
            $t = strtotime((string) $card['date_last_activity']);
            if ($t) {
                $timestamps[] = $t;
            }
        }
        if (!empty($card['date_completed'])) {
            $t = strtotime((string) $card['date_completed'] . ' 12:00:00');
            if ($t) {
                $timestamps[] = $t;
            }
        }
        if (!empty($card['due_date'])) {
            $t = strtotime((string) $card['due_date']);
            if ($t) {
                $timestamps[] = $t;
            }
        }
        if (!empty($card['created_date'])) {
            $t = strtotime((string) $card['created_date']);
            if ($t) {
                $timestamps[] = $t;
            }
        }

        return array_values(array_unique(array_filter($timestamps)));
    }

    /**
     * @param array<int, array{name: string, list_name: string, points: float, status_key: string}> $cards
     * @return array{count: int, by_list: array<string, int>, preview: array<int, array>, remaining: int}
     */
    protected function summarizeMemberCards(array $cards): array
    {
        $byList = [];
        foreach ($cards as $card) {
            $list = (string) ($card['list_name'] ?? 'Unknown');
            $byList[$list] = ($byList[$list] ?? 0) + 1;
        }

        $previewLimit = 8;

        return [
            'count' => count($cards),
            'by_list' => $byList,
            'preview' => array_slice($cards, 0, $previewLimit),
            'remaining' => max(0, count($cards) - $previewLimit),
        ];
    }

    /**
     * @return array<int, array{name: string, list_name: string, points: float, status_key: string}>
     */
    protected function memberCardsInReport(array $reportData, string $memberId): array
    {
        $out = [];
        foreach ($reportData['cards'] ?? [] as $card) {
            if (!in_array($memberId, $card['member_ids'] ?? [], true)) {
                continue;
            }
            $out[] = [
                'name' => (string) ($card['name'] ?? 'Untitled'),
                'list_name' => (string) ($card['list_name'] ?? ''),
                'points' => (float) ($card['points'] ?? 0),
                'status_key' => (string) ($card['status_key'] ?? 'other'),
            ];
        }

        return $out;
    }

    /**
     * @param string[] $memberIds
     * @return array<string, mixed>
     */
    public function buildMonthlyView(string $boardId, array $memberIds, string $monthYm, int $userId): array
    {
        $month = Carbon::createFromFormat('Y-m', $monthYm)->startOfMonth();

        return $this->snapshotForPeriod(
            $boardId,
            $memberIds,
            $month->format('Y-m-d'),
            $month->copy()->endOfMonth()->format('Y-m-d'),
            $userId
        );
    }

    /**
     * @param string[] $memberIds
     * @return array<string, mixed>
     */
    public function buildYearlyView(string $boardId, array $memberIds, int $year, int $userId): array
    {
        $periods = [];
        $memberTrends = [];

        foreach ($memberIds as $mid) {
            $memberTrends[$mid] = [
                'member_id' => $mid,
                'name' => $mid,
                'monthly_rates' => [],
            ];
        }

        for ($m = 1; $m <= 12; $m++) {
            $start = Carbon::create($year, $m, 1);
            $snap = $this->snapshotForPeriod(
                $boardId,
                $memberIds,
                $start->format('Y-m-d'),
                $start->copy()->endOfMonth()->format('Y-m-d'),
                $userId
            );
            $periods[] = [
                'label' => $start->format('M'),
                'month' => $start->format('Y-m'),
                'overall_percent' => $snap['overall_percent'],
                'members' => $snap['members'],
            ];
            foreach ($snap['members'] as $row) {
                $mid = $row['member_id'];
                if (isset($memberTrends[$mid])) {
                    $memberTrends[$mid]['name'] = $row['name'];
                    $memberTrends[$mid]['monthly_rates'][] = $row['accomplishment_rate'];
                }
            }
        }

        return [
            'year' => $year,
            'periods' => $periods,
            'member_trends' => array_values($memberTrends),
        ];
    }

    /**
     * @param string[] $memberIds
     * @return array<string, mixed>
     */
    public function buildSprintView(
        string $boardId,
        array $memberIds,
        string $dateFrom,
        string $dateTo,
        int $userId,
        ?string $label = null
    ): array {
        $snap = $this->snapshotForPeriod($boardId, $memberIds, $dateFrom, $dateTo, $userId);
        if ($label !== null && $label !== '') {
            $snap['label'] = $label;
        }

        return $snap;
    }
}
