<?php

namespace App\Services;

/**
 * Builds accountability / sprint performance summaries from board report data.
 */
class AccountabilityReportService
{
    /** @var TrelloService */
    protected $trelloService;

    public function __construct(TrelloService $trelloService)
    {
        $this->trelloService = $trelloService;
    }

    /**
     * Per-member accomplishment: completed story points / total points on cards in scope.
     * Multi-assignee cards count full points per assignee only for ids in $onlyMemberIds (team on the report).
     *
     * @param array $reportData Output of generateBoardReport()
     * @param string[] $onlyMemberIds If non-empty, only these member ids accrue points from each card
     * @return array<string, array{member_id: string, name: string, points_completed: float, points_total: float, accomplishment_rate: float|null}>
     */
    public function computeMemberAccomplishment(array $reportData, array $onlyMemberIds = []): array
    {
        $byMember = [];
        $allowed = $onlyMemberIds !== [] ? array_flip($onlyMemberIds) : null;

        foreach ($reportData['cards'] ?? [] as $card) {
            $pts = (float) ($card['points'] ?? 0);
            $status = $card['status_key'] ?? 'other';
            $ids = $card['member_ids'] ?? [];
            if ($ids === []) {
                continue;
            }

            foreach ($ids as $mid) {
                if ($allowed !== null && !isset($allowed[$mid])) {
                    continue;
                }
                if (!isset($byMember[$mid])) {
                    $byMember[$mid] = [
                        'member_id' => $mid,
                        'name' => $this->memberName($reportData, $mid),
                        'points_completed' => 0.0,
                        'points_total' => 0.0,
                    ];
                }
                $byMember[$mid]['points_total'] += $pts;
                if ($status === 'completed') {
                    $byMember[$mid]['points_completed'] += $pts;
                }
            }
        }

        foreach ($byMember as $mid => &$row) {
            $total = $row['points_total'];
            $row['accomplishment_rate'] = $total > 0
                ? round(($row['points_completed'] / $total) * 100, 2)
                : null;
        }
        unset($row);

        return $byMember;
    }

    /**
     * Average accomplishment rate across team members with any points in scope.
     *
     * @param array<int, array> $memberRows Ordered rows (e.g. membersOnlyForTeam output)
     */
    public function overallSprintAccomplishmentPercent(array $memberRows): ?float
    {
        $rates = [];
        foreach ($memberRows as $row) {
            if (($row['points_total'] ?? 0) > 0 && ($row['accomplishment_rate'] ?? null) !== null) {
                $rates[] = $row['accomplishment_rate'];
            }
        }
        if ($rates === []) {
            return null;
        }

        return round(array_sum($rates) / count($rates), 2);
    }

    /**
     * Only selected team members appear, in checkbox order, with zeroed rows if no cards in window.
     *
     * @param array<string, array> $byMember Keyed by member id from computeMemberAccomplishment()
     * @param string[] $assigneeIds Selected member ids (preserve order)
     * @return array<int, array>
     */
    public function membersOnlyForTeam(array $byMember, array $assigneeIds, array $reportData): array
    {
        $out = [];
        foreach ($assigneeIds as $id) {
            if (isset($byMember[$id])) {
                $out[] = $byMember[$id];
            } else {
                $out[] = [
                    'member_id' => $id,
                    'name' => $this->memberName($reportData, $id),
                    'points_completed' => 0.0,
                    'points_total' => 0.0,
                    'accomplishment_rate' => null,
                ];
            }
        }

        return $out;
    }

    /**
     * Label for overall sprint vs expectation threshold.
     */
    public function expectationLabel(?float $overallPercent, float $threshold): string
    {
        if ($overallPercent === null) {
            return 'No measurable points in this sprint window';
        }
        if ($overallPercent < $threshold) {
            return 'Below Expectation';
        }
        if ($overallPercent > $threshold + 10) {
            return 'Above Expectation';
        }

        return 'Meets Expectation';
    }

    /**
     * Card titles for completed work (for narrative suggestions).
     *
     * @return string[]
     */
    public function completedCardTitles(array $reportData, int $limit = 50): array
    {
        $titles = [];
        foreach ($reportData['cards'] ?? [] as $card) {
            if (($card['status_key'] ?? '') !== 'completed') {
                continue;
            }
            $name = trim((string) ($card['name'] ?? ''));
            if ($name !== '') {
                $titles[] = $name;
            }
            if (count($titles) >= $limit) {
                break;
            }
        }

        return $titles;
    }

