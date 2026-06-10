<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accountability Report — {{ $boardName }}</title>
    <link href="https://fonts.bunny.net/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Nunito', sans-serif; background: #f3f4f6; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h1 { color: #1f2937; margin-bottom: 8px; }
        .subtitle { color: #6b7280; margin-bottom: 24px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; color: #374151; margin-bottom: 6px; font-size: 0.875rem; }
        input[type="text"], input[type="number"], input[type="month"], textarea, select {
            width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem; box-sizing: border-box;
        }
        textarea { min-height: 100px; font-family: inherit; }
        .checkbox-group { max-height: 180px; overflow-y: auto; border: 1px solid #d1d5db; border-radius: 6px; padding: 8px; }
        .checkbox-item { padding: 6px; display: flex; align-items: center; gap: 8px; }
        .checkbox-item label { margin: 0; font-weight: 400; cursor: pointer; }
        .sprint-row { display: grid; grid-template-columns: 1fr 140px 140px; gap: 10px; align-items: end; margin-bottom: 12px; padding: 12px; background: #f9fafb; border-radius: 6px; }
        .sprint-row small { color: #6b7280; grid-column: 1 / -1; }
        .info { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 14px; color: #1e40af; margin-bottom: 20px; font-size: 0.9rem; }
        .actions { display: flex; gap: 10px; margin-top: 24px; flex-wrap: wrap; }
        .btn { padding: 12px 20px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; font-size: 1rem; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-secondary { background: #6b7280; color: #fff; }
        .alert-error { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 16px; }
        h2 { font-size: 1.1rem; color: #1f2937; margin: 28px 0 12px; border-bottom: 1px solid #e5e7eb; padding-bottom: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Team Accountability Report</h1>
        <p class="subtitle">{{ $boardName }}</p>

        @if(session('error'))
            <div class="alert-error">{{ session('error') }}</div>
        @endif

        <div class="info">
            <strong>How it works:</strong> For each sprint, set a date range. Cards are included when their Trello <strong>Date Completed</strong> custom field falls in that range (same as the board report). Cards without Date Completed set are excluded.
            <strong>Accomplishment rate</strong> = story points on <em>completed</em> lists ÷ total points on all cards in that window (for each <em>checked</em> team member only).
            The narrative sections use the <strong>Report month</strong> with the same Date Completed rule.
            Check at least one team member. <strong>Leave any narrative box blank</strong> to auto-generate it from Trello + sprint metrics (you can edit after export).
        </div>

        <form method="POST" action="{{ route('trello.accountability', $boardId) }}">
            @csrf

            <div class="form-group">
                <label for="report_month">Report month</label>
                <input type="month" id="report_month" name="report_month" value="{{ old('report_month', now()->format('Y-m')) }}" required>
            </div>

            <div class="form-group">
                <label for="expectation_threshold">Expectation threshold (%)</label>
                <input type="number" id="expectation_threshold" name="expectation_threshold" min="0" max="100" step="0.01" value="{{ old('expectation_threshold', 70) }}">
                <small style="color:#6b7280;">Overall sprint average below this shows “Below Expectation”.</small>
            </div>

            @if(!empty($members))
            <div class="form-group">
                <label>Team members on this report <span style="color:#b91c1c;">*</span></label>
                <div class="checkbox-group">
                    @foreach($members as $member)
                        <div class="checkbox-item">
                            <input type="checkbox" id="m_{{ $member['id'] }}" name="assignees[]" value="{{ $member['id'] }}"
                                {{ in_array($member['id'], old('assignees', []), true) ? 'checked' : '' }}>
                            <label for="m_{{ $member['id'] }}">{{ $member['fullName'] ?? $member['username'] ?? 'Unknown' }}</label>
                        </div>
                    @endforeach
                </div>
                <small style="color:#6b7280;">Only checked people appear in sprint metrics. Story points on shared cards count only toward checked assignees.</small>
                @error('assignees')
                    <div style="color:#b91c1c;font-size:0.875rem;margin-top:6px;">{{ $message }}</div>
                @enderror
            </div>
            @else
                <p style="color:#b91c1c;">This board has no members to select. Load members from Trello first.</p>
            @endif

            <h2>Sprints</h2>
            @php
                $oldSprints = old('sprints', [
                    ['label' => 'Sprint 1', 'date_from' => '', 'date_to' => ''],
                    ['label' => 'Sprint 2', 'date_from' => '', 'date_to' => ''],
                    ['label' => 'Sprint 3', 'date_from' => '', 'date_to' => ''],
                ]);
            @endphp
            @foreach($oldSprints as $i => $s)
                <div class="sprint-row">
                    <div>
                        <label>Sprint label</label>
                        <input type="text" name="sprints[{{ $i }}][label]" value="{{ $s['label'] ?? 'Sprint ' . ($i + 1) }}" placeholder="Sprint {{ $i + 1 }}">
                    </div>
                    <div>
                        <label>From</label>
                        <input type="date" name="sprints[{{ $i }}][date_from]" value="{{ $s['date_from'] ?? '' }}">
                    </div>
                    <div>
                        <label>To</label>
                        <input type="date" name="sprints[{{ $i }}][date_to]" value="{{ $s['date_to'] ?? '' }}">
                    </div>
                    <small>Skip a row by leaving both dates empty.</small>
                </div>
            @endforeach

            <h2>Narrative (optional — auto if left blank)</h2>
            <p style="color:#6b7280;font-size:0.875rem;margin-top:-8px;">
                <strong>Key / Ongoing:</strong> completed vs in-progress &amp; todo card titles for the report month.<br>
                <strong>Challenges:</strong> sprints below threshold, members below threshold, carryover counts.<br>
                <strong>Plans:</strong> prefixed next actions from in-progress and todo cards.<br>
                <strong>Context:</strong> how metrics were computed + per-sprint summary.<br>
                <strong>Outlook:</strong> short paragraph from sprint results and month status counts.
            </p>

            <div class="form-group">
                <label for="key_accomplishments">Key accomplishments</label>
                <textarea id="key_accomplishments" name="key_accomplishments" placeholder="One line per bullet, or blank for auto from completed cards">{{ old('key_accomplishments') }}</textarea>
            </div>
            <div class="form-group">
                <label for="ongoing_projects">Ongoing projects</label>
                <textarea id="ongoing_projects" name="ongoing_projects" placeholder="One line per bullet, or blank for auto from in-progress / todo cards">{{ old('ongoing_projects') }}</textarea>
            </div>
            <div class="form-group">
                <label for="challenges">Challenges</label>
                <textarea id="challenges" name="challenges" placeholder="Blank for auto (performance vs threshold + carryover)">{{ old('challenges') }}</textarea>
            </div>
            <div class="form-group">
                <label for="plans_next_steps">Plans &amp; next steps</label>
                <textarea id="plans_next_steps" name="plans_next_steps" placeholder="Blank for auto from board in-progress &amp; todo">{{ old('plans_next_steps') }}</textarea>
            </div>
            <div class="form-group">
                <label for="context_interpretation">Context &amp; interpretation</label>
                <textarea id="context_interpretation" name="context_interpretation" placeholder="Blank for auto (sprint summaries + methodology note)">{{ old('context_interpretation') }}</textarea>
            </div>
            <div class="form-group">
                <label for="overall_outlook">Overall outlook</label>
                <textarea id="overall_outlook" name="overall_outlook" placeholder="Blank for auto (trend vs threshold + month totals)">{{ old('overall_outlook') }}</textarea>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Generate accountability report</button>
                <a href="{{ route('trello.kpi.form', $boardId) }}" class="btn btn-secondary">Individual KPI</a>
                <a href="{{ route('trello.report.filter', $boardId) }}" class="btn btn-secondary">Board report filters</a>
                <a href="{{ route('trello.boards') }}" class="btn btn-secondary">Boards</a>
            </div>
        </form>
    </div>
</body>
</html>
