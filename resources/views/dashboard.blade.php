<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Trello Report App') }}</title>
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
            --accent-hover: #047857;
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
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            font-size: 1.15rem;
            color: var(--text);
            text-decoration: none;
        }
        .brand-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: linear-gradient(135deg, #2563eb, #059669);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.1rem;
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
            padding: 40px 36px;
            margin-bottom: 32px;
        }
        .hero h1 { margin: 0 0 12px; font-size: 2rem; font-weight: 800; line-height: 1.2; }
        .hero p { margin: 0; font-size: 1.05rem; opacity: 0.92; max-width: 640px; line-height: 1.55; }
        .hero-actions { margin-top: 24px; display: flex; gap: 12px; flex-wrap: wrap; }
        .hero .btn-primary { background: #fff; color: #1e40af; }
        .hero .btn-primary:hover { background: #f0f9ff; }
        .hero .btn-ghost { border-color: rgba(255,255,255,0.5); color: #fff; }
        .hero .btn-ghost:hover { background: rgba(255,255,255,0.1); }
        h2.section-title {
            font-size: 1.15rem;
            margin: 0 0 16px;
            color: var(--text);
        }
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 36px;
        }
        .feature-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 22px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        .feature-card h3 { margin: 0 0 8px; font-size: 1.05rem; }
        .feature-card p { margin: 0; color: var(--muted); font-size: 0.9rem; line-height: 1.5; }
        .feature-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 14px;
        }
        .icon-blue { background: #dbeafe; }
        .icon-green { background: #d1fae5; }
        .icon-purple { background: #ede9fe; }
        .panel {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
        }
        .panel-head {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .panel-head h2 { margin: 0; font-size: 1.05rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 20px; text-align: left; border-bottom: 1px solid var(--border); font-size: 0.9rem; }
        th { background: #f9fafb; font-weight: 700; color: #374151; }
        tr:last-child td { border-bottom: none; }
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
        .empty { padding: 32px 20px; text-align: center; color: var(--muted); }
        .muted { color: var(--muted); font-size: 0.875rem; }
        .link-sm { color: var(--primary); text-decoration: none; font-weight: 600; font-size: 0.875rem; }
        .link-sm:hover { text-decoration: underline; }
        .user-greeting { color: var(--muted); font-size: 0.9rem; margin-right: 4px; }
    </style>
</head>
<body>
    <header class="topbar">
        <a href="{{ url('/') }}" class="brand">
            <span class="brand-icon">T</span>
            <span>{{ config('app.name', 'Trello Report App') }}</span>
        </a>
        <div class="topbar-actions">
            @auth
                <span class="user-greeting">Hi, {{ auth()->user()->name }}</span>
                <a href="{{ route('trello.saved-reports') }}" class="btn btn-ghost">Saved reports</a>
                <a href="{{ route('trello.boards') }}" class="btn btn-primary">Your boards</a>
                <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn-ghost">Log out</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="btn btn-ghost">Log in</a>
                <a href="{{ route('register') }}" class="btn btn-primary">Register</a>
            @endauth
        </div>
    </header>

    <main class="wrap">
        <section class="hero">
            <h1>Trello reports for your team</h1>
            <p>
                Generate board summaries, accountability reports, and individual KPI scorecards from your Trello boards.
                Filters use the <strong>Date Completed</strong> custom field so sprint and monthly numbers match when work was actually finished.
            </p>
            <div class="hero-actions">
                @auth
                    <a href="{{ route('trello.boards') }}" class="btn btn-primary">Open boards →</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary">Log in to get started</a>
                    <a href="{{ route('register') }}" class="btn btn-ghost">Create account</a>
                @endauth
            </div>
        </section>

        <h2 class="section-title">Report types</h2>
        <div class="features">
            <div class="feature-card">
                <div class="feature-icon icon-blue">📊</div>
                <h3>Board report</h3>
                <p>Filter by date range, assignees, and lists. Charts, member stats, CSV export, and detailed card tables with Date Completed.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon icon-green">📋</div>
                <h3>Accountability report</h3>
                <p>Monthly narrative plus sprint accomplishment rates. Story points completed vs. total for each team member.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon icon-purple">🎯</div>
                <h3>Individual KPI</h3>
                <p>Productivity, efficiency, quality, collaboration, and professionalism (25 pts) across sprint windows. HTML and Word export.</p>
            </div>
        </div>

        @auth
            <div class="panel">
                <div class="panel-head">
                    <h2>Recent reports</h2>
                    <a href="{{ route('trello.saved-reports') }}" class="link-sm">All saved reports →</a>
                </div>
                @if($recentReports->isEmpty())
                    <div class="empty">
                        No saved reports yet. Pick a board and run your first report.
                    </div>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th>Board</th>
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
                                    <td><strong>{{ $report->board_name }}</strong></td>
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
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @else
            <p class="muted" style="text-align:center;margin-top:8px;">
                <a href="{{ route('login') }}" class="link-sm">Sign in</a> to see your recent reports and connect to Trello.
            </p>
        @endauth
    </main>
</body>
</html>
