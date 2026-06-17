<?php

namespace App\Support;

class PerformanceStandards
{
    public function __construct(
        public float $baselineMin = 80.0,
        public float $baselineMax = 90.0,
        public float $tier2Min = 91.0,
        public float $tier2Max = 93.0,
        public float $tier4Min = 94.0,
        public float $tier4Max = 97.0,
        public float $tier6Min = 98.0,
        public float $tier6Max = 100.0,
    ) {
    }

    public static function defaults(): self
    {
        return new self();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            baselineMin: (float) ($data['baseline_min'] ?? 80),
            baselineMax: (float) ($data['baseline_max'] ?? 90),
            tier2Min: (float) ($data['tier_2_min'] ?? 91),
            tier2Max: (float) ($data['tier_2_max'] ?? 93),
            tier4Min: (float) ($data['tier_4_min'] ?? 94),
            tier4Max: (float) ($data['tier_4_max'] ?? 97),
            tier6Min: (float) ($data['tier_6_min'] ?? 98),
            tier6Max: (float) ($data['tier_6_max'] ?? 100),
        );
    }

    /**
     * @return array<string, float>
     */
    public function toArray(): array
    {
        return [
            'baseline_min' => $this->baselineMin,
            'baseline_max' => $this->baselineMax,
            'tier_2_min' => $this->tier2Min,
            'tier_2_max' => $this->tier2Max,
            'tier_4_min' => $this->tier4Min,
            'tier_4_max' => $this->tier4Max,
            'tier_6_min' => $this->tier6Min,
            'tier_6_max' => $this->tier6Max,
        ];
    }

    /** @return array<string, string> */
    public static function validationRules(string $prefix = 'standards'): array
    {
        $p = $prefix;

        return [
            "{$p}.baseline_min" => 'required|numeric|min:0|max:100',
            "{$p}.baseline_max" => 'required|numeric|min:0|max:100|gte:' . "{$p}.baseline_min",
            "{$p}.tier_2_min" => 'required|numeric|min:0|max:100',
            "{$p}.tier_2_max" => 'required|numeric|min:0|max:100|gte:' . "{$p}.tier_2_min",
            "{$p}.tier_4_min" => 'required|numeric|min:0|max:100',
            "{$p}.tier_4_max" => 'required|numeric|min:0|max:100|gte:' . "{$p}.tier_4_min",
            "{$p}.tier_6_min" => 'required|numeric|min:0|max:100',
            "{$p}.tier_6_max" => 'required|numeric|min:0|max:100|gte:' . "{$p}.tier_6_min",
        ];
    }

    public function equals(self $other): bool
    {
        foreach ($this->toArray() as $key => $value) {
            if (abs($value - $other->toArray()[$key]) >= 0.001) {
                return false;
            }
        }

        return true;
    }

    /**
     * Primary comparison point for “below expectation” narratives (baseline floor).
     */
    public function baselineFloor(): float
    {
        return $this->baselineMin;
    }

    public function labelForPercent(?float $percent): string
    {
        if ($percent === null) {
            return 'No measurable points in this sprint window';
        }

        if ($percent < $this->baselineMin) {
            return 'Below Expectation';
        }

        if ($percent <= $this->baselineMax) {
            return sprintf(
                'Meets Baseline Expectation (%s%%–%s%%)',
                $this->formatPct($this->baselineMin),
                $this->formatPct($this->baselineMax)
            );
        }

        if ($percent < $this->tier2Min) {
            return 'Above Baseline (below increase tier)';
        }

        if ($percent <= $this->tier2Max) {
            return sprintf(
                'Eligible for up to 2%% Increase (%s%%–%s%%)',
                $this->formatPct($this->tier2Min),
                $this->formatPct($this->tier2Max)
            );
        }

        if ($percent < $this->tier4Min) {
            return 'Between increase tiers';
        }

        if ($percent <= $this->tier4Max) {
            return sprintf(
                'Eligible for up to 4%% Increase (%s%%–%s%%)',
                $this->formatPct($this->tier4Min),
                $this->formatPct($this->tier4Max)
            );
        }

        if ($percent < $this->tier6Min) {
            return 'Between increase tiers';
        }

        if ($percent <= $this->tier6Max) {
            return sprintf(
                'Eligible for up to 6%% Increase (%s%%–%s%%)',
                $this->formatPct($this->tier6Min),
                $this->formatPct($this->tier6Max)
            );
        }

        return 'Above maximum tier';
    }

    public function summaryLine(): string
    {
        return sprintf(
            'Baseline %s%%–%s%% · 2%%: %s–%s%% · 4%%: %s–%s%% · 6%%: %s–%s%%',
            $this->formatPct($this->baselineMin),
            $this->formatPct($this->baselineMax),
            $this->formatPct($this->tier2Min),
            $this->formatPct($this->tier2Max),
            $this->formatPct($this->tier4Min),
            $this->formatPct($this->tier4Max),
            $this->formatPct($this->tier6Min),
            $this->formatPct($this->tier6Max)
        );
    }

    private function formatPct(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.');
    }
}
