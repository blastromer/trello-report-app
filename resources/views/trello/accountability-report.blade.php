<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accountability — {{ $document['month_label'] }} — {{ $document['board_name'] }}</title>
    <link href="https://fonts.bunny.net/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Nunito', sans-serif; background: #f3f4f6; margin: 0; padding: 24px; color: #1f2937; line-height: 1.55; }
        .doc { max-width: 960px; margin: 0 auto; background: #fff; padding: 40px 48px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        h1 { font-size: 1.65rem; margin: 0 0 8px; }
        .meta { color: #6b7280; font-size: 0.9rem; margin-bottom: 32px; }
        h2 { font-size: 1.15rem; margin: 28px 0 12px; color: #111827; border-bottom: 2px solid #e5e7eb; padding-bottom: 6px; }
        ul { margin: 0 0 16px; padding-left: 1.25rem; }
        li { margin-bottom: 6px; }
        .sprint-block { margin-bottom: 28px; padding: 16px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb; }
        .sprint-block h3 { margin: 0 0 12px; font-size: 1.05rem; }
        .metric { margin: 6px 0; }
        .overall { font-weight: 700; margin-top: 10px; }
        .actions { margin-top: 32px; display: flex; flex-wrap: wrap; gap: 10px; }
        .btn { display: inline-block; padding: 10px 18px; border-radius: 6px; font-weight: 600; text-decoration: none; font-size: 0.95rem; border: none; cursor: pointer; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-secondary { background: #6b7280; color: #fff; }
        .muted { color: #6b7280; font-size: 0.875rem; }
        .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin: 16px 0 28px; }
        .chart-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; }
        .chart-box h3 { font-size: 0.95rem; margin: 0 0 10px; color: #374151; }
        .chart-box canvas { max-height: 280px; }
        @media print {
            body { background: #fff; padding: 0; }
            .actions { display: none; }
            .doc { box-shadow: none; max-width: none; }
            .chart-box { break-inside: avoid; }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
</head>
<body>
    <div class="doc">
        @include('trello.partials.report-library-notice')

        <h1>Team Accountability Reports</h1>
        @php
            $reportStandards = isset($document['performance_standards'])
                ? \App\Support\PerformanceStandards::fromArray($document['performance_standards'])
                : new \App\Support\PerformanceStandards(baselineMin: (float) ($document['expectation_threshold'] ?? 80));
        @endphp
        <p class="meta">Monthly report — {{ $document['month_label'] }} · {{ $document['board_name'] }} · Generated {{ $boardReport->generated_at->format('Y-m-d H:i') }}
            <br><span class="muted">Performance standards: {{ $reportStandards->summaryLine() }}</span>
            @php $np = $document['narrative_period'] ?? null; @endphp
            @if(is_array($np) && !empty($np['from']) && !empty($np['to']))
                <br><span class="muted">Narrative &amp; sprint card scope: <strong>Date Completed</strong> between {{ $np['from'] }} and {{ $np['to'] }} (cards without that field are excluded).</span>
            @endif
        </p>

        @php
            $n = $document['narrative'] ?? [];
            $bullets = function ($text) {
                $text = trim((string) $text);
                if ($text === '') return [];
                return preg_split('/\r\n|\r|\n/', $text, -1, PREG_SPLIT_NO_EMPTY);
            };
        @endphp

        <h2>Key accomplishments</h2>
        <ul>
            @forelse($bullets($n['key_accomplishments'] ?? '') as $line)
                <li>{{ $line }}</li>
            @empty
                <li class="muted">None entered.</li>
            @endforelse
        </ul>

        <h2>Ongoing projects</h2>
        <ul>
            @forelse($bullets($n['ongoing_projects'] ?? '') as $line)
                <li>{{ $line }}</li>
            @empty
                <li class="muted">None entered.</li>
            @endforelse
        </ul>

        @if(!empty(trim($n['challenges'] ?? '')))
            <h2>Challenges</h2>
            <ul>
                @foreach($bullets($n['challenges']) as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        @endif

        @if(!empty(trim($n['plans_next_steps'] ?? '')))
            <h2>Plans &amp; next steps</h2>
            <ul>
                @foreach($bullets($n['plans_next_steps']) as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        @endif

        @if(!empty(trim($n['context_interpretation'] ?? '')))
            <h2>Context &amp; interpretation</h2>
            <p style="white-space: pre-wrap;">{{ $n['context_interpretation'] }}</p>
        @endif

        <h2>Team performance metrics</h2>
        <p class="muted">Points acquired summary — {{ $document['month_label'] }}. Accomplishment = completed list points ÷ total points in sprint window (per person).</p>

        @php
            $sprintsForChart = $document['sprints'] ?? [];
            $sprintOverallLabels = [];
            $sprintOverallData = [];
            foreach ($sprintsForChart as $sp) {
                $sprintOverallLabels[] = $sp['label'] ?? 'Sprint';
                $pct = $sp['overall_accomplishment_percent'] ?? null;
                $sprintOverallData[] = $pct !== null ? round((float) $pct, 2) : null;
            }
            $memberIdsOrdered = [];
            $memberLabelsById = [];
            foreach ($sprintsForChart as $sp) {
                foreach ($sp['members'] ?? [] as $m) {
                    $mid = (string) ($m['member_id'] ?? '');
                    if ($mid !== '' && ! array_key_exists($mid, $memberLabelsById)) {
                        $memberLabelsById[$mid] = $m['name'] ?? $mid;
                        $memberIdsOrdered[] = $mid;
                    }
                }
            }
            $groupedMemberNames = array_values($memberLabelsById);
            $palette = ['rgba(37,99,235,0.75)', 'rgba(5,150,105,0.75)', 'rgba(217,119,6,0.75)', 'rgba(124,58,237,0.75)', 'rgba(220,38,38,0.75)', 'rgba(4,120,187,0.75)'];
            $groupedChartDatasets = [];
            foreach ($sprintsForChart as $idx => $sp) {
                $byId = [];
                foreach ($sp['members'] ?? [] as $m) {
                    $mid = (string) ($m['member_id'] ?? '');
                    $tot = (float) ($m['points_total'] ?? 0);
                    $byId[$mid] = ($tot > 0 && ($m['accomplishment_rate'] ?? null) !== null)
                        ? round((float) $m['accomplishment_rate'], 2)
                        : null;
                }
                $series = [];
                foreach ($memberIdsOrdered as $mid) {
                    $series[] = array_key_exists($mid, $byId) ? $byId[$mid] : null;
                }
                $groupedChartDatasets[] = [
                    'label' => $sp['label'] ?? 'Sprint',
                    'data' => $series,
                    'backgroundColor' => $palette[$idx % count($palette)],
                    'borderColor' => str_replace('0.75', '1', $palette[$idx % count($palette)]),
                    'borderWidth' => 1,
                ];
            }
        @endphp

        @if(count($sprintsForChart) > 0)
        <div class="charts-grid">
            <div class="chart-box">
                <h3>Overall sprint accomplishment (%)</h3>
                <canvas id="chartAccountabilitySprints" aria-label="Sprint overall chart" role="img"></canvas>
            </div>
            @if(count($groupedMemberNames) > 0 && count($groupedChartDatasets) > 0)
            <div class="chart-box" style="grid-column: 1 / -1;">
                <h3>Accomplishment % by team member</h3>
                <canvas id="chartAccountabilityMembers" aria-label="Member by sprint chart" role="img"></canvas>
            </div>
            @endif
        </div>
        @endif

        @foreach($document['sprints'] ?? [] as $sprint)
            <div class="sprint-block">
                <h3>{{ $sprint['label'] }}
                    <span class="muted">({{ $sprint['date_from'] }} — {{ $sprint['date_to'] }})</span>
                </h3>
                @foreach($sprint['members'] ?? [] as $m)
                    @if(($m['points_total'] ?? 0) > 0)
                        <div class="metric">
                            <strong>{{ $m['name'] }}:</strong>
                            {{ $m['accomplishment_rate'] !== null ? number_format($m['accomplishment_rate'], 2) : '0' }}% accomplishment rate
                            <span class="muted">({{ rtrim(rtrim(number_format($m['points_completed'], 2), '0'), '.') }} / {{ rtrim(rtrim(number_format($m['points_total'], 2), '0'), '.') }} pts completed)</span>
                        </div>
                    @else
                        <div class="metric muted">{{ $m['name'] }}: no points in this window</div>
                    @endif
                @endforeach
                <div class="overall">
                    Overall sprint performance:
                    @if($sprint['overall_accomplishment_percent'] !== null)
                        {{ number_format($sprint['overall_accomplishment_percent'], 2) }}% — {{ $sprint['expectation_label'] ?? '' }}
                    @else
                        N/A — {{ $sprint['expectation_label'] ?? '' }}
                    @endif
                </div>
            </div>
        @endforeach

        @if(!empty(trim($n['overall_outlook'] ?? '')))
            <h2>Overall outlook</h2>
            <p style="white-space: pre-wrap;">{{ $n['overall_outlook'] }}</p>
        @endif

        <div class="actions">
            <button type="button" class="btn btn-primary" onclick="window.print()">Print / PDF</button>
            @if($boardReport->exists)
                <a href="{{ route('trello.accountability.docx', $boardReport) }}" class="btn btn-primary">Download Word (.docx)</a>
                <a href="{{ route('trello.saved-reports') }}" class="btn btn-secondary">Saved reports</a>
            @endif
            <a href="{{ route('trello.accountability.form', $document['board_id']) }}" class="btn btn-secondary">New report</a>
            <a href="{{ route('trello.board.dashboard', $document['board_id']) }}" class="btn btn-secondary">Back to board</a>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var baselineMin = {{ (float) $reportStandards->baselineMin }};
            var baselineMax = {{ (float) $reportStandards->baselineMax }};
            var el1 = document.getElementById('chartAccountabilitySprints');
            if (el1 && typeof Chart !== 'undefined') {
                new Chart(el1, {
                    type: 'bar',
                    data: {
                        labels: @json($sprintOverallLabels),
                        datasets: [{
                            label: 'Team avg. accomplishment %',
                            data: @json($sprintOverallData),
                            backgroundColor: 'rgba(37, 99, 235, 0.65)',
                            borderColor: '#2563eb',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    footer: function () {
                                        return 'Baseline: ' + baselineMin + '%–' + baselineMax + '%';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                title: { display: true, text: 'Percent' }
                            }
                        }
                    },
                    plugins: [{
                        id: 'baselineBand',
                        afterDraw: function (chart) {
                            var scale = chart.scales.y;
                            if (!scale) return;
                            var ctx = chart.ctx;
                            var yTop = scale.getPixelForValue(baselineMax);
                            var yBottom = scale.getPixelForValue(baselineMin);
                            ctx.save();
                            ctx.fillStyle = 'rgba(5, 150, 105, 0.12)';
                            ctx.fillRect(chart.chartArea.left, yTop, chart.chartArea.right - chart.chartArea.left, yBottom - yTop);
                            ctx.setLineDash([6, 4]);
                            ctx.strokeStyle = '#059669';
                            ctx.lineWidth = 1;
                            [baselineMin, baselineMax].forEach(function (val) {
                                var y = scale.getPixelForValue(val);
                                ctx.beginPath();
                                ctx.moveTo(chart.chartArea.left, y);
                                ctx.lineTo(chart.chartArea.right, y);
                                ctx.stroke();
                            });
                            ctx.fillStyle = '#047857';
                            ctx.font = '11px sans-serif';
                            ctx.fillText('Baseline ' + baselineMin + '%–' + baselineMax + '%', chart.chartArea.left + 4, yTop - 4);
                            ctx.restore();
                        }
                    }]
                });
            }
            var el2 = document.getElementById('chartAccountabilityMembers');
            if (el2 && typeof Chart !== 'undefined') {
                new Chart(el2, {
                    type: 'bar',
                    data: {
                        labels: @json($groupedMemberNames),
                        datasets: @json($groupedChartDatasets)
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { position: 'bottom' } },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                title: { display: true, text: 'Accomplishment %' }
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>
