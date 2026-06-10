<?php

namespace App\Support;

use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Style\Table as TableStyle;

/**
 * Professionalism KPI: five 0–5 dimensions (25 points total). Rubric text for reports / DOCX.
 */
final class ProfessionalismKpiReference
{
    public static function rubricHeading(): string
    {
        return 'Professionalism KPI — scoring guide';
    }

    /** @return string[] */
    public static function dimensionKeys(): array
    {
        return ['reliability', 'communication', 'accountability', 'team_conduct', 'adaptability'];
    }

    /**
     * Short labels for table headers (form / report / DOCX).
     *
     * @return array<string, string>
     */
    public static function dimensionShortLabels(): array
    {
        return [
            'reliability' => 'Rel.',
            'communication' => 'Comm.',
            'accountability' => 'Acct.',
            'team_conduct' => 'Team',
            'adaptability' => 'Adpt.',
        ];
    }

    public static function weightLine(): string
    {
        return 'Category weight: 25 points total (sum of five categories, each scored 0–5).';
    }

    public static function purposeLine(): string
    {
        return 'Purpose: Measure professionalism through reliability, communication, accountability, teamwork, and adaptability.';
    }

    /**
     * @return array<int, array{title: string, definition: string, rows: array<int, array{score: string, description: string}>}>
     */
    public static function categories(): array
    {
        return [
            [
                'title' => '1. Reliability & Dependability (0–5 points)',
                'definition' => 'Consistently meets deadlines, attends work as scheduled, and follows through on commitments.',
                'rows' => [
                    ['score' => '5', 'description' => 'Always dependable; consistently meets or exceeds deadlines and commitments.'],
                    ['score' => '4', 'description' => 'Reliable and consistent with minor lapses.'],
                    ['score' => '3', 'description' => 'Generally dependable but occasionally needs reminders or extensions.'],
                    ['score' => '2', 'description' => 'Frequently requires follow-up; sometimes misses deadlines.'],
                    ['score' => '1', 'description' => 'Often unreliable or inconsistent in attendance or task completion.'],
                ],
            ],
            [
                'title' => '2. Communication & Professional Interaction (0–5 points)',
                'definition' => 'Communicates clearly and respectfully with colleagues, clients, and supervisors, maintaining a professional tone across all platforms.',
                'rows' => [
                    ['score' => '5', 'description' => 'Always communicates clearly, promptly, and respectfully; models professional tone.'],
                    ['score' => '4', 'description' => 'Usually professional and clear, with only minor lapses.'],
                    ['score' => '3', 'description' => 'Generally clear but occasionally slow or unpolished in communication.'],
                    ['score' => '2', 'description' => 'Sometimes unclear, unresponsive, or unprofessional.'],
                    ['score' => '1', 'description' => 'Frequently disrespectful, dismissive, or unresponsive.'],
                ],
            ],
            [
                'title' => '3. Accountability & Integrity (0–5 points)',
                'definition' => 'Takes responsibility for actions, owns mistakes, and follows through on corrective measures.',
                'rows' => [
                    ['score' => '5', 'description' => 'Highly accountable; demonstrates strong ownership and ethical integrity.'],
                    ['score' => '4', 'description' => 'Accepts responsibility and corrects mistakes with minimal prompting.'],
                    ['score' => '3', 'description' => 'Usually accountable but may need reminders to follow through.'],
                    ['score' => '2', 'description' => 'Occasionally avoids responsibility or fails to correct issues.'],
                    ['score' => '1', 'description' => 'Rarely accountable; blames others or hides mistakes.'],
                ],
            ],
            [
                'title' => '4. Team Conduct & Attitude (0–5 points)',
                'definition' => 'Works respectfully with others, contributes to a positive work environment, and maintains a constructive attitude.',
                'rows' => [
                    ['score' => '5', 'description' => 'Consistently positive, supportive, and team-oriented; builds morale.'],
                    ['score' => '4', 'description' => 'Generally positive and cooperative with rare attitude lapses.'],
                    ['score' => '3', 'description' => 'Works well with others but may show occasional negativity or resistance.'],
                    ['score' => '2', 'description' => 'Sometimes difficult to work with or displays negative attitude.'],
                    ['score' => '1', 'description' => 'Frequently disrespectful, disengaged, or disruptive.'],
                ],
            ],
            [
                'title' => '5. Adaptability & Professional Growth (0–5 points)',
                'definition' => 'Responds well to feedback, embraces change, and shows initiative for continuous improvement.',
                'rows' => [
                    ['score' => '5', 'description' => 'Exceptionally adaptable; actively seeks improvement and thrives under change.'],
                    ['score' => '4', 'description' => 'Open to feedback and adjusts well with minimal resistance.'],
                    ['score' => '3', 'description' => 'Accepts feedback but takes time to adjust.'],
                    ['score' => '2', 'description' => 'Hesitant or resistant to feedback and change.'],
                    ['score' => '1', 'description' => 'Refuses feedback or resists all changes.'],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $scores keyed by dimensionKeys + total
     */
    public static function sumTotal(array $scores): int
    {
        $sum = 0;
        foreach (self::dimensionKeys() as $k) {
            $sum += (int) ($scores[$k] ?? 0);
        }

        return min(25, max(0, $sum));
    }

    public static function appendRubricToWordSection(Section $section): void
    {
        $section->addTextBreak(2);
        $section->addText(DocxSafeText::stripIllegalXmlCharacters(self::rubricHeading()), ['bold' => true, 'size' => 12, 'color' => '111827']);
        $section->addTextBreak(1);
        $section->addText(DocxSafeText::stripIllegalXmlCharacters(self::weightLine()), ['size' => 9]);
        $section->addText(DocxSafeText::stripIllegalXmlCharacters(self::purposeLine()), ['size' => 9, 'italic' => true]);
        $section->addTextBreak(1);

        $tableStyle = ['borderSize' => 4, 'borderColor' => 'cccccc', 'cellMargin' => 60, 'layout' => TableStyle::LAYOUT_FIXED];
        $hFont = ['bold' => true, 'size' => 8];
        $cFont = ['size' => 8];

        foreach (self::categories() as $cat) {
            $section->addText(DocxSafeText::stripIllegalXmlCharacters($cat['title']), ['bold' => true, 'size' => 9]);
            $section->addText(DocxSafeText::stripIllegalXmlCharacters('Definition: ' . $cat['definition']), ['size' => 8]);
            $section->addTextBreak(1);

            $table = $section->addTable($tableStyle);
            $table->addRow();
            $table->addCell(900)->addText('Score', $hFont);
            $table->addCell(9000)->addText('Description', $hFont);
            foreach ($cat['rows'] as $row) {
                $table->addRow();
                $table->addCell(900)->addText(DocxSafeText::stripIllegalXmlCharacters($row['score']), $cFont);
                $table->addCell(9000)->addText(DocxSafeText::stripIllegalXmlCharacters($row['description']), $cFont);
            }
            $section->addTextBreak(1);
        }

        $section->addText(DocxSafeText::stripIllegalXmlCharacters('Total professionalism score = sum of the five categories (maximum 25).'), ['bold' => true, 'size' => 9]);
    }
}
