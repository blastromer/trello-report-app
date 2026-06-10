<?php

namespace App\Services;

use App\Support\DocxSafeText;
use App\Support\ProfessionalismKpiReference;
use DateTimeInterface;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Style\Section;
use PhpOffice\PhpWord\Style\Table;

class IndividualKpiDocxExportService
{
    /** EMU per inch (Office drawing) */
    private const EMU_PER_INCH = 914400;

    /**
     * Stream a Word 2007 (.docx) document to php://output.
     */
    public function writeDocxToOutput(array $document, DateTimeInterface $generatedAt): void
    {
        $prevEscaping = Settings::isOutputEscapingEnabled();
        Settings::setOutputEscapingEnabled(true);

        try {
            $this->writeDocxBody($document, $generatedAt);
        } finally {
            Settings::setOutputEscapingEnabled($prevEscaping);
        }
    }

    private function writeDocxBody(array $document, DateTimeInterface $generatedAt): void
    {
        $phpWord = new PhpWord();

        $section = $phpWord->addSection([
            'orientation' => Section::ORIENTATION_LANDSCAPE,
            'marginLeft' => 720,
            'marginRight' => 720,
            'marginTop' => 720,
            'marginBottom' => 720,
        ]);

        $section->addText('Individual KPI', ['bold' => true, 'size' => 20]);
        $section->addText(
            DocxSafeText::stripIllegalXmlCharacters((string) ($document['month_label'] ?? ''))
            . ' · ' . DocxSafeText::stripIllegalXmlCharacters((string) ($document['board_name'] ?? ''))
            . ' · Generated ' . $generatedAt->format('Y-m-d H:i'),
            ['size' => 10, 'color' => '555555']
        );

        $cov = $document['date_coverage'] ?? null;
        if (is_array($cov) && !empty($cov['date_from']) && !empty($cov['date_to'])) {
            $section->addText(
                'Coverage window: '
                . DocxSafeText::stripIllegalXmlCharacters((string) $cov['date_from'])
                . ' → '
                . DocxSafeText::stripIllegalXmlCharacters((string) $cov['date_to']),
                ['size' => 9, 'color' => '666666']
            );
        }

        $hasProf = !empty($document['professionalism']['members'] ?? []);

        $section->addTextBreak(1);
        $this->addInfoBanner($section, $document, $hasProf);
        $section->addTextBreak(1);
        $this->addColumnDefinitionsSection($section, $hasProf);

        $chartData = $this->extractMemberChartData($document);
        $hasCharts = $chartData['labels'] !== [] && ($chartData['prod_series'] ?? []) !== [];
        if ($hasCharts) {
            $section->addTextBreak(1);
            $section->addText('Charts', ['bold' => true, 'size' => 13, 'color' => '111827']);
            $section->addTextBreak(1);
            $this->addGroupedColumnChart(
                $section,
                $chartData['labels'],
                $chartData['prod_series'],
                'Productivity % by member (weighted)',
                'Percent'
            );
            $section->addTextBreak(1);
            $this->addGroupedColumnChart(
                $section,
                $chartData['labels'],
                $chartData['eff_series'],
                'Efficiency % by member (raw)',
                'Percent'
            );
        }

        $sprints = $document['sprints'] ?? [];
        if ($sprints !== []) {
            if ($hasCharts) {
                $section->addPageBreak();
            }
            $section->addText('Sprint breakdown', ['bold' => true, 'size' => 14, 'color' => '111827']);
            $section->addTextBreak(1);
        }

        $first = true;
        foreach ($sprints as $sp) {
            if (!$first) {
                $section->addPageBreak();
            }
            $first = false;
            $this->appendSprintBlock($section, $sp, $hasProf);
        }

        ProfessionalismKpiReference::appendRubricToWordSection($section);

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save('php://output');
    }

