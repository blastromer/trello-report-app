<?php

namespace App\Services;

use App\Support\ProfessionalismKpiReference;
use Illuminate\Http\Request;

class IndividualKpiReportService
{
    /** @var TrelloService */
    protected $trelloService;

    public function __construct(TrelloService $trelloService)
    {
        $this->trelloService = $trelloService;
    }

    /**
     * Build KPI rows for one sprint window.
     *
     * Productivity (30%): sum(completed points * severity_multiplier) / required_points * 30.
     * Efficiency (30%): completed points / required_points * 30.
     *
     * @param string $boardId
     * @param string $label
     * @param string $dateFrom Y-m-d
     * @param string $dateTo Y-m-d
     * @param string[] $memberIds Selected team members
     * @param array<string, float|int|string> $requiredPointsByMember memberId => capacity (tier)
     * @param array<string, float|int|string> $qualityPercentByMember memberId => 0..100
     * @param array<string, float|int|string> $collabPercentByMember memberId => 0..100
     */
    public function buildSprintKpi(
        string $boardId,
        string $label,
        string $dateFrom,
        string $dateTo,
        array $memberIds,
        array $requiredPointsByMember,
        array $qualityPercentByMember,
        array $collabPercentByMember
    ): array
    {
        $report = $this->trelloService->generateBoardReport($boardId, [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'assignees' => $memberIds,
            'lists' => [],
        ]);

        $memberNames = [];
        foreach ($report['member_stats'] ?? [] as $mid => $stats) {
            $memberNames[(string) $mid] = $stats['name'] ?? 'Unknown';
        }

        $rowsByMember = [];
        foreach ($memberIds as $mid) {
            $qPct = $this->clampPercent($qualityPercentByMember[$mid] ?? null);
            $cPct = $this->clampPercent($collabPercentByMember[$mid] ?? null);
            $rowsByMember[$mid] = [
                'member_id' => $mid,
                'name' => $memberNames[$mid] ?? 'Unknown',
                'required_points' => (float) ($requiredPointsByMember[$mid] ?? 0),
                'quality_percent' => $qPct,
                'collaboration_percent' => $cPct,
                'quality_score' => $qPct !== null ? round(($qPct / 100) * 30, 2) : 0.0,
                'collaboration_score' => $cPct !== null ? round(($cPct / 100) * 10, 2) : 0.0,
                'completed_points' => 0.0,
                'assigned_points' => 0.0,
                'weighted_completed_points' => 0.0,
                'productivity_score' => 0.0,
                'efficiency_score' => 0.0,
                'productivity_percent' => null,
                'efficiency_percent' => null,
                'bonus_weighted_points' => 0.0,
                'total_score' => 0.0,
            ];
        }

        foreach ($report['cards'] ?? [] as $card) {
            $pts = (float) ($card['points'] ?? 0);
            if ($pts <= 0) {
                continue;
            }
            $status = (string) ($card['status_key'] ?? 'other');
            $mult = (float) ($card['severity_multiplier'] ?? 1.0);
            $assignees = (array) ($card['member_ids'] ?? []);

            foreach ($assignees as $mid) {
                $mid = (string) $mid;
                if (!isset($rowsByMember[$mid])) {
                    continue;
                }
                $rowsByMember[$mid]['assigned_points'] += $pts;
                if ($status === 'completed') {
                    $rowsByMember[$mid]['completed_points'] += $pts;
                    $rowsByMember[$mid]['weighted_completed_points'] += ($pts * $mult);
                }
            }
        }

        foreach ($rowsByMember as &$row) {
            $required = (float) ($row['required_points'] ?? 0);
            $weighted = (float) ($row['weighted_completed_points'] ?? 0);
            $completed = (float) ($row['completed_points'] ?? 0);

            if ($required > 0) {
                $row['productivity_percent'] = round(($weighted / $required) * 100, 2);
                $row['efficiency_percent'] = round(($completed / $required) * 100, 2);

                $row['productivity_score'] = round(($weighted / $required) * 30, 2);
                $row['efficiency_score'] = round(($completed / $required) * 30, 2);
                $row['bonus_weighted_points'] = $weighted > $required ? round($weighted - $required, 2) : 0.0;
            } else {
                $row['productivity_percent'] = null;
                $row['efficiency_percent'] = null;
                $row['productivity_score'] = 0.0;
                $row['efficiency_score'] = 0.0;
                $row['bonus_weighted_points'] = 0.0;
            }

            $row['total_score'] = round(
                (float) $row['productivity_score'] +
                (float) $row['efficiency_score'] +
                (float) ($row['quality_score'] ?? 0) +
                (float) ($row['collaboration_score'] ?? 0),
                2
            );
        }
        unset($row);

        $overall = $this->overallSprintScores(array_values($rowsByMember));

        return [
            'label' => $label,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'rows' => array_values($rowsByMember),
            'overall' => $overall,
        ];
    }

