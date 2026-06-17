@if($mode === 'year' && $yearData)
    <div class="stat-grid">
        <div class="stat"><strong>{{ $yearData['year'] }}</strong><span>Calendar year</span></div>
        <div class="stat"><strong>{{ count($teamMemberIds ?? []) }}</strong><span>Team members</span></div>
    </div>
    <div class="chart-box">
        <h3 style="margin:0 0 10px;font-size:0.95rem;">Team average by month (%)</h3>
        <canvas id="chartYearOverall"></canvas>
    </div>
    <div class="chart-box">
        <h3 style="margin:0 0 10px;font-size:0.95rem;">Member accomplishment by month (%)</h3>
        <canvas id="chartYearMembers"></canvas>
    </div>
@elseif($performance)
    <div class="stat-grid">
        <div class="stat"><strong>{{ $performance['overall_percent'] !== null ? number_format($performance['overall_percent'], 1) . '%' : '—' }}</strong><span>Team accomplishment</span></div>
        <div class="stat"><strong>{{ number_format($performance['total_points'], 0) }}</strong><span>Points in scope</span></div>
        <div class="stat"><strong>{{ $performance['total_cards'] }}</strong><span>Cards in scope</span></div>
        <div class="stat"><strong>{{ count($performance['accomplishment_lists'] ?? []) }}</strong><span>Lists matched</span></div>
    </div>
    @if(!empty($performance['accomplishment_lists']))
        <p class="muted" style="margin:0 0 14px;">Lists: {{ implode(' · ', $performance['accomplishment_lists']) }}</p>
    @endif
    @if(!empty($performance['overall_tier_label']))
        <p class="muted" style="margin:0 0 14px;"><strong>Period:</strong> {{ $performance['label'] }} · {{ $performance['overall_tier_label'] }}</p>
    @endif
    <div class="chart-box">
        <h3 style="margin:0 0 10px;font-size:0.95rem;">Accomplishment by team member (%)</h3>
        <canvas id="chartMembers"></canvas>
    </div>
    <h3 style="font-size:1rem;margin:20px 0 10px;">Accomplishment lists ({{ str_replace(', ', ' · ', \App\Support\TeamAccomplishmentLists::labelsSentence()) }})</h3>
    <table>
        <thead>
            <tr>
                <th>Member</th>
                <th>Completed pts</th>
                <th>Total pts</th>
                <th>Rate</th>
                <th>Tier</th>
                <th>Cards in scope</th>
            </tr>
        </thead>
        <tbody>
            @foreach($performance['members'] as $m)
                <tr>
                    <td><strong>{{ $m['name'] }}</strong></td>
                    <td>{{ number_format($m['points_completed'], 1) }}</td>
                    <td>{{ number_format($m['points_total'], 1) }}</td>
                    <td>{{ $m['accomplishment_rate'] !== null ? number_format($m['accomplishment_rate'], 1) . '%' : '—' }}</td>
                    <td style="font-size:0.85rem;">{{ $m['tier_label'] ?? '—' }}</td>
                    <td style="font-size:0.82rem;">
                        @php $summary = $m['card_summary'] ?? null; @endphp
                        @if($summary && $summary['count'] > 0)
                            <strong>{{ $summary['count'] }}</strong>
                            @foreach($summary['by_list'] as $listName => $cnt)
                                <span class="muted">· {{ $cnt }} {{ $listName }}</span>
                            @endforeach
                            @if(!empty($summary['preview']))
                                <details style="margin-top:6px;">
                                    <summary class="muted" style="cursor:pointer;">Show cards</summary>
                                    <ul style="margin:6px 0 0;padding-left:18px;">
                                        @foreach($summary['preview'] as $c)
                                            <li>
                                                {{ $c['name'] }}
                                                <span class="muted">({{ $c['list_name'] }}, {{ number_format($c['points'], 0) }} pts)</span>
                                            </li>
                                        @endforeach
                                        @if(($summary['remaining'] ?? 0) > 0)
                                            <li class="muted">…and {{ $summary['remaining'] }} more</li>
                                        @endif
                                    </ul>
                                </details>
                            @endif
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php $currentWip = $performance['current_pipeline_members'] ?? []; @endphp
    @if(collect($currentWip)->sum('card_count') > 0)
        <h3 style="font-size:1rem;margin:24px 0 10px;">Other lists (not {{ \App\Support\TeamAccomplishmentLists::labelsSentence() }})</h3>
        <p class="muted" style="margin:0 0 10px;">Open pipeline cards on your team outside the accomplishment lists above.</p>
        <div class="chart-box">
            <canvas id="chartWipCurrent"></canvas>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Member</th>
                    <th>WIP pts</th>
                    <th>Cards</th>
                    <th>Examples</th>
                </tr>
            </thead>
            <tbody>
                @foreach($currentWip as $w)
                    @if(($w['card_count'] ?? 0) > 0)
                        <tr>
                            <td><strong>{{ $w['name'] }}</strong></td>
                            <td>{{ number_format($w['points'], 1) }}</td>
                            <td>{{ $w['card_count'] }}</td>
                            <td style="font-size:0.82rem;">
                                @foreach(array_slice($w['cards'] ?? [], 0, 3) as $c)
                                    {{ $c['name'] }} <span class="muted">({{ $c['list_name'] }}, {{ number_format($c['points'], 0) }} pts)</span>@if(!$loop->last)<br>@endif
                                @endforeach
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif
@elseif($mode === 'sprint')
    <p class="muted">Set sprint dates and click <strong>Update charts</strong>.</p>