    private function addInfoBanner($section, array $document, bool $hasProf): void
    {
        $mult = $document['severity_multipliers'] ?? [];
        $table = $section->addTable([
            'borderSize' => 8,
            'borderColor' => 'BFDBFE',
            'layout' => Table::LAYOUT_FIXED,
        ]);
        $table->addRow();
        $cell = $table->addCell(12500, [
            'borderSize' => 8,
            'borderColor' => 'BFDBFE',
            'shading' => ['fill' => 'EFF6FF'],
        ]);

        $infoFont = ['size' => 9, 'color' => '1E40AF'];
        $cell->addText('Metrics implemented:', array_merge($infoFont, ['bold' => true]));
        $cell->addTextBreak(1);
        $cell->addText(
            'Productivity (30%) = sum(completed story points × severity multiplier) ÷ required points × 30.',
            $infoFont
        );
        $cell->addTextBreak(1);
        $cell->addText(
            'Efficiency (30%) = completed story points ÷ required points × 30.',
            $infoFont
        );
        $cell->addTextBreak(1);
        $cell->addText(
            'Severity comes from the Trello custom field named Severity (P1..P4): '
            . 'P1=' . ($mult['P1'] ?? '') . ' (Critical), P2=' . ($mult['P2'] ?? '') . ' (High), '
            . 'P3=' . ($mult['P3'] ?? '') . ' (Medium), P4=' . ($mult['P4'] ?? '') . ' (Low).',
            $infoFont
        );
        $cell->addTextBreak(1);
        $cell->addText(
            'Productivity uses weighted completed points and Efficiency uses raw completed points, both divided by the member’s required points.',
            $infoFont
        );
        if ($hasProf) {
            $cell->addTextBreak(1);
            $cell->addText(
                'Professionalism (25): five categories scored 0–5 each on the form; same scores are shown on every sprint and added to Total (100) for Grand (125).',
                $infoFont
            );
        }
    }

    private function addColumnDefinitionsSection($section, bool $hasProf): void
    {
        $section->addText('Column definitions (KPI tables)', ['bold' => true, 'size' => 11, 'color' => '111827']);
        $section->addTextBreak(1);

        $defs = [
            'Required' => 'Required points (tier capacity). Used as the denominator for Productivity and Efficiency.',
            'Completed' => 'Sum of story points on cards classified as Completed for this sprint window and member.',
            'Weighted completed' => 'Sum of (completed story points × severity multiplier). Severity comes from the Trello custom field “Severity” (P1=1.3, P2=1.2, P3=1.1, P4=1.0).',
            'Productivity %' => '(Weighted completed ÷ Required) × 100.',
            'Efficiency %' => '(Completed ÷ Required) × 100.',
            'Productivity (30)' => '(Weighted completed ÷ Required) × 30. Productivity component out of 30%.',
            'Efficiency (30)' => '(Completed ÷ Required) × 30. Efficiency component out of 30%.',
            'Quality %' => 'Manual input per member. Converted to Quality (30) as (Quality% ÷ 100) × 30.',
            'Quality (30)' => '(Quality% ÷ 100) × 30.',
            'Collaboration %' => 'Manual input per member. Converted to Collaboration (10) as (Collab% ÷ 100) × 10.',
            'Collaboration (10)' => '(Collab% ÷ 100) × 10.',
            'Total (100)' => 'Productivity (30) + Efficiency (30) + Quality (30) + Collaboration (10).',
            'Bonus (weighted)' => 'Max(0, Weighted completed − Required). Excess weighted points beyond capacity.',
        ];

        if ($hasProf) {
            $short = ProfessionalismKpiReference::dimensionShortLabels();
            foreach (ProfessionalismKpiReference::dimensionKeys() as $k) {
                $label = $short[$k] ?? $k;
                $defs[$label . ' (0–5)'] = 'Professionalism category; manual score 0–5. See rubric appendix.';
            }
            $defs['Prof Σ (25)'] = 'Sum of the five professionalism scores (max 25). Same for all sprints.';
            $defs['Grand (125)'] = 'Total (100) + Prof Σ (25).';
        }

        foreach ($defs as $title => $text) {
            $section->addListItem(
                DocxSafeText::stripIllegalXmlCharacters($title . ': ' . $text),
                0,
                ['size' => 8, 'color' => '374151']
            );
        }
    }