    /**
     * Overall: average of non-null percents among members with required_points > 0.
     */
    protected function overallSprintScores(array $rows): array
    {
        $prod = [];
        $eff = [];
        $qual = [];
        $collab = [];
        $total = [];
        foreach ($rows as $r) {
            if (($r['required_points'] ?? 0) <= 0) {
                continue;
            }
            if ($r['productivity_percent'] !== null) {
                $prod[] = (float) $r['productivity_percent'];
            }
            if ($r['efficiency_percent'] !== null) {
                $eff[] = (float) $r['efficiency_percent'];
            }
            if (($r['quality_percent'] ?? null) !== null) {
                $qual[] = (float) $r['quality_percent'];
            }
            if (($r['collaboration_percent'] ?? null) !== null) {
                $collab[] = (float) $r['collaboration_percent'];
            }
            if (($r['total_score'] ?? null) !== null) {
                $total[] = (float) $r['total_score'];
            }
        }

        return [
            'productivity_percent' => $prod === [] ? null : round(array_sum($prod) / count($prod), 2),
            'efficiency_percent' => $eff === [] ? null : round(array_sum($eff) / count($eff), 2),
            'quality_percent' => $qual === [] ? null : round(array_sum($qual) / count($qual), 2),
            'collaboration_percent' => $collab === [] ? null : round(array_sum($collab) / count($collab), 2),
            'total_score' => $total === [] ? null : round(array_sum($total) / count($total), 2),
        ];
    }

    /**
     * Merge professionalism scores (25 pts max) and grand total (125 = 100 + 25) into each row and overall averages.
     *
     * @param array<string, array<string, int>> $professionalismByMember member id => scores + total
     */
    public function applyProfessionalismToSprint(array $sprint, array $professionalismByMember): array
    {
        foreach ($sprint['rows'] as &$row) {
            $mid = (string) ($row['member_id'] ?? '');
            if ($mid === '' || !isset($professionalismByMember[$mid])) {
                continue;
            }
            $prow = $professionalismByMember[$mid];
            $row['professionalism'] = $prow;
            $row['grand_total'] = round((float) ($row['total_score'] ?? 0) + (int) ($prow['total'] ?? 0), 2);
        }
        unset($row);

        $sprint['overall'] = $this->extendOverallForProfessionalism($sprint['rows'], $sprint['overall']);

        return $sprint;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, float|null> $overall
     * @return array<string, float|null>
     */
    protected function extendOverallForProfessionalism(array $rows, array $overall): array
    {
        $profTotals = [];
        $grand = [];
        foreach ($rows as $r) {
            if (isset($r['professionalism']['total'])) {
                $profTotals[] = (float) $r['professionalism']['total'];
            }
            if (isset($r['grand_total'])) {
                $grand[] = (float) $r['grand_total'];
            }
        }
        $overall['professionalism_total'] = $profTotals === [] ? null : round(array_sum($profTotals) / count($profTotals), 2);
        $overall['grand_total'] = $grand === [] ? null : round(array_sum($grand) / count($grand), 2);

        return $overall;
    }

    /**
     * @param array<string, array<string, int>> $out keyed by member id
     */
    public function buildProfessionalismFromRequest(Request $request, array $assignees): array
    {
        $out = [];
        foreach ($assignees as $mid) {
            $mid = (string) $mid;
            $entry = [];
            foreach (ProfessionalismKpiReference::dimensionKeys() as $k) {
                $entry[$k] = (int) $request->input("professionalism.$mid.$k");
            }
            $entry['total'] = ProfessionalismKpiReference::sumTotal($entry);
            $out[$mid] = $entry;
        }

        return $out;
    }

    protected function clampPercent($v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (!is_numeric($v)) {
            return null;
        }
        $f = (float) $v;
        if ($f < 0) $f = 0;
        if ($f > 100) $f = 100;

        return round($f, 2);
    }
}

