<?php

namespace App\Services;

use App\Support\DocxSafeText;
use DateTimeInterface;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Style\Section;

class AccountabilityDocxExportService
{
    private const EMU_PER_INCH = 914400;

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
            'orientation' => Section::ORIENTATION_PORTRAIT,
            'marginLeft' => 900,
            'marginRight' => 900,
            'marginTop' => 900,
            'marginBottom' => 900,
        ]);

        $section->addText('Team Accountability Reports', ['bold' => true, 'size' => 22]);
        $section->addText(
            'Monthly report — '
            . DocxSafeText::stripIllegalXmlCharacters((string) ($document['month_label'] ?? ''))
            . ' · ' . DocxSafeText::stripIllegalXmlCharacters((string) ($document['board_name'] ?? ''))
            . ' · Generated ' . $generatedAt->format('Y-m-d H:i'),
            ['size' => 10, 'color' => '555555']
        );

        $np = $document['narrative_period'] ?? null;
        if (is_array($np) && !empty($np['from']) && !empty($np['to'])) {
            $section->addText(
                'Narrative period (Date Completed field): '
                . DocxSafeText::stripIllegalXmlCharacters((string) $np['from'])
                . ' → '
                . DocxSafeText::stripIllegalXmlCharacters((string) $np['to']),
                ['size' => 9, 'color' => '666666']
            );
        }

        $n = $document['narrative'] ?? [];

        $this->addHeading($section, 'Key accomplishments');
        $this->addBulletLines($section, $n['key_accomplishments'] ?? '');

        $this->addHeading($section, 'Ongoing projects');
        $this->addBulletLines($section, $n['ongoing_projects'] ?? '');

        if ($this->textHasContent($n['challenges'] ?? '')) {
            $this->addHeading($section, 'Challenges');
            $this->addBulletLines($section, $n['challenges'] ?? '');
        }

        if ($this->textHasContent($n['plans_next_steps'] ?? '')) {
            $this->addHeading($section, 'Plans & next steps');
            $this->addBulletLines($section, $n['plans_next_steps'] ?? '');
        }

        if ($this->textHasContent($n['context_interpretation'] ?? '')) {
            $this->addHeading($section, 'Context & interpretation');
            $this->addPreformattedParagraph($section, $n['context_interpretation'] ?? '');
        }

        $this->addHeading($section, 'Team performance metrics');
        $section->addText(
            'Points acquired summary — '
            . DocxSafeText::stripIllegalXmlCharacters((string) ($document['month_label'] ?? ''))
            . '. Accomplishment = completed list points ÷ total points in sprint window (per person).',
            ['size' => 9, 'italic' => true, 'color' => '555555']
        );
        $standardsLine = isset($document['performance_standards'])
            ? \App\Support\PerformanceStandards::fromArray($document['performance_standards'])->summaryLine()
            : 'Baseline ' . rtrim(rtrim(number_format((float) ($document['expectation_threshold'] ?? 80), 2), '0'), '.') . '%';
        $section->addText('Performance standards: ' . DocxSafeText::stripIllegalXmlCharacters($standardsLine), ['size' => 9, 'color' => '444444']);
        $section->addTextBreak(1);

        $chartData = $this->extractChartData($document);
        if ($chartData['sprint_labels'] !== [] && $chartData['sprint_overall_values'] !== []) {
            $this->addColumnChart(
                $section,
                $chartData['sprint_labels'],
                [
                    [
                        'name' => 'Team avg. accomplishment %',
                        'values' => $chartData['sprint_overall_values'],
                    ],
                ],
                'Overall sprint accomplishment (%)',
                'Percent'
            );
            $section->addTextBreak(1);
        }

        if ($chartData['member_labels'] !== [] && $chartData['member_series'] !== []) {
            $this->addColumnChart(
                $section,
                $chartData['member_labels'],
                $chartData['member_series'],
                'Accomplishment % by team member',
                'Accomplishment %'
            );
            $section->addTextBreak(1);
        }

        foreach ($document['sprints'] ?? [] as $sprint) {
            $this->addSprintBlock($section, $sprint);
        }

        if ($this->textHasContent($n['overall_outlook'] ?? '')) {
            $this->addHeading($section, 'Overall outlook');
            $this->addPreformattedParagraph($section, $n['overall_outlook'] ?? '');
        }

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save('php://output');
    }

    private function addHeading($section, string $title): void
    {
        $section->addTextBreak(1);
        $section->addText(DocxSafeText::stripIllegalXmlCharacters($title), ['bold' => true, 'size' => 14, 'color' => '111827']);
    }

    private function textHasContent(string $text): bool
    {
        return trim(DocxSafeText::stripIllegalXmlCharacters($text)) !== '';
    }

    /**
     * @return string[]
     */
    private function linesFromText(string $text): array
    {
        $text = trim(DocxSafeText::stripIllegalXmlCharacters($text));
        if ($text === '') {
            return [];
        }

        return preg_split('/\r\n|\r|\n/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private function addBulletLines($section, string $text): void
    {
        $lines = $this->linesFromText($text);
        if ($lines === []) {
            $section->addText('None entered.', ['size' => 10, 'italic' => true, 'color' => '6b7280']);

            return;
        }
        foreach ($lines as $line) {
            $section->addListItem(DocxSafeText::stripIllegalXmlCharacters($line), 0, ['size' => 10]);
        }
    }

    private function addPreformattedParagraph($section, string $text): void
    {
        foreach ($this->linesFromText($text) as $line) {
            $section->addText(DocxSafeText::stripIllegalXmlCharacters($line), ['size' => 10]);
            $section->addTextBreak(1);
        }
    }

    private function addSprintBlock($section, array $sprint): void
    {
        $section->addTextBreak(1);
        $label = DocxSafeText::stripIllegalXmlCharacters((string) ($sprint['label'] ?? 'Sprint'))
            . ' (' . DocxSafeText::stripIllegalXmlCharacters((string) ($sprint['date_from'] ?? ''))
            . ' — ' . DocxSafeText::stripIllegalXmlCharacters((string) ($sprint['date_to'] ?? '')) . ')';
        $section->addText($label, ['bold' => true, 'size' => 11]);

        foreach ($sprint['members'] ?? [] as $m) {
            $name = DocxSafeText::stripIllegalXmlCharacters((string) ($m['name'] ?? ''));
            $total = (float) ($m['points_total'] ?? 0);
            if ($total > 0) {
                $rate = $m['accomplishment_rate'] !== null
                    ? number_format((float) $m['accomplishment_rate'], 2)
                    : '0';
                $pc = $this->trimNum($m['points_completed'] ?? 0);
                $pt = $this->trimNum($m['points_total'] ?? 0);
                $section->addText(
                    $name . ': ' . $rate . '% accomplishment rate (' . $pc . ' / ' . $pt . ' pts completed)',
                    ['size' => 10]
                );
            } else {
                $section->addText($name . ': no points in this window', ['size' => 10, 'color' => '6b7280']);
            }
        }

        $overall = $sprint['overall_accomplishment_percent'] ?? null;
        $expect = DocxSafeText::stripIllegalXmlCharacters((string) ($sprint['expectation_label'] ?? ''));
        $overallText = $overall !== null
            ? number_format((float) $overall, 2) . '% — ' . $expect
            : 'N/A — ' . $expect;
        $section->addText('Overall sprint performance: ' . $overallText, ['bold' => true, 'size' => 10]);
    }

    private function trimNum(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.') ?: '0';
    }

    /**
     * @return array{
     *   sprint_labels: string[],
     *   sprint_overall_values: float[],
     *   member_labels: string[],
     *   member_series: array<int, array{name: string, values: float[]}>
     * }
     */
    private function extractChartData(array $document): array
    {
        $sprints = $document['sprints'] ?? [];
        $sprintLabels = [];
        $sprintOverallValues = [];
        foreach ($sprints as $sp) {
            $sprintLabels[] = DocxSafeText::stripIllegalXmlCharacters((string) ($sp['label'] ?? 'Sprint'));
            $pct = $sp['overall_accomplishment_percent'] ?? null;
            $sprintOverallValues[] = $pct !== null ? (float) $pct : 0.0;
        }

        $memberIdsOrdered = [];
        $memberLabelsById = [];
        foreach ($sprints as $sp) {
            foreach ($sp['members'] ?? [] as $m) {
                $mid = (string) ($m['member_id'] ?? '');
                if ($mid !== '' && !array_key_exists($mid, $memberLabelsById)) {
                    $memberLabelsById[$mid] = DocxSafeText::stripIllegalXmlCharacters((string) ($m['name'] ?? $mid));
                    $memberIdsOrdered[] = $mid;
                }
            }
        }
        $memberLabels = array_values($memberLabelsById);

        $memberSeries = [];
        foreach ($sprints as $sp) {
            $byId = [];
            foreach ($sp['members'] ?? [] as $m) {
                $mid = (string) ($m['member_id'] ?? '');
                $tot = (float) ($m['points_total'] ?? 0);
                $byId[$mid] = ($tot > 0 && ($m['accomplishment_rate'] ?? null) !== null)
                    ? round((float) $m['accomplishment_rate'], 2)
                    : 0.0;
            }
            $series = [];
            foreach ($memberIdsOrdered as $mid) {
                $series[] = array_key_exists($mid, $byId) ? $byId[$mid] : 0.0;
            }
            $memberSeries[] = [
                'name' => DocxSafeText::stripIllegalXmlCharacters((string) ($sp['label'] ?? 'Sprint')),
                'values' => $series,
            ];
        }

        return [
            'sprint_labels' => $sprintLabels,
            'sprint_overall_values' => $sprintOverallValues,
            'member_labels' => $memberLabels,
            'member_series' => $memberSeries,
        ];
    }

    /**
     * @param array<int, array{name: string, values: float[]}> $seriesList
     */
    private function addColumnChart($section, array $categoryLabels, array $seriesList, string $title, string $valueAxisTitle): void
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
            'width' => (int) (6.5 * self::EMU_PER_INCH),
            'height' => (int) (2.6 * self::EMU_PER_INCH),
            'showLegend' => count($seriesList) > 1,
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
}
