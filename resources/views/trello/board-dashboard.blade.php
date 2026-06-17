<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $boardName }} — Trello Report App</title>
    <link href="https://fonts.bunny.net/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f3f4f6;
            --card: #ffffff;
            --text: #1f2937;
            --muted: #6b7280;
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --accent: #059669;
            --purple: #7c3aed;
            --border: #e5e7eb;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Nunito', sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            min-height: 100vh;
        }
        .topbar {
            background: var(--card);
            border-bottom: 1px solid var(--border);
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }
        .topbar-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .btn {
            display: inline-block;
            padding: 10px 18px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-secondary { background: #6b7280; color: #fff; }
        .btn-secondary:hover { background: #4b5563; }
        .btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--border); }
        .btn-ghost:hover { background: #f9fafb; color: var(--text); }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 32px 24px 48px; }
        .hero {
            background: linear-gradient(135deg, #1e40af 0%, #065f46 100%);
            color: #fff;
            border-radius: 12px;
            padding: 36px 32px;
            margin-bottom: 28px;
        }
        .hero h1 { margin: 0 0 8px; font-size: 1.85rem; font-weight: 800; }
        .hero-meta { margin: 0; opacity: 0.9; font-size: 0.95rem; }
        .hero-actions { margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap; }
        .hero .btn-ghost { border-color: rgba(255,255,255,0.5); color: #fff; }
        .hero .btn-ghost:hover { background: rgba(255,255,255,0.12); }
        h2.section-title { font-size: 1.1rem; margin: 0 0 14px; }
        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }
        .report-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 22px;
            text-decoration: none;
            color: inherit;
            transition: box-shadow 0.2s, transform 0.2s;
            display: block;
        }
        .report-card:hover {
            box-shadow: 0 6px 16px rgba(0,0,0,0.08);
            transform: translateY(-2px);
        }
        .report-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            margin-bottom: 14px;
        }
        .icon-blue { background: #dbeafe; }
        .icon-green { background: #d1fae5; }
        .icon-purple { background: #ede9fe; }
        .report-card h3 { margin: 0 0 8px; font-size: 1.1rem; }
        .report-card p { margin: 0; color: var(--muted); font-size: 0.9rem; line-height: 1.5; }
        .report-card .cta {
            display: inline-block;
            margin-top: 14px;
            font-weight: 700;
            font-size: 0.9rem;
        }
        .cta-blue { color: var(--primary); }
        .cta-green { color: var(--accent); }
        .cta-purple { color: var(--purple); }
        .panel {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 22px 24px;
            margin-bottom: 22px;
        }
        .panel-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }
        .panel-head h2 { margin: 0; font-size: 1.1rem; }
        .link-sm { color: var(--primary); font-weight: 600; font-size: 0.875rem; text-decoration: none; }
        .link-sm:hover { text-decoration: underline; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid var(--border); }
        th { color: var(--muted); font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.03em; }
        .muted { color: var(--muted); }
        .empty { color: var(--muted); padding: 12px 0; }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .badge-board { background: #dbeafe; color: #1e40af; }
        .badge-accountability { background: #d1fae5; color: #065f46; }
        .badge-kpi { background: #ede9fe; color: #5b21b6; }
        .badge-team { background: #ffedd5; color: #c2410c; }
        .settings-list { list-style: none; margin: 0; padding: 0; }
        .settings-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 14px 0;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
        }
        .settings-list li:last-child { border-bottom: none; }
        .settings-list strong { display: block; margin-bottom: 2px; }
        .settings-list span { color: var(--muted); font-size: 0.875rem; }
        .alert-info {
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <header class="topbar">
        <a href="{{ route('trello.boards') }}" class="btn btn-ghost">← All boards</a>
        <div class="topbar-actions">
            <a href="{{ route('trello.saved-reports') }}" class="btn btn-ghost">Saved reports</a>
            <a href="{{ route('home') }}" class="btn btn-ghost">Dashboard</a>
            <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                @csrf
                <button type="submit" class="btn btn-secondary">Logout</button>
            </form>
        </div>
    </header>

    <main class="wrap">
        <section class="hero">
            <h1>{{ $boardName }}</h1>
            <p class="hero-meta">Board ID: {{ $boardId }}</p>
            <div class="hero-actions">
                @if($trelloUrl)
                    <a href="{{ $trelloUrl }}" target="_blank" rel="noopener" class="btn btn-ghost">Open in Trello ↗</a>
                @endif
            </div>
        </section>

        <h2 class="section-title">My team</h2>
        <div class="panel" style="margin-bottom:28px;">
            <div class="panel-head">
                <h2 style="margin:0;font-size:1.05rem;">Team members &amp; performance</h2>
                <a href="{{ route('trello.team', $boardId) }}" class="link-sm">Open team hub →</a>
            </div>
            @if(!empty($teamMemberIds))
                <p class="muted" style="margin:0 0 10px;"><strong>{{ count($teamMemberIds) }}</strong> saved teammate(s) — pre-selected on all reports. Monitor accomplishment by month, year, or sprint.</p>
            @else
                <p class="muted" style="margin:0;">No team saved yet. Pick your teammates once; they will be filtered automatically on every report.</p>
            @endif
            <a href="{{ route('trello.team', $boardId) }}" class="btn btn-ghost" style="margin-top:10px;display:inline-block;">Manage team &amp; charts</a>
        </div>

        <h2 class="section-title">Reports</h2>
        <div class="report-grid">
            <a href="{{ route('trello.report.filter', $boardId) }}" class="report-card">
                <div class="report-icon icon-blue">📊</div>
                <h3>Board report</h3>
                <p>Filter by date range, assignees, and lists. Charts, member stats, CSV export, and card tables with Date Completed.</p>
                <span class="cta cta-blue">Generate report →</span>
            </a>
            <a href="{{ route('trello.accountability.form', $boardId) }}" class="report-card">
                <div class="report-icon icon-green">📋</div>
                <h3>Accountability report</h3>
                <p>Monthly narrative plus sprint accomplishment rates. Story points completed vs. total for each team member.</p>
                <span class="cta cta-green">Create report →</span>
            </a>
            <a href="{{ route('trello.kpi.form', $boardId) }}" class="report-card">
                <div class="report-icon icon-purple">🎯</div>
                <h3>Individual KPI</h3>
                <p>Productivity, efficiency, quality, collaboration, and professionalism across sprint windows. HTML and Word export.</p>
                <span class="cta cta-purple">Create report →</span>
            </a>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Saved reports for this board</h2>
                <a href="{{ route('trello.saved-reports') }}" class="link-sm">All saved reports →</a>
            </div>
            @if($recentReports->isEmpty())
                <div class="empty">No saved reports yet. Pick a report type above to get started.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Generated</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentReports as $report)
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
                                <td><span class="badge {{ $badgeClass }}">{{ $report->typeLabel() }}</span></td>
                                <td class="muted">{{ $report->generated_at->format('M j, Y g:i A') }}</td>
                                <td style="display:flex;flex-wrap:wrap;gap:8px;">
                                    <a href="{{ $report->viewUrl() }}" class="link-sm">View</a>
                                    @if($type === 'board')
                                        <a href="{{ route('trello.report.csv', $report) }}" class="link-sm">CSV</a>
                                    @elseif($type === 'accountability')
                                        <a href="{{ route('trello.accountability.docx', $report) }}" class="link-sm">Word</a>
                                    @elseif($type !== 'team_performance')
                                        <a href="{{ route('trello.kpi.html', $report) }}" class="link-sm">HTML</a>
                                        <a href="{{ route('trello.kpi.docx', $report) }}" class="link-sm">Word</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Settings</h2>
            </div>
            @if(!$isVisibleOnBoardsPage)
                <div class="alert-info">
                    This board is hidden from your boards list.
                    <a href="{{ route('trello.boards.settings') }}" class="link-sm">Choose boards</a> to show it on the main list.
                </div>
            @endif
            <ul class="settings-list">
                <li>
                    <div>
                        <strong>Performance standards</strong>
                        <span>Accountability baseline: <strong>{{ ($performanceStandards ?? \App\Support\PerformanceStandards::defaults())->summaryLine() }}</strong></span>
                    </div>
                    <a href="{{ route('trello.accountability.form', $boardId) }}" class="btn btn-ghost">Manage standards</a>
                </li>
                <li>
                    <div>
                        <strong>Boards list visibility</strong>
                        <span>Control which boards appear when you browse all boards.</span>
                    </div>
                    <a href="{{ route('trello.boards.settings') }}" class="btn btn-ghost">Choose boards</a>
                </li>
                @if($trelloUrl)
                    <li>
                        <div>
                            <strong>Trello board</strong>
                            <span>Open the live board in Trello to manage cards and lists.</span>
                        </div>
                        <a href="{{ $trelloUrl }}" target="_blank" rel="noopener" class="btn btn-ghost">Open in Trello ↗</a>
                    </li>
                @endif
            </ul>
        </div>
    </main>
</body>
</html>
