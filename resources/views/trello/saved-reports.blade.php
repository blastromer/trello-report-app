<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Saved reports — Trello Report App</title>
    <link href="https://fonts.bunny.net/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #f3f4f6; --card: #fff; --text: #1f2937; --muted: #6b7280; --border: #e5e7eb; --primary: #2563eb; }
        body { font-family: 'Nunito', sans-serif; background: var(--bg); margin: 0; min-height: 100vh; color: var(--text); }
        .topbar { background: var(--card); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .btn { display: inline-block; padding: 10px 18px; border-radius: 6px; font-weight: 600; font-size: 0.95rem; text-decoration: none; border: none; cursor: pointer; }
        .btn-ghost { background: #fff; color: var(--muted); border: 1px solid var(--border); }
        .btn-ghost:hover { background: #f9fafb; color: var(--text); }
        .btn-secondary { background: #6b7280; color: #fff; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 32px 24px 48px; }
        h1 { margin: 0 0 8px; font-size: 1.75rem; }
        .subtitle { color: var(--muted); margin: 0 0 24px; }
        .panel { background: var(--card); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { text-align: left; padding: 12px 16px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        th { background: #f9fafb; color: var(--muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.03em; }
        tr:last-child td { border-bottom: none; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
        .badge-board { background: #dbeafe; color: #1e40af; }
        .badge-accountability { background: #d1fae5; color: #065f46; }
        .badge-kpi { background: #ede9fe; color: #5b21b6; }
        .badge-team { background: #ffedd5; color: #c2410c; }
        .muted { color: var(--muted); }
        .actions-cell { display: flex; flex-wrap: wrap; gap: 8px; }
        .link-sm { color: var(--primary); font-weight: 600; font-size: 0.875rem; text-decoration: none; }
        .link-sm:hover { text-decoration: underline; }
        .empty { padding: 40px 24px; text-align: center; color: var(--muted); }
    </style>
</head>
<body>
    <header class="topbar">
        <a href="{{ route('home') }}" class="btn btn-ghost">← Dashboard</a>
        <div style="display:flex;gap:10px;">
            <a href="{{ route('trello.boards') }}" class="btn btn-ghost">Boards</a>
            <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                @csrf
                <button type="submit" class="btn btn-secondary">Logout</button>
            </form>
        </div>
    </header>
    <main class="wrap">
        <h1>Saved reports</h1>
        <p class="subtitle">Open reports in the browser or export in your preferred format.</p>

        <div class="panel">
            @if($reports->isEmpty())
                <div class="empty">
                    No saved reports yet. Generate a report and leave <strong>Save to my library</strong> checked.
                </div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Board</th>
                            <th>Type</th>
                            <th>Generated</th>
                            <th>View &amp; export</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reports as $report)
                            @php
                                $type = $report->resolvedType();
                                $badgeClass = match ($type) {
                                    'accountability' => 'badge-accountability',
                                    'individual_kpi' => 'badge-kpi',
                                    'team_performance' => 'badge-team',
                                    default => 'badge-board',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $report->board_name }}</strong><br>
                                    <a href="{{ route('trello.board.dashboard', $report->board_id) }}" class="link-sm">Board dashboard</a>
                                </td>
                                <td><span class="badge {{ $badgeClass }}">{{ $report->typeLabel() }}</span></td>
                                <td class="muted">{{ $report->generated_at->format('M j, Y g:i A') }}</td>
                                <td>
                                    <div class="actions-cell">
                                        <a href="{{ $report->viewUrl() }}" class="link-sm">View</a>
                                        @if($type === 'board')
                                            <a href="{{ route('trello.report.csv', $report) }}" class="link-sm">CSV</a>
                                        @elseif($type === 'accountability')
                                            <a href="{{ route('trello.accountability.docx', $report) }}" class="link-sm">Word</a>
                                        @elseif($type !== 'team_performance')
                                            <a href="{{ route('trello.kpi.html', $report) }}" class="link-sm">HTML</a>
                                            <a href="{{ route('trello.kpi.docx', $report) }}" class="link-sm">Word</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </main>
</body>
</html>