    /**
     * Card titles still in progress or todo.
     *
     * @return string[]
     */
    public function ongoingCardTitles(array $reportData, int $limit = 40): array
    {
        $titles = [];
        foreach ($reportData['cards'] ?? [] as $card) {
            $s = $card['status_key'] ?? '';
            if (!in_array($s, ['in_progress', 'todo'], true)) {
                continue;
            }
            $name = trim((string) ($card['name'] ?? ''));
            if ($name !== '') {
                $titles[] = $name;
            }
            if (count($titles) >= $limit) {
                break;
            }
        }

        return $titles;
    }

    /**
     * Run generateBoardReport with filters and return sprint block for the UI.
     *
     * @param string[] $assigneeIds Required non-empty: only these members appear in metrics and accrue points
     */
    public function buildSprintBlock(string $boardId, string $label, ?string $dateFrom, ?string $dateTo, array $assigneeIds): array
    {
        $filters = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'assignees' => $assigneeIds,
            'lists' => [],
            'date_completed_only' => !empty($dateFrom) || !empty($dateTo),
        ];

        $report = $this->trelloService->generateBoardReport($boardId, $filters);
        $byMember = $this->computeMemberAccomplishment($report, $assigneeIds);
        $membersOrdered = $this->membersOnlyForTeam($byMember, $assigneeIds, $report);
        $overall = $this->overallSprintAccomplishmentPercent($membersOrdered);

