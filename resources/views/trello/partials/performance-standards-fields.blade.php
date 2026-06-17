@php
    $s = old('standards', isset($currentStandards) ? $currentStandards->toArray() : \App\Support\PerformanceStandards::defaults()->toArray());
@endphp
<table class="standards-table">
    <thead>
        <tr>
            <th>Baseline expectation</th>
            <th>Up to 2% increase</th>
            <th>Up to 4% increase</th>
            <th>Up to 6% increase</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>
                <div class="range-inputs">
                    <input type="number" name="standards[baseline_min]" class="std-field" data-std="baseline_min" min="0" max="100" step="0.01" value="{{ $s['baseline_min'] ?? 80 }}" required>
                    <span>–</span>
                    <input type="number" name="standards[baseline_max]" class="std-field" data-std="baseline_max" min="0" max="100" step="0.01" value="{{ $s['baseline_max'] ?? 90 }}" required>
                    <span>%</span>
                </div>
            </td>
            <td>
                <div class="range-inputs">
                    <input type="number" name="standards[tier_2_min]" class="std-field" data-std="tier_2_min" min="0" max="100" step="0.01" value="{{ $s['tier_2_min'] ?? 91 }}" required>
                    <span>–</span>
                    <input type="number" name="standards[tier_2_max]" class="std-field" data-std="tier_2_max" min="0" max="100" step="0.01" value="{{ $s['tier_2_max'] ?? 93 }}" required>
                    <span>%</span>
                </div>
            </td>
            <td>
                <div class="range-inputs">
                    <input type="number" name="standards[tier_4_min]" class="std-field" data-std="tier_4_min" min="0" max="100" step="0.01" value="{{ $s['tier_4_min'] ?? 94 }}" required>
                    <span>–</span>
                    <input type="number" name="standards[tier_4_max]" class="std-field" data-std="tier_4_max" min="0" max="100" step="0.01" value="{{ $s['tier_4_max'] ?? 97 }}" required>
                    <span>%</span>
                </div>
            </td>
            <td>
                <div class="range-inputs">
                    <input type="number" name="standards[tier_6_min]" class="std-field" data-std="tier_6_min" min="0" max="100" step="0.01" value="{{ $s['tier_6_min'] ?? 98 }}" required>
                    <span>–</span>
                    <input type="number" name="standards[tier_6_max]" class="std-field" data-std="tier_6_max" min="0" max="100" step="0.01" value="{{ $s['tier_6_max'] ?? 100 }}" required>
                    <span>%</span>
                </div>
            </td>
        </tr>
    </tbody>
</table>