    /**
     * @return array{labels: string[], prod_series: array<int, array{name: string, values: float[]}>, eff_series: array<int, array{name: string, values: float[]}>}
     */
    private function extractMemberChartData(array $document): array
    {
        $sprints = $document['sprints'] ?? [];
        $memberLabels = [];
        $memberIds = [];
        foreach ($sprints as $sp) {
            foreach ($sp['rows'] ?? [] as $r) {
                $mid = (string) ($r['member_id'] ?? '');
                if ($mid !== '' && !array_key_exists($mid, $memberLabels)) {
                    $memberLabels[$mid] = DocxSafeText::stripIllegalXmlCharacters((string) ($r['name'] ?? $mid));
                    $memberIds[] = $mid;
                }
            }
        }
        $labels = array_values($memberLabels);

        $prodSeries = [];
        $effSeries = [];
        foreach ($sprints as $sp) {
            $byId = [];
            foreach ($sp['rows'] ?? [] as $r) {
                $byId[(string) $r['member_id']] = [
                    'prod' => $r['productivity_percent'] ?? null,
                    'eff' => $r['efficiency_percent'] ?? null,
                ];
            }
            $prod = [];
            $eff = [];
            foreach ($memberIds as $mid) {
                $pp = $byId[$mid]['prod'] ?? null;
                $ee = $byId[$mid]['eff'] ?? null;
                $prod[] = $pp !== null ? (float) $pp : 0.0;
                $eff[] = $ee !== null ? (float) $ee : 0.0;
            }
            $name = DocxSafeText::stripIllegalXmlCharacters((string) ($sp['label'] ?? 'Sprint'));
            $prodSeries[] = ['name' => $name, 'values' => $prod];
            $effSeries[] = ['name' => $name, 'values' => $eff];
        }

        return [
            'labels' => $labels,
            'prod_series' => $prodSeries,
            'eff_series' => $effSeries,
        ];
    }

    /**
     * @param array<int, array{name: string, values: float[]}> $seriesList
     */
    private function addGroupedColumnChart($section, array $categoryLabels, array $seriesList, string $title, string $valueAxisTitle): void
    {
        if ($categoryLabels === [] || $seriesList === []) {
            return;
        }

        $categoryLabels = array_map(
            static fn ($c) => DocxSafeText::stripIllegalXmlCharacters((string) $c),
            $categoryLabels
        );

        $seriesList = array_map(static function ($s) {
            return [
                'name' => DocxSafeText::escapeForChartRawXml((string) ($s['name'] ?? '')),
                'values' => $s['values'],
            ];
        }, $seriesList);

        $first = $seriesList[0];
        $chartStyle = [
            'width' => (int) (7.5 * self::EMU_PER_INCH),
            'height' => (int) (2.75 * self::EMU_PER_INCH),
            'showLegend' => true,
            'legendPosition' => 'b',
            'title' => DocxSafeText::escapeForChartRawXml($title),
            'valueAxisTitle' => DocxSafeText::escapeForChartRawXml($valueAxisTitle),
            'colors' => ['2563EB', '059669', 'D97706', '7C3AED', 'DC2626', '0478BB'],
        ];

        $chart = $section->addChart(
            'column',
            $categoryLabels,
            $first['values'],
            $chartStyle,
            $first['name']
        );

        for ($i = 1, $n = count($seriesList); $i < $n; $i++) {
            $chart->addSeries($categoryLabels, $seriesList[$i]['values'], $seriesList[$i]['name']);
        }
    }

