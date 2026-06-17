<?php

namespace App\Models;

use App\Support\PerformanceStandards;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class BoardExpectationThreshold extends Model
{
    protected $fillable = [
        'user_id',
        'board_id',
        'threshold',
        'baseline_min',
        'baseline_max',
        'tier_2_min',
        'tier_2_max',
        'tier_4_min',
        'tier_4_max',
        'tier_6_min',
        'tier_6_max',
    ];

    protected $casts = [
        'threshold' => 'float',
        'baseline_min' => 'float',
        'baseline_max' => 'float',
        'tier_2_min' => 'float',
        'tier_2_max' => 'float',
        'tier_4_min' => 'float',
        'tier_4_max' => 'float',
        'tier_6_min' => 'float',
        'tier_6_max' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toPerformanceStandards(): PerformanceStandards
    {
        if ($this->baseline_min !== null) {
            return PerformanceStandards::fromArray($this->toArray());
        }

        $legacy = (float) ($this->threshold ?? PerformanceStandards::defaults()->baselineMin);

        return new PerformanceStandards(baselineMin: $legacy);
    }

    public static function currentStandardsFor(int $userId, string $boardId): PerformanceStandards
    {
        $latest = static::latestFor($userId, $boardId);

        return $latest !== null ? $latest->toPerformanceStandards() : PerformanceStandards::defaults();
    }

    /** @deprecated Use currentStandardsFor()->baselineFloor() */
    public static function currentFor(int $userId, string $boardId): float
    {
        return static::currentStandardsFor($userId, $boardId)->baselineFloor();
    }

    public static function latestFor(int $userId, string $boardId): ?self
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('board_id', $boardId)
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * @return Collection<int, self>
     */
    public static function historyFor(int $userId, string $boardId, int $limit = 20): Collection
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('board_id', $boardId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public static function recordIfChanged(int $userId, string $boardId, PerformanceStandards $standards): ?self
    {
        $latest = static::latestFor($userId, $boardId);
        if ($latest !== null && $latest->toPerformanceStandards()->equals($standards)) {
            return null;
        }

        return static::create(array_merge(
            [
                'user_id' => $userId,
                'board_id' => $boardId,
                'threshold' => $standards->baselineFloor(),
            ],
            $standards->toArray()
        ));
    }
}
