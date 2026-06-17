<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Choose boards — Trello Report App</title>
    <link href="https://fonts.bunny.net/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Nunito', sans-serif; background: #f3f4f6; margin: 0; padding: 20px; }
        .container { max-width: 720px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h1 { color: #1f2937; margin: 0 0 8px; font-size: 1.75rem; }
        .subtitle { color: #6b7280; margin: 0 0 24px; }
        .alert { padding: 12px 14px; border-radius: 6px; margin-bottom: 18px; font-size: 0.9rem; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .panel { border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 20px; background: #f9fafb; }
        .panel label { display: flex; align-items: flex-start; gap: 10px; cursor: pointer; font-weight: 600; color: #374151; }
        .panel small { display: block; margin: 8px 0 0 28px; color: #6b7280; font-weight: 400; }
        .board-list { max-height: 420px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 8px; }
        .board-item { display: flex; align-items: flex-start; gap: 10px; padding: 10px 8px; border-bottom: 1px solid #f3f4f6; }
        .board-item:last-child { border-bottom: none; }
        .board-item label { margin: 0; font-weight: 400; cursor: pointer; flex: 1; }
        .board-item .id { display: block; font-size: 0.8rem; color: #9ca3af; margin-top: 2px; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 22px; }
        .btn { padding: 11px 18px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.95rem; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-secondary { background: #6b7280; color: #fff; }
        .btn-ghost { background: #fff; color: #374151; border: 1px solid #d1d5db; }
        .toolbar { display: flex; gap: 10px; margin-bottom: 10px; flex-wrap: wrap; }
        .toolbar button { background: none; border: none; color: #2563eb; font-weight: 600; cursor: pointer; padding: 0; font-size: 0.875rem; }
        .toolbar button:hover { text-decoration: underline; }
        #board-list-wrap.is-disabled { opacity: 0.45; pointer-events: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Choose boards to show</h1>
        <p class="subtitle">Pick which Trello boards appear on your boards page. This is saved per account.</p>

        @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('trello.boards.settings.save') }}">
            @csrf

            <div class="panel">
                <label>
                    <input type="checkbox" id="show_all" name="show_all" value="1" {{ old('show_all', $showAll) ? 'checked' : '' }}>
                    <span>Show all boards from Trello</span>
                </label>
                <small>When checked, every board you can access in Trello is listed (default).</small>
            </div>

            <div id="board-list-wrap" class="{{ old('show_all', $showAll) ? 'is-disabled' : '' }}">
                <div class="toolbar">
                    <button type="button" id="select-all">Select all</button>
                    <span style="color:#d1d5db;">|</span>
                    <button type="button" id="select-none">Clear all</button>
                </div>
                <div class="board-list">
                    @forelse($allBoards as $board)
                        @php
                            $bid = $board['id'];
                            $checked = in_array($bid, old('board_ids', $selectedIds), true);
                        @endphp
                        <div class="board-item">
                            <input type="checkbox" id="b_{{ $bid }}" name="board_ids[]" value="{{ $bid }}" class="js-board-cb" {{ $checked ? 'checked' : '' }}>
                            <label for="b_{{ $bid }}">
                                {{ $board['name'] ?? 'Unnamed board' }}
                                <span class="id">{{ $bid }}</span>
                            </label>
                        </div>
                    @empty
                        <p style="padding:16px;color:#6b7280;margin:0;">No boards returned from Trello.</p>
                    @endforelse
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Save selection</button>
                <a href="{{ route('trello.boards') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var showAll = document.getElementById('show_all');
            var wrap = document.getElementById('board-list-wrap');
            var boxes = document.querySelectorAll('.js-board-cb');

            function syncDisabled() {
                if (!showAll || !wrap) return;
                wrap.classList.toggle('is-disabled', showAll.checked);
            }

            if (showAll) {
                showAll.addEventListener('change', syncDisabled);
                syncDisabled();
            }

            document.getElementById('select-all')?.addEventListener('click', function () {
                boxes.forEach(function (cb) { cb.checked = true; });
                if (showAll) showAll.checked = false;
                syncDisabled();
            });
            document.getElementById('select-none')?.addEventListener('click', function () {
                boxes.forEach(function (cb) { cb.checked = false; });
                if (showAll) showAll.checked = false;
                syncDisabled();
            });
        });
    </script>
</body>
</html>