    private function appendSprintBlock($section, array $sp, bool $hasProf): void
    {
        $section->addTextBreak(1);
        $label = DocxSafeText::stripIllegalXmlCharacters((string) ($sp['label'] ?? 'Sprint'))
            . ' (' . DocxSafeText::stripIllegalXmlCharacters((string) ($sp['date_from'] ?? ''))
            . ' — ' . DocxSafeText::stripIllegalXmlCharacters((string) ($sp['date_to'] ?? '')) . ')';
        $section->addText(
            $label,
            ['bold' => true, 'size' => 12]
        );

        $o = $sp['overall'] ?? [];
        $overallLine = 'Overall avg: Productivity ' . $this->fmtPercent($o['productivity_percent'] ?? null)
            . ' · Efficiency ' . $this->fmtPercent($o['efficiency_percent'] ?? null)
            . ' · Quality ' . $this->fmtPercent($o['quality_percent'] ?? null)
            . ' · Collaboration ' . $this->fmtPercent($o['collaboration_percent'] ?? null)
            . ' · Total ' . $this->fmtScore($o['total_score'] ?? null) . '/100';
        if ($hasProf) {
            $overallLine .= ' · Prof ' . $this->fmtScore($o['professionalism_total'] ?? null) . '/25'
                . ' · Grand ' . $this->fmtScore($o['grand_total'] ?? null) . '/125';
        }
        $section->addText($overallLine, ['size' => 9, 'color' => '444444']);

        $tableStyle = [
            'borderSize' => 5,
            'borderColor' => '999999',
            'cellMargin' => 35,
            'layout' => Table::LAYOUT_FIXED,
        ];
        $table = $section->addTable($tableStyle);

        $headerFont = ['bold' => true, 'size' => 6];
        $cellFont = ['size' => 6];

        $widths = [1020, 420, 420, 500, 400, 400, 420, 420, 360, 360, 360, 360, 400];
        $headers = [
            'Member',
            'Required',
            'Completed',
            "Weighted\ncompleted",
            "Productivity\n%",
            "Efficiency\n%",
            "Productivity\n(30)",
            "Efficiency\n(30)",
            "Quality\n%",
            "Quality\n(30)",
            "Collab\n%",
            "Collab\n(10)",
            "Total\n(100)",
        ];

        if ($hasProf) {
            $short = ProfessionalismKpiReference::dimensionShortLabels();
            foreach (ProfessionalismKpiReference::dimensionKeys() as $k) {
                $widths[] = 260;
                $headers[] = DocxSafeText::stripIllegalXmlCharacters($short[$k] ?? $k);
            }
            $widths[] = 320;
            $widths[] = 360;
            $headers[] = "Prof\nΣ";
            $headers[] = "Grand\n125";
        }

        $widths[] = 420;
        $headers[] = "Bonus\n(weighted)";

        $table->addRow(null, ['cantSplit' => true]);
        foreach ($headers as $i => $h) {
            $table->addCell($widths[$i])->addText($h, $headerFont);
        }

        foreach ($sp['rows'] ?? [] as $r) {
            $table->addRow();
            $p = $r['productivity_percent'] ?? null;
            $e = $r['efficiency_percent'] ?? null;

            $cells = [
                ['text' => DocxSafeText::stripIllegalXmlCharacters((string) ($r['name'] ?? '')), 'font' => array_merge($cellFont, ['bold' => true])],
                ['text' => $this->fmtPoints((float) ($r['required_points'] ?? 0)), 'font' => $cellFont],
                ['text' => $this->fmtPoints((float) ($r['completed_points'] ?? 0)), 'font' => $cellFont],
                ['text' => $this->fmtPoints((float) ($r['weighted_completed_points'] ?? 0)), 'font' => $cellFont],
                ['text' => $this->fmtPercent($p), 'font' => $cellFont],
                ['text' => $this->fmtPercent($e), 'font' => $cellFont],
                ['text' => $this->fmtPoints((float) ($r['productivity_score'] ?? 0), 2), 'font' => $cellFont],
                ['text' => $this->fmtPoints((float) ($r['efficiency_score'] ?? 0), 2), 'font' => $cellFont],
                ['text' => $this->fmtPercent($r['quality_percent'] ?? null), 'font' => $cellFont],
                ['text' => $this->fmtPoints((float) ($r['quality_score'] ?? 0), 2), 'font' => $cellFont],
                ['text' => $this->fmtPercent($r['collaboration_percent'] ?? null), 'font' => $cellFont],
                ['text' => $this->fmtPoints((float) ($r['collaboration_score'] ?? 0), 2), 'font' => $cellFont],
                ['text' => $this->fmtPoints((float) ($r['total_score'] ?? 0), 2), 'font' => array_merge($cellFont, ['bold' => true])],
            ];

            if ($hasProf) {
                $pr = $r['professionalism'] ?? [];
                foreach (ProfessionalismKpiReference::dimensionKeys() as $k) {
                    $cells[] = ['text' => isset($pr[$k]) ? (string) (int) $pr[$k] : '—', 'font' => $cellFont];
                }
                $cells[] = ['text' => isset($pr['total']) ? (string) (int) $pr['total'] : '—', 'font' => array_merge($cellFont, ['bold' => true])];
                $cells[] = ['text' => isset($r['grand_total']) ? $this->fmtPoints((float) $r['grand_total'], 2) : '—', 'font' => array_merge($cellFont, ['bold' => true])];
            }

            $cells[] = ['text' => $this->fmtPoints((float) ($r['bonus_weighted_points'] ?? 0), 2), 'font' => $cellFont];

            foreach ($cells as $i => $cell) {
                $table->addCell($widths[$i])->addText($cell['text'], $cell['font']);
            }
        }
    }

    private function fmtPoints(float $n, int $decimals = 2): string
    {
        return $this->trimZeros(number_format($n, $decimals, '.', ''));
    }

    private function fmtPercent($n): string
    {
        if ($n === null || $n === '') {
            return 'N/A';
        }

        return $this->trimZeros(number_format((float) $n, 2, '.', '')) . '%';
    }

    private function fmtScore($n): string
    {
        if ($n === null || $n === '') {
            return 'N/A';
        }

        return $this->trimZeros(number_format((float) $n, 2, '.', ''));
    }

    private function trimZeros(string $s): string
    {
        return rtrim(rtrim($s, '0'), '.') ?: '0';
    }
}
