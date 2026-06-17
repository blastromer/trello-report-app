<?php

namespace App\Support;

class TeamAccomplishmentLists
{
    /**
     * Sprint-complete columns — same rules as Done Sprint (counts toward completed points, Date Completed filter).
     *
     * @var string[]
     */
    public const COMPLETED_SPRINT_LIST_NAMES = [
        'Done Sprint',
        'Temp for Checking and Review',
    ];

    /** @var string[] Canonical list names (matched case-insensitively). */
    public const LIST_NAMES = [
        'In Development',
        'Done Sprint',
        'Temp for Checking and Review',
        'Blocked',
    ];

    /**
     * @return string[]
     */
    public static function completedSprintNormalizedNames(): array
    {
        return array_map(fn (string $n) => strtolower(trim($n)), self::COMPLETED_SPRINT_LIST_NAMES);
    }

    public static function completedSprintLabelsSentence(): string
    {
        return implode(' · ', self::COMPLETED_SPRINT_LIST_NAMES);
    }

    /**
     * @return string[]
     */
    public static function normalizedNames(): array
    {
        return array_map(fn (string $n) => strtolower(trim($n)), self::LIST_NAMES);
    }

    /**
     * Resolve Trello list IDs whose names match the team accomplishment lists.
     *
     * @param array<int, array<string, mixed>> $boardLists from getBoardLists()
     * @return array{ids: string[], labels: string[], missing: string[]}
     */
    public static function resolveFromBoardLists(array $boardLists): array
    {
        $wanted = self::normalizedNames();
        $ids = [];
        $labels = [];
        $found = [];

        foreach ($boardLists as $list) {
            $name = trim((string) ($list['name'] ?? ''));
            $normalized = strtolower($name);
            if (in_array($normalized, $wanted, true)) {
                $ids[] = (string) $list['id'];
                $labels[] = $name;
                $found[] = $normalized;
            }
        }

        $missing = [];
        foreach (self::LIST_NAMES as $label) {
            if (!in_array(strtolower(trim($label)), $found, true)) {
                $missing[] = $label;
            }
        }

        return [
            'ids' => $ids,
            'labels' => $labels,
            'missing' => $missing,
        ];
    }

    public static function labelsSentence(): string
    {
        return implode(', ', self::LIST_NAMES);
    }
}