@endif

@if($performance && $mode !== 'year')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var members = @json($performance['members']);
        var labels = members.map(function (m) { return m.name; });
        var data = members.map(function (m) { return m.accomplishment_rate; });
        var el = document.getElementById('chartMembers');
        if (el && typeof Chart !== 'undefined') {
            new Chart(el, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Accomplishment %',
                        data: data,
                        backgroundColor: 'rgba(37, 99, 235, 0.65)',
                        borderColor: '#2563eb',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: { y: { beginAtZero: true, max: 100, title: { display: true, text: 'Percent' } } },
                    plugins: { legend: { display: false } }
                }
            });
        }

        var wip = @json($performance['current_pipeline_members'] ?? []);
        var wipFiltered = wip.filter(function (w) { return (w.card_count || 0) > 0; });
        var elWip = document.getElementById('chartWipCurrent');
        if (elWip && typeof Chart !== 'undefined' && wipFiltered.length) {
            new Chart(elWip, {
                type: 'bar',
                data: {
                    labels: wipFiltered.map(function (w) { return w.name; }),
                    datasets: [{
                        label: 'Open WIP story points',
                        data: wipFiltered.map(function (w) { return w.points; }),
                        backgroundColor: 'rgba(217, 119, 6, 0.7)',
                        borderColor: '#d97706',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: { y: { beginAtZero: true, title: { display: true, text: 'Points' } } },
                    plugins: { legend: { display: false } }
                }
            });
        }
    });
</script>
@endif

@if($yearData)
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var periods = @json($yearData['periods']);
        var trends = @json($yearData['member_trends']);
        var monthLabels = periods.map(function (p) { return p.label; });
        var overall = periods.map(function (p) { return p.overall_percent; });

        var el1 = document.getElementById('chartYearOverall');
        if (el1 && typeof Chart !== 'undefined') {
            new Chart(el1, {
                type: 'line',
                data: {
                    labels: monthLabels,
                    datasets: [{
                        label: 'Team average %',
                        data: overall,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37,99,235,0.1)',
                        fill: true,
                        tension: 0.2
                    }]
                },
                options: {
                    responsive: true,
                    scales: { y: { beginAtZero: true, max: 100 } }
                }
            });
        }

        var palette = ['#2563eb','#059669','#d97706','#7c3aed','#dc2626','#0891b2','#be185d'];
        var el2 = document.getElementById('chartYearMembers');
        if (el2 && typeof Chart !== 'undefined') {
            new Chart(el2, {
                type: 'line',
                data: {
                    labels: monthLabels,
                    datasets: trends.map(function (t, i) {
                        return {
                            label: t.name,
                            data: t.monthly_rates,
                            borderColor: palette[i % palette.length],
                            tension: 0.2,
                            spanGaps: true
                        };
                    })
                },
                options: {
                    responsive: true,
                    scales: { y: { beginAtZero: true, max: 100 } },
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }
    });
</script>
@endif
