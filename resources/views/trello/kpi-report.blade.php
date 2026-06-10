<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Individual KPI — {{ $document['month_label'] }} — {{ $document['board_name'] }}</title>
    <link href="https://fonts.bunny.net/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Nunito', sans-serif; background: #f3f4f6; margin: 0; padding: 24px; color: #1f2937; line-height: 1.55; }
        .doc { max-width: 1100px; margin: 0 auto; background: #fff; padding: 36px 44px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        h1 { font-size: 1.55rem; margin: 0 0 6px; }
        .meta { color: #6b7280; font-size: 0.9rem; margin-bottom: 22px; }
        h2 { font-size: 1.1rem; margin: 26px 0 12px; color: #111827; border-bottom: 2px solid #e5e7eb; padding-bottom: 6px; }
        .info { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 14px; color: #1e40af; margin: 14px 0 18px; font-size: 0.9rem; }
        table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 700; color: #374151; position: sticky; top: 0; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 999px; font-weight: 700; font-size: 0.8rem; }
        .b-ok { background: #d1fae5; color: #065f46; }
        .b-warn { background: #fef3c7; color: #92400e; }
        .b-muted { background: #e5e7eb; color: #374151; }
        .th-tip { display: inline-flex; align-items: center; gap: 6px; }
        .tip-icon { display: inline-flex; width: 18px; height: 18px; align-items: center; justify-content: center; border-radius: 999px; background: #e0e7ff; color: #3730a3; font-size: 12px; font-weight: 800; cursor: help; }
        .sprint { margin-bottom: 26px; }
        .sprint-head { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
        .sprint-head strong { font-size: 1.02rem; }
        .muted { color: #6b7280; font-size: 0.875rem; }
        .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 18px; margin: 14px 0 20px; }
        .chart-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; }
        .chart-box h3 { font-size: 0.95rem; margin: 0 0 10px; color: #374151; }
        .chart-box canvas { max-height: 290px; }
        .actions { margin-top: 26px; display: flex; flex-wrap: wrap; gap: 10px; }
        .btn { display: inline-block; padding: 10px 18px; border-radius: 6px; font-weight: 600; text-decoration: none; font-size: 0.95rem; border: none; cursor: pointer; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-secondary { background: #6b7280; color: #fff; }
        @media print {
            body { background: #fff; padding: 0; }
            .actions, .export-hint { display: none; }
            .doc { box-shadow: none; max-width: none; }
            .chart-box { break-inside: avoid; }
        }
        body.standalone-export .export-hint { break-inside: avoid; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
</head>
<body @if(!empty($standaloneExport)) class="standalone-export" @endif>
    <div class="doc">
        @if(!empty($standaloneExport))
            <p class="export-hint muted" style="font-size:0.82rem;margin:0 0 16px;padding:10px 12px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;color:#0c4a6e;">
                <strong>Google Docs:</strong> Upload this file to Google Drive, then right-click → <em>Open with → Google Docs</em>.
                Tables and styling usually convert well. For charts inside Docs, use <strong>Print / PDF</strong> on the live report, or open this HTML in Chrome while online so Chart.js can load.
            </p>
        @endif
        <h1>Individual KPI</h1>
        <p class="meta">
            {{ $document['month_label'] }} · {{ $document['board_name'] }} · Generated {{ $boardReport->generated_at->format('Y-m-d H:i') }}
            @php
                $cov = $document['date_coverage'] ?? null;
            @endphp
            @if(is_array($cov) && !empty($cov['date_from']) && !empty($cov['date_to']))
                <br><span class="muted">Coverage window: {{ $cov['date_from'] }} → {{ $cov['date_to'] }}</span>
            @endif
        </p>

        <div class="info">
            <strong>Severity multipliers:</strong>
            P1={{ $document['severity_multipliers']['P1'] }} (Critical),
            P2={{ $document['severity_multipliers']['P2'] }} (High),
            P3={{ $document['severity_multipliers']['P3'] }} (Medium),
            P4={{ $document['severity_multipliers']['P4'] }} (Low).
            <br>
            Productivity uses <em>weighted completed points</em> and Efficiency uses <em>raw completed points</em>, both divided by the member’s required points.
        </div>

        @php
            $hasProf = !empty($document['professionalism']['members'] ?? []);
            $profDims = \App\Support\ProfessionalismKpiReference::dimensionKeys();
            $profShort = $document['professionalism']['dimension_labels'] ?? \App\Support\ProfessionalismKpiReference::dimensionShortLabels();
            $sprints = $document['sprints'] ?? [];
            $memberLabels = [];
            $memberIds = [];
            foreach ($sprints as $sp) {
                foreach ($sp['rows'] ?? [] as $r) {
                    $mid = (string) ($r['member_id'] ?? '');
                    if ($mid !== '' && !array_key_exists($mid, $memberLabels)) {
                        $memberLabels[$mid] = $r['name'] ?? $mid;
                        $memberIds[] = $mid;
                    }
                }
            }
            $labels = array_values($memberLabels);

            $palette = ['rgba(37,99,235,0.75)', 'rgba(5,150,105,0.75)', 'rgba(217,119,6,0.75)', 'rgba(124,58,237,0.75)', 'rgba(220,38,38,0.75)', 'rgba(4,120,187,0.75)'];
            $prodDatasets = [];
            $effDatasets = [];
            foreach ($sprints as $i => $sp) {
                $byId = [];
                foreach ($sp['rows'] ?? [] as $r) {
                    $byId[(string) $r['member_id']] = [
                        'prod' => $r['productivity_percent'],
                        'eff' => $r['efficiency_percent'],
                    ];
                }
                $prod = [];
                $eff = [];
                foreach ($memberIds as $mid) {
                    $prod[] = $byId[$mid]['prod'] ?? null;
                    $eff[] = $byId[$mid]['eff'] ?? null;
                }
                $c = $palette[$i % count($palette)];
                $prodDatasets[] = ['label' => $sp['label'] ?? 'Sprint', 'data' => $prod, 'backgroundColor' => $c, 'borderColor' => str_replace('0.75', '1', $c), 'borderWidth' => 1];
                $effDatasets[] = ['label' => $sp['label'] ?? 'Sprint', 'data' => $eff, 'backgroundColor' => $c, 'borderColor' => str_replace('0.75', '1', $c), 'borderWidth' => 1];
            }
        @endphp

        @if(count($labels) > 0 && count($sprints) > 0)
            <div class="charts-grid">
                <div class="chart-box">
                    <h3>Productivity % by member (weighted)</h3>
                    <canvas id="chartKpiProd" aria-label="Productivity chart" role="img"></canvas>
                </div>
                <div class="chart-box">
                    <h3>Efficiency % by member (raw)</h3>
                    <canvas id="chartKpiEff" aria-label="Efficiency chart" role="img"></canvas>
                </div>
            </div>
        @endif

        <h2>Sprint breakdown</h2>
        @foreach($sprints as $sp)
            <div class="sprint">
                <div class="sprint-head">
                    <div>
                        <strong>{{ $sp['label'] }}</strong>
                        <span class="muted">({{ $sp['date_from'] }} — {{ $sp['date_to'] }})</span>
                    </div>
                    <div class="muted">
                        Overall avg:
                        Productivity {{ $sp['overall']['productivity_percent'] !== null ? number_format($sp['overall']['productivity_percent'], 2) : 'N/A' }}% ·
                        Efficiency {{ $sp['overall']['efficiency_percent'] !== null ? number_format($sp['overall']['efficiency_percent'], 2) : 'N/A' }}% ·
                        Quality {{ $sp['overall']['quality_percent'] !== null ? number_format($sp['overall']['quality_percent'], 2) : 'N/A' }}% ·
                        Collaboration {{ $sp['overall']['collaboration_percent'] !== null ? number_format($sp['overall']['collaboration_percent'], 2) : 'N/A' }}% ·
                        Total {{ $sp['overall']['total_score'] !== null ? number_format($sp['overall']['total_score'], 2) : 'N/A' }}/100
                        @if($hasProf)
                            · Prof {{ isset($sp['overall']['professionalism_total']) && $sp['overall']['professionalism_total'] !== null ? number_format($sp['overall']['professionalism_total'], 2) : 'N/A' }}/25
                            · Grand {{ isset($sp['overall']['grand_total']) && $sp['overall']['grand_total'] !== null ? number_format($sp['overall']['grand_total'], 2) : 'N/A' }}/125
                        @endif
                    </div>
                </div>

                <div style="max-height: 420px; overflow: auto; border: 1px solid #e5e7eb; border-radius: 8px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>
                                    <span class="th-tip">Required
                                        <span class="tip-icon" title="Required points (tier capacity). Used as the denominator for Productivity and Efficiency.">i</span>
                                    </span>
                                </th>
                                <th>
                                    <span class="th-tip">Completed
                                        <span class="tip-icon" title="Sum of story points on cards classified as Completed for this sprint window and member.">i</span>
                                    </span>
                                </th>
                                <th>
                                    <span class="th-tip">Weighted completed
                                        <span class="tip-icon" title="Sum of (completed story points × severity multiplier). Severity comes from the Trello custom field “Severity” (P1=1.3, P2=1.2, P3=1.1, P4=1.0).">i</span>
                                    </span>
                                </th>
                                <th>
                                    <span class="th-tip">Productivity %
                                        <span class="tip-icon" title="(Weighted completed ÷ Required) × 100.">i</span>
                                    </span>
                                </th>
                                <th>
                                    <span class="th-tip">Efficiency %
                                        <span class="tip-icon" title="(Completed ÷ Required) × 100.">i</span>
                                    </span>
                                </th>
                                <th>
                                    <span class="th-tip">Productivity (30)
                                        <span class="tip-icon" title="(Weighted completed ÷ Required) × 30. This is the Productivity component out of 30%.">i</span>
                                    </span>
                                </th>
                                <th>
                                    <span class="th-tip">Efficiency (30)
                                        <span class="tip-icon" title="(Completed ÷ Required) × 30. This is the Efficiency component out of 30%.">i</span>
                                    </span>
                                </th>
                                <th>
                                    <span class="th-tip">Quality %
                                        <span class="tip-icon" title="Manual input per member. Converted to Quality (30) as (Quality% ÷ 100) × 30.">i</span>
                                    </span>
                                </th>
                                <th>
                                    <span class="th-tip">Quality (30)
                                        <span class="tip-icon" title="(Quality% ÷ 100) × 30.">i</span>
                                    </span>
                                </th>
                                <th>
                                    <span class="th-tip">Collab %
                                        <span class="tip-icon" title="Manual input per member. Converted to Collaboration (10) as (Collab% ÷ 100) × 10.">i</span>
                                    </span>
                                </th>
                                <th>
                                    <span class="th-tip">Collab (10)
                                        <span class="tip-icon" title="(Collab% ÷ 100) × 10.">i</span>
                                    </span>
                                </th>
                                <th>
                                    <span class="th-tip">Total (100)
                                        <span class="tip-icon" title="Productivity (30) + Efficiency (30) + Quality (30) + Collaboration (10).">i</span>
                                    </span>
                                </th>
                                @if($hasProf)
                                    @foreach($profDims as $dk)
                                        <th title="{{ $dk }}">{{ $profShort[$dk] ?? $dk }}</th>
                                    @endforeach
                                    <th>
                                        <span class="th-tip">Prof (25)
                                            <span class="tip-icon" title="Sum of five professionalism categories (0–5 each). Same for all sprints in this report.">i</span>
                                        </span>
                                    </th>
                                    <th>
                                        <span class="th-tip">Grand (125)
                                            <span class="tip-icon" title="Total (100) + Professionalism (25).">i</span>
                                        </span>
                                    </th>
                                @endif
                                <th>
                                    <span class="th-tip">Bonus (weighted)
                                        <span class="tip-icon" title="Max(0, Weighted completed − Required). This is the excess weighted points beyond capacity.">i</span>
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sp['rows'] ?? [] as $r)
                                @php
                                    $p = $r['productivity_percent'];
                                    $e = $r['efficiency_percent'];
                                    $badge = function ($x) {
                                        if ($x === null) return ['b-muted', 'N/A'];
                                        if ($x >= 100) return ['b-ok', '100%+'];
                                        if ($x >= 85) return ['b-ok', 'Good'];
                                        if ($x >= 70) return ['b-warn', 'Watch'];
                                        return ['b-warn', 'Low'];
                                    };
                                    [$pc, $pl] = $badge($p);
                                    [$ec, $el] = $badge($e);
                                @endphp
                                <tr>
                                    <td><strong>{{ $r['name'] }}</strong></td>
                                    <td>{{ rtrim(rtrim(number_format((float) $r['required_points'], 2), '0'), '.') }}</td>
                                    <td>{{ rtrim(rtrim(number_format((float) $r['completed_points'], 2), '0'), '.') }}</td>
                                    <td>{{ rtrim(rtrim(number_format((float) $r['weighted_completed_points'], 2), '0'), '.') }}</td>
                                    <td>
                                        <span class="badge {{ $pc }}">{{ $p !== null ? number_format($p, 2) . '%' : 'N/A' }}</span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $ec }}">{{ $e !== null ? number_format($e, 2) . '%' : 'N/A' }}</span>
                                    </td>
                                    <td>{{ number_format((float) $r['productivity_score'], 2) }}</td>
                                    <td>{{ number_format((float) $r['efficiency_score'], 2) }}</td>
                                    <td>{{ $r['quality_percent'] !== null ? number_format((float) $r['quality_percent'], 2) . '%' : 'N/A' }}</td>
                                    <td>{{ number_format((float) ($r['quality_score'] ?? 0), 2) }}</td>
                                    <td>{{ $r['collaboration_percent'] !== null ? number_format((float) $r['collaboration_percent'], 2) . '%' : 'N/A' }}</td>
                                    <td>{{ number_format((float) ($r['collaboration_score'] ?? 0), 2) }}</td>
                                    <td><strong>{{ number_format((float) ($r['total_score'] ?? 0), 2) }}</strong></td>
                                    @if($hasProf)
                                        @php $pr = $r['professionalism'] ?? []; @endphp
                                        @foreach($profDims as $dk)
                                            <td>{{ isset($pr[$dk]) ? (int) $pr[$dk] : '—' }}</td>
                                        @endforeach
                                        <td><strong>{{ isset($pr['total']) ? (int) $pr['total'] : '—' }}</strong></td>
                                        <td><strong>{{ isset($r['grand_total']) ? number_format((float) $r['grand_total'], 2) : '—' }}</strong></td>
                                    @endif
                                    <td>{{ number_format((float) $r['bonus_weighted_points'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        @include('trello.partials.professionalism-kpi')

        @if(empty($standaloneExport))
        <div class="actions">
            <button type="button" class="btn btn-primary" onclick="window.print()">Print / PDF</button>
            <a href="{{ route('trello.kpi.html', $boardReport) }}" class="btn btn-primary" title="Same layout as this page. Upload to Google Drive → Open with Google Docs.">Download HTML (Google Docs)</a>
            <a href="{{ route('trello.kpi.docx', $boardReport) }}" class="btn btn-secondary" title="Microsoft Word format; layout may differ from this page.">Download Word (.docx)</a>
            <a href="{{ route('trello.kpi.form', $document['board_id']) }}" class="btn btn-secondary">New KPI report</a>
            <a href="{{ route('trello.accountability.form', $document['board_id']) }}" class="btn btn-secondary">Accountability</a>
            <a href="{{ route('trello.boards') }}" class="btn btn-secondary">Boards</a>
        </div>
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var labels = @json($labels);
            var prodDatasets = @json($prodDatasets);
            var effDatasets = @json($effDatasets);

            var baseOpts = {
                responsive: true,
                plugins: { legend: { position: 'bottom' } },
                scales: {
                    y: { beginAtZero: true, max: 160, title: { display: true, text: 'Percent' } }
                }
            };

            var elP = document.getElementById('chartKpiProd');
            if (elP && typeof Chart !== 'undefined') {
                new Chart(elP, { type: 'bar', data: { labels: labels, datasets: prodDatasets }, options: baseOpts });
            }
            var elE = document.getElementById('chartKpiEff');
            if (elE && typeof Chart !== 'undefined') {
                new Chart(elE, { type: 'bar', data: { labels: labels, datasets: effDatasets }, options: baseOpts });
            }
        });
    </script>
</body>
</html>

