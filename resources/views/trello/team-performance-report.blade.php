<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Team performance — {{ $document['period_label'] }} — {{ $document['board_name'] }}</title>
    <link href="https://fonts.bunny.net/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
    <style>
        :root { --bg:#f3f4f6; --card:#fff; --text:#1f2937; --muted:#6b7280; --border:#e5e7eb; --primary:#2563eb; }
        body { font-family:'Nunito',sans-serif; background:var(--bg); margin:0; color:var(--text); }
        .topbar { background:var(--card); border-bottom:1px solid var(--border); padding:14px 24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; }
        .btn { display:inline-block; padding:10px 18px; border-radius:6px; font-weight:600; font-size:0.95rem; text-decoration:none; border:none; cursor:pointer; }
        .btn-ghost { background:#fff; color:var(--muted); border:1px solid var(--border); }
        .btn-secondary { background:#6b7280; color:#fff; }
        .wrap { max-width:1100px; margin:0 auto; padding:28px 24px 48px; }
        h1 { margin:0 0 6px; font-size:1.75rem; }
        .subtitle { color:var(--muted); margin:0 0 24px; }
        .panel { background:var(--card); border:1px solid var(--border); border-radius:10px; padding:22px; margin-bottom:22px; }
        .muted { color:var(--muted); font-size:0.875rem; }
        table { width:100%; border-collapse:collapse; font-size:0.9rem; }
        th, td { text-align:left; padding:10px 8px; border-bottom:1px solid var(--border); }
        th { color:var(--muted); font-size:0.78rem; text-transform:uppercase; }
        .chart-box { background:#f9fafb; border:1px solid var(--border); border-radius:8px; padding:14px; margin-bottom:16px; }
        .chart-box canvas { max-height:320px; }
        .stat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:12px; margin-bottom:16px; }
        .stat { background:#f9fafb; border:1px solid var(--border); border-radius:8px; padding:14px; text-align:center; }
        .stat strong { display:block; font-size:1.4rem; color:var(--primary); }
        .stat span { font-size:0.8rem; color:var(--muted); }
        .alert-success { background:#d1fae5; color:#065f46; padding:12px; border-radius:6px; margin-bottom:16px; }
        @media print {
            .topbar, .actions { display:none; }
            body { background:#fff; }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <a href="{{ route('trello.board.dashboard', $document['board_id']) }}" class="btn btn-ghost">← {{ $document['board_name'] }}</a>
        <div class="actions" style="display:flex;gap:10px;">
            <a href="{{ route('trello.team', $document['board_id']) }}" class="btn btn-ghost">My team</a>
            <a href="{{ route('trello.saved-reports') }}" class="btn btn-ghost">Saved reports</a>
        </div>
    </header>

    <main class="wrap">
        @if(session('success'))
            <div class="alert-success">{{ session('success') }}</div>
        @endif

        <h1>Team performance report</h1>
        <p class="subtitle">
            {{ $document['board_name'] }} ·
            {{ match($document['mode']) { 'year' => 'Year ' . $document['filter_year'], 'sprint' => $document['period_label'], default => $document['filter_month'] } }}
            · saved {{ $boardReport->generated_at->format('M j, Y g:i A') }}
        </p>

        <div class="panel">
            @include('trello.partials.report-library-notice')

            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:12px 14px;margin-bottom:14px;font-size:0.875rem;color:#1e40af;">
                Snapshot of accomplishment metrics for <strong>{{ count($document['team_member_ids'] ?? []) }}</strong> team member(s).
                Lists: <strong>{{ \App\Support\TeamAccomplishmentLists::labelsSentence() }}</strong>.
            </div>
            <p class="muted" style="margin:0 0 16px;">Standards at save time: {{ $document['standards_summary'] ?? '—' }}</p>

            @include('trello.partials.team-performance-results', [
                'mode' => $document['mode'],
                'performance' => $document['performance'] ?? null,
                'yearData' => $document['year_data'] ?? null,
                'teamMemberIds' => $document['team_member_ids'] ?? [],
            ])
        </div>
    </main>
</body>
</html>
