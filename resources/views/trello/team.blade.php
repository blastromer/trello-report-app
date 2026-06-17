<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My team — {{ $boardName }}</title>
    <link href="https://fonts.bunny.net/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
    <style>
        :root { --bg:#f3f4f6; --card:#fff; --text:#1f2937; --muted:#6b7280; --border:#e5e7eb; --primary:#2563eb; }
        body { font-family:'Nunito',sans-serif; background:var(--bg); margin:0; color:var(--text); }
        .topbar { background:var(--card); border-bottom:1px solid var(--border); padding:14px 24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; }
        .btn { display:inline-block; padding:10px 18px; border-radius:6px; font-weight:600; font-size:0.95rem; text-decoration:none; border:none; cursor:pointer; }
        .btn-primary { background:var(--primary); color:#fff; }
        .btn-secondary { background:#6b7280; color:#fff; }
        .btn-ghost { background:#fff; color:var(--muted); border:1px solid var(--border); }
        .wrap { max-width:1100px; margin:0 auto; padding:28px 24px 48px; }
        h1 { margin:0 0 6px; font-size:1.75rem; }
        .subtitle { color:var(--muted); margin:0 0 24px; }
        .panel { background:var(--card); border:1px solid var(--border); border-radius:10px; padding:22px; margin-bottom:22px; }
        .panel h2 { margin:0 0 14px; font-size:1.1rem; }
        .checkbox-group { max-height:220px; overflow-y:auto; border:1px solid var(--border); border-radius:6px; padding:8px; }
        .checkbox-item { padding:6px; display:flex; align-items:center; gap:8px; }
        .checkbox-item label { margin:0; font-weight:400; cursor:pointer; }
        .alert-success { background:#d1fae5; color:#065f46; padding:12px; border-radius:6px; margin-bottom:16px; }
        .alert-error { background:#fee2e2; color:#991b1b; padding:12px; border-radius:6px; margin-bottom:16px; }
        .mode-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
        .mode-tabs a { padding:8px 14px; border-radius:6px; text-decoration:none; font-weight:600; font-size:0.9rem; border:1px solid var(--border); color:var(--text); background:#f9fafb; }
        .mode-tabs a.active { background:var(--primary); color:#fff; border-color:var(--primary); }
        .filter-row { display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; margin-bottom:16px; }
        .filter-row label { display:block; font-size:0.8rem; font-weight:600; margin-bottom:4px; color:var(--muted); }
        .filter-row input, .filter-row select { padding:9px 12px; border:1px solid var(--border); border-radius:6px; }
        table { width:100%; border-collapse:collapse; font-size:0.9rem; }
        th, td { text-align:left; padding:10px 8px; border-bottom:1px solid var(--border); }
        th { color:var(--muted); font-size:0.78rem; text-transform:uppercase; }
        .chart-box { background:#f9fafb; border:1px solid var(--border); border-radius:8px; padding:14px; margin-bottom:16px; }
        .chart-box canvas { max-height:320px; }
        .stat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:12px; margin-bottom:16px; }
        .stat { background:#f9fafb; border:1px solid var(--border); border-radius:8px; padding:14px; text-align:center; }
        .stat strong { display:block; font-size:1.4rem; color:var(--primary); }
        .stat span { font-size:0.8rem; color:var(--muted); }
        .muted { color:var(--muted); font-size:0.875rem; }
        .toolbar { display:flex; gap:8px; flex-wrap:wrap; margin-top:12px; }
        .btn-save { background:#059669; color:#fff; }
    </style>
</head>
<body>
    <header class="topbar">
        <a href="{{ route('trello.board.dashboard', $boardId) }}" class="btn btn-ghost">← {{ $boardName }}</a>
        <a href="{{ route('trello.saved-reports') }}" class="btn btn-ghost">Saved reports</a>
    </header>

    <main class="wrap">
        <h1>My team</h1>
        <p class="subtitle">{{ $boardName }} — save teammates once; they are pre-selected on every report.</p>

        @if(session('success'))
            <div class="alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert-error">{{ session('error') }}</div>
        @endif

        <div class="panel">
            <h2>1. Select &amp; save your team</h2>
            <p class="muted" style="margin:0 0 12px;">Check the people on your team. Board, accountability, and KPI reports will pre-select them automatically.</p>
            <form method="POST" action="{{ route('trello.team.save', $boardId) }}">
                @csrf
                @include('trello.partials.team-member-checkboxes', [
                    'members' => $members,
                    'boardId' => $boardId,
                    'defaultAssignees' => $teamMemberIds,
                    'oldKey' => 'member_ids',
                    'inputName' => 'member_ids[]',
                    'checkboxPrefix' => 'team',
                    'required' => false,
                ])
                <div class="toolbar">
                    <button type="button" class="btn btn-ghost" onclick="document.querySelectorAll('input[name=\'member_ids[]\']').forEach(function(c){c.checked=true})">Select all</button>
                    <button type="button" class="btn btn-ghost" onclick="document.querySelectorAll('input[name=\'member_ids[]\']').forEach(function(c){c.checked=false})">Clear all</button>
                    <button type="submit" class="btn btn-save">Save my team</button>
                </div>
            </form>
        </div>

        <div class="panel">
            <h2>2. Monitor team performance</h2>
            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:12px 14px;margin-bottom:14px;font-size:0.875rem;color:#1e40af;">
                <strong>Accomplishment by team</strong> includes only cards in:
                <strong>{{ \App\Support\TeamAccomplishmentLists::labelsSentence() }}</strong>.
                Rate = <strong>{{ \App\Support\TeamAccomplishmentLists::completedSprintLabelsSentence() }}</strong> points ÷ total points in those lists (In Development + Blocked count toward total, not completed).
                Filtered to the selected period: completed sprint columns need <strong>Date Completed</strong> in range;
                <strong>In Development</strong> and <strong>Blocked</strong> need activity in range.
            </div>
            <p class="muted" style="margin:0 0 12px;">Standards: {{ $standards->summaryLine() }}</p>

            @if(empty($teamMemberIds))
                <p class="muted">Save your team above to see performance charts.</p>
            @else
                <div class="mode-tabs">
                    <a href="{{ route('trello.team', ['boardId' => $boardId, 'mode' => 'month', 'month' => $filterMonth]) }}" class="{{ $mode === 'month' ? 'active' : '' }}">Month</a>
                    <a href="{{ route('trello.team', ['boardId' => $boardId, 'mode' => 'year', 'year' => $filterYear]) }}" class="{{ $mode === 'year' ? 'active' : '' }}">Year</a>
                    <a href="{{ route('trello.team', ['boardId' => $boardId, 'mode' => 'sprint', 'sprint_label' => $filterSprintLabel, 'date_from' => $filterDateFrom, 'date_to' => $filterDateTo]) }}" class="{{ $mode === 'sprint' ? 'active' : '' }}">Sprint / custom</a>
                </div>

                <form method="GET" action="{{ route('trello.team', $boardId) }}" class="filter-row">
                    <input type="hidden" name="mode" value="{{ $mode }}">
                    <input type="hidden" name="apply_filters" value="1">
                    @if($mode === 'month')
                        <div>
                            <label>Month</label>
                            <input type="month" name="month" value="{{ $filterMonth }}">
                        </div>
                    @elseif($mode === 'year')
                        <div>
                            <label>Year</label>
                            <input type="number" name="year" value="{{ $filterYear }}" min="2000" max="2100" style="width:100px;">
                        </div>
                    @else
                        <div>
                            <label>Sprint label</label>
                            <input type="text" name="sprint_label" value="{{ $filterSprintLabel }}" placeholder="Sprint 1">
                        </div>
                        <div>
                            <label>From</label>
                            <input type="date" name="date_from" value="{{ $filterDateFrom }}" required>
                        </div>
                        <div>
                            <label>To</label>
                            <input type="date" name="date_to" value="{{ $filterDateTo }}" required>
                        </div>
                    @endif
                    <div style="margin-bottom:14px;flex:1 1 100%;">
                        @include('trello.partials.save-report-option', [
                            'exportHint' => 'Store this snapshot so you can reopen charts and member tables from Saved reports.',
                        ])
                    </div>
                    <div>
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">Update charts</button>
                    </div>
                </form>

                @if($performanceError)
                    <div class="alert-error">{{ $performanceError }}</div>
                @endif

                @if(!empty($performance['accomplishment_lists_missing']))
                    <div class="alert-error" style="background:#fef3c7;color:#92400e;border-color:#fde68a;">
                        Missing lists on this board: {{ implode(', ', $performance['accomplishment_lists_missing']) }}.
                        Create them in Trello or rename to match exactly.
                    </div>
                @endif

                @include('trello.partials.team-performance-results', [
                    'mode' => $mode,
                    'performance' => $performance,
                    'yearData' => $yearData,
                    'teamMemberIds' => $teamMemberIds,
                ])
            @endif
        </div>
    </main>
</body>
</html>
