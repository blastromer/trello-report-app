<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Trello Boards - Trello Report App</title>
    <link href="https://fonts.bunny.net/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1f2937;
            margin-bottom: 30px;
            font-size: 2rem;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .boards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .board-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            background: #f9fafb;
            transition: all 0.3s ease;
        }
        .board-card:hover {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .board-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 10px;
        }
        .board-link {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 16px;
            background-color: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: background-color 0.3s;
        }
        .board-link:hover {
            background-color: #1d4ed8;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1 style="margin: 0;">Trello Boards</h1>
            <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
                @csrf
                <button type="submit" style="padding: 8px 16px; background-color: #6b7280; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">
                    Logout
                </button>
            </form>
        </div>

        @if(session('error'))
            <div class="alert alert-error">
                {{ session('error') }}
            </div>
        @endif

        @if(empty($boards))
            <div class="empty-state">
                <p>No boards found. Please check your Trello API credentials.</p>
            </div>
        @else
            <div class="boards-grid">
                @foreach($boards as $board)
                    <div class="board-card">
                        <div class="board-name">{{ $board['name'] }}</div>
                        <p style="color: #6b7280; font-size: 0.875rem; margin: 10px 0;">
                            ID: {{ $board['id'] }}
                        </p>
                        <a href="{{ route('trello.report.filter', $board['id']) }}" class="board-link">
                            Generate Report
                        </a>
                        <a href="{{ route('trello.accountability.form', $board['id']) }}" class="board-link" style="margin-left: 8px; background-color: #059669;">
                            Accountability Report
                        </a>
                        <a href="{{ route('trello.kpi.form', $board['id']) }}" class="board-link" style="margin-left: 8px; background-color: #7c3aed;">
                            Individual KPI
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</body>
</html>
