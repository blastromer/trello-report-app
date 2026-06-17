<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Filter Report - {{ $boardName }}</title>
    <link href="https://fonts.bunny.net/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
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
        .form-group {
            margin-bottom: 25px;
        }
        label {
            display: block;
            color: #374151;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.875rem;
        }
        input[type="date"],
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        input[type="date"]:focus,
        select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .checkbox-group {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 10px;
        }
        .checkbox-item {
            padding: 8px;
            display: flex;
            align-items: center;
        }
        .checkbox-item input {
            margin-right: 8px;
        }
        .checkbox-item label {
            margin: 0;
            font-weight: normal;
            cursor: pointer;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
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
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        .info-box {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            color: #1e40af;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Generate Report: {{ $boardName }}</h1>
        <p class="subtitle">Configure filters for your report (all fields are optional)</p>

        <div class="info-box">
            <strong>Note:</strong> Leave filters empty to generate a report for all cards. Points are extracted from labels (e.g., "5pts", "10 points") or card names (e.g., "[5pts] Task name").
        </div>

        <form method="POST" action="{{ route('trello.report', $boardId) }}">
            @csrf

            <div class="form-group">
                <label for="date_from">Date From</label>
                <input type="date" id="date_from" name="date_from" value="{{ old('date_from') }}">
                <small style="color: #6b7280; font-size: 0.875rem;">With <strong>Date Completed only</strong> (default below), only cards whose Trello <strong>Date Completed</strong> field is between From and To are included—not due date, last activity, or creation time.</small>
            </div>

            <div class="form-group">
                <label for="date_to">Date To</label>
                <input type="date" id="date_to" name="date_to" value="{{ old('date_to') }}">
            </div>

            <div class="form-group">
                <div class="checkbox-item" style="border: 1px solid #d1d5db; border-radius: 6px; padding: 12px;">
                    <input type="checkbox" id="date_filter_broad" name="date_filter_broad" value="1"
                        {{ old('date_filter_broad') ? 'checked' : '' }}>
                    <label for="date_filter_broad" style="font-weight: 600;">Broad date matching (not recommended)</label>
                </div>
                <small style="color: #6b7280; font-size: 0.875rem; display: block; margin-top: 8px;">
                    <strong>Default:</strong> From/To use only the <strong>Date Completed</strong> custom field. Cards without that field are excluded. Check the box above only if you also want cards that match via due date, last activity, or creation time.
                </small>
            </div>

            @if(!empty($members))
            <div class="form-group">
                <label>Assignees (select your team or specific members)</label>
                @include('trello.partials.team-member-checkboxes', [
                    'members' => $members,
                    'boardId' => $boardId,
                    'defaultAssignees' => $defaultAssignees ?? [],
                    'checkboxPrefix' => 'member',
                ])
                <small style="color: #6b7280; font-size: 0.875rem;">Show only cards assigned to selected members. Leave all unchecked for everyone.</small>
            </div>
            @endif

            @if(!empty($lists))
            <div class="form-group">
                <label>Lists / Columns (Select one or more)</label>
                <div class="checkbox-group">
                    @foreach($lists as $list)
                        <div class="checkbox-item">
                            <input
                                type="checkbox"
                                id="list_{{ $list['id'] }}"
                                name="lists[]"
                                value="{{ $list['id'] }}"
                                {{ in_array($list['id'], old('lists', [])) ? 'checked' : '' }}
                            >
                            <label for="list_{{ $list['id'] }}">{{ $list['name'] ?? 'Unnamed list' }}</label>
                        </div>
                    @endforeach
                </div>
                <small style="color: #6b7280; font-size: 0.875rem;">Show only cards that are in the selected lists / columns</small>
            </div>
            @endif

            @include('trello.partials.save-report-option')

            <div class="actions">
                <button type="submit" class="btn btn-primary">Generate Report</button>
                <a href="{{ route('trello.board.dashboard', $boardId) }}" class="btn btn-secondary">Back to board</a>
            </div>
        </form>
    </div>
</body>
</html>
