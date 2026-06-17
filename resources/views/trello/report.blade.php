<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Board Report - {{ $boardReport->board_name }}</title>
    <link href="https://fonts.bunny.net/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1f2937;
            margin-bottom: 10px;
            font-size: 2rem;
        }
        .subtitle {
            color: #6b7280;
            margin-bottom: 30px;
        }
        .actions {
            margin-bottom: 30px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        .btn-primary {
            background-color: #2563eb;
            color: white;
        }
        .btn-primary:hover {
            background-color: #1d4ed8;
        }
        .btn-secondary {
            background-color: #6b7280;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #4b5563;
        }
        .section {
            margin-bottom: 40px;
        }
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 25px;
            border-radius: 8px;
            color: white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .stat-card.blue {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        }
        .stat-card.green {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        .stat-card.orange {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 0.875rem;
            opacity: 0.9;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background-color: #f9fafb;
            font-weight: 600;
            color: #1f2937;
            position: sticky;
            top: 0;
        }
        tr:hover {
            background-color: #f9fafb;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .status-completed {
            background-color: #d1fae5;
            color: #065f46;
        }
        .status-in-progress {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .status-todo {
            background-color: #fef3c7;
            color: #92400e;
        }
        .status-other {
            background-color: #e5e7eb;
            color: #374151;
        }
        .points-badge {
            background-color: #fef3c7;
            color: #92400e;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.875rem;
        }
        .member-tag {
            display: inline-block;
            background-color: #e0e7ff;
            color: #3730a3;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.875rem;
            margin-right: 4px;
        }
        .label-tag {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            margin-right: 4px;
            font-weight: 500;
        }
        .filters-applied {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .filters-applied strong {
            color: #1e40af;
        }
        .scrollable-table {
            max-height: 600px;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
        }
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-top: 16px;
        }
        .chart-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
        }
        .chart-box h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #374151;
            margin: 0 0 12px;
        }
        .chart-box canvas {
            max-height: 320px;
        }
        @media print {
            .actions { display: none; }
            .chart-box { break-inside: avoid; }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
</head>
<body>
    <div class="container">
        @include('trello.partials.report-library-notice')

        <h1>{{ $boardReport->board_name }}</h1>
        <p class="subtitle">Report generated at: {{ $boardReport->generated_at->format('Y-m-d H:i:s') }}</p>

        @if(!empty($report['filters_applied']) && (isset($report['filters_applied']['date_from']) || isset($report['filters_applied']['date_to']) || !empty($report['filters_applied']['assignees']) || !empty($report['filters_applied']['lists']) || !empty($report['filters_applied']['date_completed_only'])))
            <div class="filters-applied">
                <strong>Filters Applied:</strong>
                @if(!empty($report['filters_applied']['date_from']))
                    <span>From: {{ $report['filters_applied']['date_from'] }}</span>
                @endif
                @if(!empty($report['filters_applied']['date_to']))
                    <span>To: {{ $report['filters_applied']['date_to'] }}</span>
                @endif
                @if(!empty($report['filters_applied']['date_from']) || !empty($report['filters_applied']['date_to']))
                    @if(!empty($report['filters_applied']['date_completed_only']))
                        <span>Date range: <strong>Date Completed</strong> field only</span>
                    @else
                        <span>Date range: <strong>broad</strong> (due / activity / creation / Date Completed)</span>
                    @endif
                @endif
                @if(!empty($report['filters_applied']['assignees']))
                    <span>Assignees: {{ count($report['filters_applied']['assignees']) }} selected</span>
                @endif
                @if(!empty($report['filters_applied']['lists']))
                    <span>Lists: {{ count($report['filters_applied']['lists']) }} selected</span>
                @endif
            </div>
        @endif

        <div class="actions">
            @if($boardReport->exists)
                <a href="{{ route('trello.report.csv', $boardReport) }}" class="btn btn-primary">
                    Export as CSV
                </a>
            @endif
            <button type="button" class="btn btn-primary" onclick="window.print()">Print / PDF</button>
            <a href="{{ route('trello.report.filter', $boardReport->board_id) }}" class="btn btn-secondary">
                Generate New Report
            </a>
            @if($boardReport->exists)
                <a href="{{ route('trello.saved-reports') }}" class="btn btn-secondary">Saved reports</a>
            @endif
            <a href="{{ route('trello.board.dashboard', $boardReport->board_id) }}" class="btn btn-secondary">
                Back to board
            </a>
        </div>

        <div class="section">
            <h2 class="section-title">Summary Statistics</h2>
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-value">{{ $report['total_cards'] }}</div>
                    <div class="stat-label">Total Cards</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">{{ $report['total_lists'] }}</div>
                    <div class="stat-label">Total Lists</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-value">{{ $report['total_points'] ?? 0 }}</div>
                    <div class="stat-label">Total Points</div>
                </div>
                <div class="stat-card orange">
                    <div class="stat-value">{{ count($report['members'] ?? []) }}</div>
                    <div class="stat-label">Team Members</div>
                </div>
            </div>
        </div>

        @php
            $statusChartLabels = [];
            $statusChartCounts = [];
            $statusColors = [];
            $colorMap = [
                'completed' => '#10b981',
                'in_progress' => '#2563eb',
                'todo' => '#f59e0b',
                'other' => '#9ca3af',
            ];
            foreach ($report['status_breakdown'] ?? [] as $status => $count) {
                if ($status === 'total') {
                    continue;
                }
                $statusChartLabels[] = ucfirst(str_replace('_', ' ', $status));
                $statusChartCounts[] = (int) $count;
                $statusColors[] = $colorMap[$status] ?? '#6b7280';
            }
            $memberChartLabels = [];
            $memberChartPoints = [];
            foreach ($report['member_stats'] ?? [] as $stats) {
                $memberChartLabels[] = $stats['name'] ?? 'Member';
                $memberChartPoints[] = round((float) ($stats['total_points'] ?? 0), 2);
            }
            $listChartLabels = [];
            $listChartPoints = [];
            $listSorted = collect($report['cards_by_list'] ?? [])->sortByDesc(function ($l) {
                return $l['total_points'] ?? 0;
            })->take(15);
            foreach ($listSorted as $listData) {
                $listChartLabels[] = \Illuminate\Support\Str::limit($listData['list_name'] ?? 'List', 36);
                $listChartPoints[] = round((float) ($listData['total_points'] ?? 0), 2);
            }
        @endphp

        <div class="section">
            <h2 class="section-title">Charts</h2>
            <div class="charts-grid">
                <div class="chart-box">
                    <h3>Cards by status</h3>
                    <canvas id="chartBoardStatus" aria-label="Status breakdown chart" role="img"></canvas>
                </div>
                @if(!empty($memberChartLabels))
                <div class="chart-box">
                    <h3>Story points by member</h3>
                    <canvas id="chartBoardMembers" aria-label="Member points chart" role="img"></canvas>
                </div>
                @endif
                @if(!empty($listChartLabels))
                <div class="chart-box" style="grid-column: 1 / -1;">
                    <h3>Story points by list (top 15)</h3>
                    <canvas id="chartBoardLists" aria-label="Points by list chart" role="img"></canvas>
                </div>
                @endif
            </div>
        </div>

        <div class="section">
            <h2 class="section-title">Status Breakdown</h2>
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Count</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['status_breakdown'] as $status => $count)
                        @if($status !== 'total')
                            <tr>
                                <td>
                                    <span class="status-badge status-{{ str_replace('_', '-', $status) }}">
                                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                                    </span>
                                </td>
                                <td><strong>{{ $count }}</strong></td>
                                <td>{{ $report['total_cards'] > 0 ? number_format(($count / $report['total_cards']) * 100, 1) : 0 }}%</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        @if(!empty($report['member_stats']))
        <div class="section">
            <h2 class="section-title">Member Statistics
                @if(!empty($report['filters_applied']['assignees']))
                    <small style="font-size: 0.875rem; font-weight: normal; color: #6b7280;">(Selected Assignees Only)</small>
                @endif
            </h2>
            <table>
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Cards Assigned</th>
                        <th>Total Points</th>
                        <th>Average Points per Card</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['member_stats'] as $memberId => $stats)
                        <tr>
                            <td><strong>{{ $stats['name'] }}</strong></td>
                            <td>{{ $stats['card_count'] }}</td>
                            <td>
                                @if($stats['total_points'] > 0)
                                    <span class="points-badge">{{ $stats['total_points'] }} pts</span>
                                @else
                                    <span style="color: #9ca3af;">0</span>
                                @endif
                            </td>
                            <td>
                                @if($stats['card_count'] > 0)
                                    {{ number_format($stats['total_points'] / $stats['card_count'], 1) }}
                                @else
                                    <span style="color: #9ca3af;">0.0</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <div class="section">
            <h2 class="section-title">Cards by List</h2>
            <table>
                <thead>
                    <tr>
                        <th>List Name</th>
                        <th>Card Count</th>
                        <th>Total Points</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['cards_by_list'] as $listName => $listData)
                        <tr>
                            <td><strong>{{ $listData['list_name'] }}</strong></td>
                            <td>{{ $listData['card_count'] }}</td>
                            <td>
                                @if($listData['total_points'] > 0)
                                    <span class="points-badge">{{ $listData['total_points'] }} pts</span>
                                @else
                                    <span style="color: #9ca3af;">0</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="section">
            <h2 class="section-title">Detailed Card Information</h2>
            <div class="scrollable-table">
                <table>
                    <thead>
                        <tr>
                            <th>Card Name</th>
                            <th>List</th>
                            <th>Assignees</th>
                            <th>Points</th>
                            <th>Labels</th>
                            <th>Due Date</th>
                            <th>Date Completed</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['cards'] as $card)
                            @php
                                $normalizedListName = strtolower(trim($card['list_name']));
                                $explicitCompletedLists = array_merge([
                                    'for dev deployment/review (tiger/jan review)',
                                    'for dev deployment/review',
                                    'on dev environment',
                                    'on staging / demo to po',
                                    'on live',
                                    'done / archive',
                                    'done/archive',
                                    'done/archived',
                                    'archive done',
                                ], \App\Support\TeamAccomplishmentLists::completedSprintNormalizedNames());
                                $explicitInProgressLists = [
                                    'in dev',
                                ];
                                $explicitTodoLists = [
                                    'current sprint',
                                ];

                                if (in_array($normalizedListName, $explicitCompletedLists, true) ||
                                    // Handles variants like "On Live🎉"
                                    str_starts_with($normalizedListName, 'on live') ||
                                    str_contains($normalizedListName, 'for dev deployment/review') ||
                                    str_contains($normalizedListName, 'for staging deployment/review') ||
                                    strpos($normalizedListName, 'done') !== false ||
                                    strpos($normalizedListName, 'complete') !== false) {
                                    $statusClass = 'completed';
                                    $statusLabel = 'Completed';
                                } elseif (in_array($normalizedListName, $explicitInProgressLists, true) ||
                                          strpos($normalizedListName, 'progress') !== false ||
                                          strpos($normalizedListName, 'doing') !== false) {
                                    $statusClass = 'in-progress';
                                    $statusLabel = 'In Progress';
                                } elseif (in_array($normalizedListName, $explicitTodoLists, true) ||
                                          strpos($normalizedListName, 'todo') !== false ||
                                          strpos($normalizedListName, 'backlog') !== false) {
                                    $statusClass = 'todo';
                                    $statusLabel = 'Todo';
                                } else {
                                    $statusClass = 'other';
                                    $statusLabel = 'Other';
                                }
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $card['name'] }}</strong>
                                    @if(!empty($card['description']))
                                        <br><small style="color: #6b7280;">{{ Str::limit($card['description'], 50) }}</small>
                                    @endif
                                </td>
                                <td>{{ $card['list_name'] }}</td>
                                <td>
                                    @if(!empty($card['members']))
                                        @foreach($card['members'] as $member)
                                            <span class="member-tag">{{ $member }}</span>
                                        @endforeach
                                    @else
                                        <span style="color: #9ca3af;">Unassigned</span>
                                    @endif
                                </td>
                                <td>
                                    @if($card['points'] > 0)
                                        <span class="points-badge">{{ $card['points'] }} pts</span>
                                    @else
                                        <span style="color: #9ca3af;">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if(!empty($card['labels']))
                                        @foreach($card['labels'] as $label)
                                            <span class="label-tag" style="background-color: #{{ $label['color'] ?? 'gray' }}20; color: #{{ $label['color'] ?? 'gray' }};">
                                                {{ $label['name'] ?? '' }}
                                            </span>
                                        @endforeach
                                    @else
                                        <span style="color: #9ca3af;">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($card['due_date'])
                                        <span style="{{ $card['due_complete'] ? 'text-decoration: line-through; color: #9ca3af;' : '' }}">
                                            {{ \Carbon\Carbon::parse($card['due_date'])->format('M d, Y') }}
                                        </span>
                                        @if($card['due_complete'])
                                            <span class="status-badge status-completed" style="margin-left: 5px;">Done</span>
                                        @endif
                                    @else
                                        <span style="color: #9ca3af;">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if(!empty($card['date_completed']))
                                        {{ \Carbon\Carbon::parse($card['date_completed'])->format('M d, Y') }}
                                    @else
                                        <span style="color: #9ca3af;">-</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="status-badge status-{{ $statusClass }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var commonOpts = {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            };

            var elStatus = document.getElementById('chartBoardStatus');
            if (elStatus && typeof Chart !== 'undefined') {
                new Chart(elStatus, {
                    type: 'doughnut',
                    data: {
                        labels: @json($statusChartLabels),
                        datasets: [{
                            data: @json($statusChartCounts),
                            backgroundColor: @json($statusColors),
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: Object.assign({}, commonOpts, {
                        plugins: {
                            legend: { position: 'bottom' },
                            title: { display: @json(array_sum($statusChartCounts) === 0), text: 'No cards in scope' }
                        }
                    })
                });
            }

            var elMem = document.getElementById('chartBoardMembers');
            if (elMem && typeof Chart !== 'undefined') {
                new Chart(elMem, {
                    type: 'bar',
                    data: {
                        labels: @json($memberChartLabels),
                        datasets: [{
                            label: 'Points',
                            data: @json($memberChartPoints),
                            backgroundColor: 'rgba(37, 99, 235, 0.65)',
                            borderColor: '#2563eb',
                            borderWidth: 1
                        }]
                    },
                    options: Object.assign({}, commonOpts, {
                        scales: {
                            y: { beginAtZero: true, title: { display: true, text: 'Story points' } }
                        }
                    })
                });
            }

            var elLists = document.getElementById('chartBoardLists');
            if (elLists && typeof Chart !== 'undefined') {
                new Chart(elLists, {
                    type: 'bar',
                    data: {
                        labels: @json($listChartLabels),
                        datasets: [{
                            label: 'Points',
                            data: @json($listChartPoints),
                            backgroundColor: 'rgba(5, 150, 105, 0.65)',
                            borderColor: '#059669',
                            borderWidth: 1
                        }]
                    },
                    options: Object.assign({}, commonOpts, {
                        indexAxis: 'y',
                        scales: {
                            x: { beginAtZero: true, title: { display: true, text: 'Story points' } }
                        }
                    })
                });
            }
        });
    </script>
</body>
</html>