        return [
            'label' => $label,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'members' => $membersOrdered,
            'overall_accomplishment_percent' => $overall,
            'summary' => [
                'total_cards' => $report['total_cards'] ?? 0,
                'total_points' => $report['total_points'] ?? 0,
                'status_breakdown' => $report['status_breakdown'] ?? [],
            ],
        ];
    }

    /**
     * Challenges / risks inferred from sprint metrics and month status mix.
     *
     * @param array<int, array> $sprintsOut Blocks from buildSprintBlock + expectation_label
     */
    public function suggestChallenges(array $sprintsOut, float $threshold, array $monthReport): string
    {
        $lines = [];

        foreach ($sprintsOut as $s) {
            $pct = $s['overall_accomplishment_percent'] ?? null;
            if ($pct !== null && $pct < $threshold) {
                $lines[] = sprintf(
                    '%s: overall team accomplishment was %.2f%%, below the %.0f%% expectation.',
                    $s['label'],
                    $pct,
                    $threshold
                );
            }
            foreach ($s['members'] ?? [] as $m) {
                $total = (float) ($m['points_total'] ?? 0);
                $rate = $m['accomplishment_rate'] ?? null;
                if ($total > 0 && $rate !== null && $rate < $threshold) {
                    $lines[] = sprintf(
                        '%s — %s at %.2f%% accomplishment (story points in completed lists vs. total in that sprint window).',
                        $s['label'],
                        $m['name'],
                        $rate
                    );
                }
            }
        }

        $sb = $monthReport['status_breakdown'] ?? [];
        $inProg = (int) ($sb['in_progress'] ?? 0);
        $todo = (int) ($sb['todo'] ?? 0);
        $done = (int) ($sb['completed'] ?? 0);
        $other = (int) ($sb['other'] ?? 0);
        if ($inProg + $todo > 0) {
            $lines[] = sprintf(
                'Workload still active: %d card(s) in progress, %d in todo/backlog for the month window (filters applied).',
                $inProg,
                $todo
            );
        }
        if ($other > 0) {
            $lines[] = sprintf('%d card(s) are in lists classified as “other” — confirm pipeline list names match your done rules.', $other);
        }
        if ($done === 0 && ($inProg + $todo + $other) > 0) {
            $lines[] = 'No cards fell into “completed” lists for this month window; verify dates, assignees, or list naming if work was actually finished.';
        }

        if ($lines === []) {
            $lines[] = 'No automatic issues flagged versus the expectation threshold; edit this section with real-world blockers (dependencies, reviews, leave, etc.).';
        }

        return implode("\n", array_values(array_unique($lines)));
    }

    /**
     * Plans / next steps from in-progress and todo cards in the month report.
     */
    public function suggestPlansNextSteps(array $monthReport, int $inProgressLimit = 25, int $todoLimit = 20): string
    {
        $inProg = [];
        $todo = [];
        foreach ($monthReport['cards'] ?? [] as $card) {
            $s = $card['status_key'] ?? '';
            $name = trim((string) ($card['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            if ($s === 'in_progress') {
                $inProg[] = $name;
            } elseif ($s === 'todo') {
                $todo[] = $name;
            }
        }

        $lines = [];
        foreach (array_slice($inProg, 0, $inProgressLimit) as $t) {
            $lines[] = 'Advance / complete: ' . $t;
        }
        foreach (array_slice($todo, 0, $todoLimit) as $t) {
            $lines[] = 'Plan / pull into sprint: ' . $t;
        }

        if ($lines === []) {
            $lines[] = 'No in-progress or todo cards matched the month and assignee filters; define next sprint scope on the board or widen the date range.';
        }

        return implode("\n", $lines);
    }

    /**
     * Context tying sprint windows to expectation threshold (auto prose).
     *
     * @param array<int, array> $sprintsOut
     */
    public function suggestContextInterpretation(array $sprintsOut, float $threshold, string $monthLabel): string
    {
        $parts = [];
        $parts[] = 'This section is generated from Trello: accomplishment rate is story points on completed pipeline lists divided by total points on cards in each sprint window for the selected team members. Card windows use the Date Completed custom field only.';
        $parts[] = 'Reporting month for narrative card lists: ' . $monthLabel . '.';

        foreach ($sprintsOut as $s) {
            $pct = $s['overall_accomplishment_percent'] ?? null;
            $expect = $s['expectation_label'] ?? '';
            if ($pct !== null) {
                $parts[] = sprintf(
                    '%s (%s–%s): team-average accomplishment %.2f%% (%s).',
                    $s['label'],
                    $s['date_from'],
                    $s['date_to'],
                    $pct,
                    $expect
                );
            } else {
                $parts[] = sprintf(
                    '%s (%s–%s): not enough story-point data in scope to compute a team average (%s).',
                    $s['label'],
                    $s['date_from'],
                    $s['date_to'],
                    $expect
                );
            }
        }

        $parts[] = sprintf('Expectation threshold: %.0f%%.', $threshold);

        return implode(' ', $parts);
    }

    /**
     * Short overall outlook from sprint results + month completion counts.
     *
     * @param array<int, array> $sprintsOut
     */
    public function suggestOverallOutlook(array $sprintsOut, array $monthReport, float $threshold): string
    {
        $below = 0;
        $atOrAbove = 0;
        foreach ($sprintsOut as $s) {
            $pct = $s['overall_accomplishment_percent'] ?? null;
            if ($pct === null) {
                continue;
            }
            if ($pct < $threshold) {
                $below++;
            } else {
                $atOrAbove++;
            }
        }

        $sb = $monthReport['status_breakdown'] ?? [];
        $completed = (int) ($sb['completed'] ?? 0);
        $inProg = (int) ($sb['in_progress'] ?? 0);
        $todo = (int) ($sb['todo'] ?? 0);

        if ($atOrAbove > 0 && $below === 0) {
            return sprintf(
                'Sprint averages met the %.0f%% expectation across configured windows. In the reporting month, %d card(s) sat in completed lists; %d still in progress and %d in todo/backlog — use the Plans & next steps list to drive the next cycle.',
                $threshold,
                $completed,
                $inProg,
                $todo
            );
        }

        if ($below > 0) {
            return sprintf(
                'One or more sprints landed below the %.0f%% accomplishment expectation. Prioritize finishing in-progress work (%d cards in month scope), rightsizing points, and clearing blockers next cycle.',
                $threshold,
                $inProg
            );
        }

        return sprintf(
            'Insufficient sprint metric data to characterize trend automatically. Month scope: %d completed-list cards, %d in progress, %d todo — refine sprint dates or story points and regenerate.',
            $completed,
            $inProg,
            $todo
        );
    }

    protected function memberName(array $reportData, string $memberId): string
    {
        $stats = $reportData['member_stats'][$memberId] ?? null;
        if (is_array($stats) && !empty($stats['name'])) {
            return $stats['name'];
        }

        foreach ($reportData['members'] ?? [] as $m) {
            if (($m['id'] ?? '') === $memberId) {
                return $m['fullName'] ?? $m['username'] ?? 'Unknown';
            }
        }

        return 'Unknown';
    }
}
