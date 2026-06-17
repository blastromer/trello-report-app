<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardTeamGroup extends Model
{
    protected $fillable = [
        'user_id',
        'board_id',
        'member_ids',
        'performance_mode',
        'performance_month',
        'performance_year',
        'sprint_label',
        'sprint_date_from',
        'sprint_date_to',
    ];

    protected $casts = [
        'member_ids' => 'array',
        'performance_year' => 'integer',
        'sprint_date_from' => 'date:Y-m-d',
        'sprint_date_to' => 'date:Y-m-d',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return string[]
     */
    public static function memberIdsFor(int $userId, string $boardId): array
    {
        $group = static::query()
            ->where('user_id', $userId)
            ->where('board_id', $boardId)
            ->first();

        if (!$group || !is_array($group->member_ids)) {
            return [];
        }

        return array_values(array_filter($group->member_ids));
    }

    public static function saveFor(int $userId, string $boardId, array $memberIds): self
    {
        return static::updateOrCreate(
            ['user_id' => $userId, 'board_id' => $boardId],
            ['member_ids' => array_values(array_unique(array_filter($memberIds)))]
        );
    }

    /**
     * @return array{
     *     mode: string,
     *     month: string,
     *     year: int,
     *     sprint_label: string,
     *     sprint_date_from: string,
     *     sprint_date_to: string
     * }
     */
    public static function performancePrefsFor(int $userId, string $boardId): array
    {
        $group = static::query()
            ->where('user_id', $userId)
            ->where('board_id', $boardId)
            ->first();

        $defaults = [
            'mode' => 'month',
            'month' => now()->format('Y-m'),
            'year' => (int) now()->year,
            'sprint_label' => 'Sprint',
            'sprint_date_from' => '',
            'sprint_date_to' => '',
        ];

        if (!$group) {
            return $defaults;
        }

        return [
            'mode' => $group->performance_mode ?: $defaults['mode'],
            'month' => $group->performance_month ?: $defaults['month'],
            'year' => $group->performance_year ?: $defaults['year'],
            'sprint_label' => $group->sprint_label ?: $defaults['sprint_label'],
            'sprint_date_from' => $group->sprint_date_from
                ? $group->sprint_date_from->format('Y-m-d')
                : '',
            'sprint_date_to' => $group->sprint_date_to
                ? $group->sprint_date_to->format('Y-m-d')
                : '',
        ];
    }

    /**
     * @param array{
     *     mode?: string,
     *     month?: string,
     *     year?: int,
     *     sprint_label?: string,
     *     sprint_date_from?: string|null,
     *     sprint_date_to?: string|null
     * } $prefs
     */
    public static function savePerformancePrefs(int $userId, string $boardId, array $prefs): void
    {
        $record = static::query()->firstOrCreate(
            ['user_id' => $userId, 'board_id' => $boardId],
            ['member_ids' => []]
        );

        $mode = in_array($prefs['mode'] ?? '', ['month', 'year', 'sprint'], true)
            ? $prefs['mode']
            : ($record->performance_mode ?: 'month');

        $record->fill([
            'performance_mode' => $mode,
            'performance_month' => !empty($prefs['month']) ? substr((string) $prefs['month'], 0, 7) : null,
            'performance_year' => !empty($prefs['year']) ? (int) $prefs['year'] : null,
            'sprint_label' => isset($prefs['sprint_label']) ? trim((string) $prefs['sprint_label']) : null,
            'sprint_date_from' => !empty($prefs['sprint_date_from']) ? $prefs['sprint_date_from'] : null,
            'sprint_date_to' => !empty($prefs['sprint_date_to']) ? $prefs['sprint_date_to'] : null,
        ]);
        $record->save();
    }
}
