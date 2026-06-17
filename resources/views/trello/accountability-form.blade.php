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
        .sprint-row { display: grid; grid-template-columns: 1fr 140px 140px auto; gap: 10px; align-items: end; margin-bottom: 12px; padding: 12px; background: #f9fafb; border-radius: 6px; }
        .sprint-row small { color: #6b7280; grid-column: 1 / -1; }
        .btn-add-sprint { background: #fff; color: #2563eb; border: 1px dashed #93c5fd; padding: 10px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; margin-bottom: 12px; }
        .btn-add-sprint:hover { background: #eff6ff; }
        .btn-remove-sprint { background: #fff; color: #b91c1c; border: 1px solid #fecaca; padding: 8px 12px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.85rem; white-space: nowrap; }
        .btn-remove-sprint:hover { background: #fef2f2; }
        .sprints-hint { color: #6b7280; font-size: 0.875rem; margin: 0 0 12px; }
        .info { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 14px; color: #1e40af; margin-bottom: 20px; font-size: 0.9rem; }
        .actions { display: flex; gap: 10px; margin-top: 24px; flex-wrap: wrap; }
        .btn { padding: 12px 20px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; font-size: 1rem; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-secondary { background: #6b7280; color: #fff; }
        .alert-error { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 16px; }
        .alert-success { background: #d1fae5; color: #065f46; padding: 12px; border-radius: 6px; margin-bottom: 16px; }
        .alert-info { background: #eff6ff; color: #1e40af; padding: 12px; border-radius: 6px; margin-bottom: 16px; }
        h2 { font-size: 1.1rem; color: #1f2937; margin: 28px 0 12px; border-bottom: 1px solid #e5e7eb; padding-bottom: 8px; }
        .threshold-panel { border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; background: #f9fafb; margin-bottom: 20px; }
        .threshold-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
        .threshold-row .field { flex: 1; min-width: 160px; }
        .threshold-history { margin-top: 16px; }
        .threshold-history summary { cursor: pointer; font-weight: 600; color: #374151; font-size: 0.9rem; }
        .threshold-history table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.875rem; }
        .threshold-history th, .threshold-history td { text-align: left; padding: 8px 6px; border-bottom: 1px solid #e5e7eb; }
        .threshold-history th { color: #6b7280; font-weight: 600; font-size: 0.78rem; text-transform: uppercase; }
        .btn-save-threshold { background: #059669; color: #fff; padding: 10px 16px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; white-space: nowrap; }
        .btn-save-threshold:hover { background: #047857; }
        .standards-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; margin-top: 10px; }
        .standards-table th, .standards-table td { border: 1px solid #e5e7eb; padding: 10px; text-align: center; vertical-align: middle; }
        .standards-table th { background: #f3f4f6; color: #374151; font-size: 0.78rem; }
        .range-inputs { display: flex; align-items: center; justify-content: center; gap: 6px; flex-wrap: wrap; }
        .range-inputs input { width: 72px; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; text-align: center; }
        .standards-ref { background: #fff; border: 1px dashed #93c5fd; border-radius: 6px; padding: 12px; margin-bottom: 12px; font-size: 0.85rem; color: #1e40af; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Team Accountability Report</h1>
        <p class="subtitle">{{ $boardName }}</p>

        @if(session('error'))
            <div class="alert-error">{{ session('error') }}</div>
        @endif
        @if(session('success'))
            <div class="alert-success">{{ session('success') }}</div>
        @endif
        @if(session('info'))
            <div class="alert-info">{{ session('info') }}</div>
        @endif

        <div class="info">
            <strong>How it works:</strong> For each sprint, set a date range. Cards are included when their Trello <strong>Date Completed</strong> custom field falls in that range (same as the board report). Cards without Date Completed set are excluded.
            <strong>Accomplishment rate</strong> = story points on <em>completed</em> lists ÷ total points on all cards in that window (for each <em>checked</em> team member only).
            The narrative sections use the <strong>Report month</strong> with the same Date Completed rule.
            Check at least one team member. <strong>Leave any narrative box blank</strong> to auto-generate it from Trello + sprint metrics (you can edit after export).
        </div>

        <form id="accountability-report-form" method="POST" action="{{ route('trello.accountability', $boardId) }}">
            @csrf

            <div class="form-group">
                <label for="report_month">Report month</label>
                <input type="month" id="report_month" name="report_month" value="{{ old('report_month', now()->format('Y-m')) }}" required>
            </div>

            <div class="threshold-panel">
                <label style="margin-bottom:10px;">Performance standards (Dev/IT baseline)</label>
                <div class="standards-ref">
                    <strong>Company baseline:</strong> Meets expectation <strong>80%–90%</strong> ·
                    2% increase <strong>91%–93%</strong> ·
                    4% increase <strong>94%–97%</strong> ·
                    6% increase <strong>98%–100%</strong>
                </div>
                <p style="color:#6b7280;font-size:0.875rem;margin:0 0 8px;">
                    Saved: <strong>{{ $currentStandards->summaryLine() }}</strong><br>
                    Use <strong>Save standards</strong> to store changes for this board (history is kept).
                    Generating a report uses the values below without auto-saving.
                </p>
                @include('trello.partials.performance-standards-fields', ['currentStandards' => $currentStandards])
                <div class="threshold-row" style="margin-top:14px;">
                    <button type="button" class="btn-save-threshold" id="btn-save-threshold">Save standards</button>
                </div>
                <small style="color:#6b7280;display:block;margin-top:8px;">Below {{ number_format($currentStandards->baselineMin, 0) }}% = Below Expectation. Within baseline = Meets Baseline Expectation. Higher tiers map to salary increase eligibility.</small>

                @if($thresholdHistory->isNotEmpty())
                    <details class="threshold-history" open>
                        <summary>Standards history ({{ $thresholdHistory->count() }})</summary>
                        <table>
                            <thead>
                                <tr>
                                    <th>Standards</th>
                                    <th>Changed</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($thresholdHistory as $entry)
                                    <tr>
                                        <td><strong>{{ $entry->toPerformanceStandards()->summaryLine() }}</strong></td>
                                        <td>{{ $entry->created_at->format('M j, Y g:i A') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </details>
                @endif
            </div>

            <div class="form-group">
                <label>Team members on this report <span style="color:#b91c1c;">*</span></label>
                @include('trello.partials.team-member-checkboxes', [
                    'members' => $members ?? [],
                    'boardId' => $boardId,
                    'defaultAssignees' => $defaultAssignees ?? [],
                    'required' => true,
                ])
                <small style="color:#6b7280;">Only checked people appear in sprint metrics. Story points on shared cards count only toward checked assignees.</small>
            </div>

            <h2>Sprints</h2>
            <p class="sprints-hint">Set the date range for each sprint window. Start with one sprint and click <strong>Add sprint</strong> for more (up to 8).</p>

            <template id="sprint-row-template">
                <div class="sprint-row">
                    <div>
                        <label>Sprint label</label>
                        <input type="text" class="js-sp-label" placeholder="Sprint 1">
                    </div>
                    <div>
                        <label>From</label>
                        <input type="date" class="js-sp-from">
                    </div>
                    <div>
                        <label>To</label>
                        <input type="date" class="js-sp-to">
                    </div>
                    <div>
                        <label>&nbsp;</label>
                        <button type="button" class="btn-remove-sprint js-remove-sprint" title="Remove sprint">Remove</button>
                    </div>
                    <small>Both dates are required for this sprint to be included in the report.</small>
                </div>
            </template>

            @php
                $oldSprints = old('sprints', [
                    ['label' => 'Sprint 1', 'date_from' => '', 'date_to' => ''],
                ]);
            @endphp
            <div id="sprint-rows">
                @foreach($oldSprints as $i => $s)
                    <div class="sprint-row">
                        <div>
                            <label>Sprint label</label>
                            <input type="text" name="sprints[{{ $i }}][label]" class="js-sp-label" value="{{ $s['label'] ?? 'Sprint ' . ($i + 1) }}" placeholder="Sprint {{ $i + 1 }}">
                        </div>
                        <div>
                            <label>From</label>
                            <input type="date" name="sprints[{{ $i }}][date_from]" class="js-sp-from" value="{{ $s['date_from'] ?? '' }}">
                        </div>
                        <div>
                            <label>To</label>
                            <input type="date" name="sprints[{{ $i }}][date_to]" class="js-sp-to" value="{{ $s['date_to'] ?? '' }}">
                        </div>
                        <div>
                            <label>&nbsp;</label>
                            <button type="button" class="btn-remove-sprint js-remove-sprint" title="Remove sprint">Remove</button>
                        </div>
                        <small>Both dates are required for this sprint to be included in the report.</small>
                    </div>
                @endforeach
            </div>
            <button type="button" class="btn-add-sprint" id="btn-add-sprint">+ Add sprint</button>

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

            @include('trello.partials.save-report-option')

            <div class="actions">
                <button type="submit" class="btn btn-primary">Generate accountability report</button>
                <a href="{{ route('trello.board.dashboard', $boardId) }}" class="btn btn-secondary">Back to board</a>
            </div>
        </form>

        <form id="threshold-save-form" method="POST" action="{{ route('trello.accountability.threshold.save', $boardId) }}" style="display:none;">
            @csrf
            <div id="threshold-save-fields"></div>
        </form>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var sprintRowsEl = document.getElementById('sprint-rows');
            var sprintTpl = document.getElementById('sprint-row-template');
            var maxSprints = 8;

            function reindexSprintRows() {
                if (!sprintRowsEl) return;
                var rows = sprintRowsEl.querySelectorAll('.sprint-row');
                rows.forEach(function (row, index) {
                    var labelInput = row.querySelector('.js-sp-label');
                    var fromInput = row.querySelector('.js-sp-from');
                    var toInput = row.querySelector('.js-sp-to');
                    if (labelInput) labelInput.name = 'sprints[' + index + '][label]';
                    if (fromInput) fromInput.name = 'sprints[' + index + '][date_from]';
                    if (toInput) toInput.name = 'sprints[' + index + '][date_to]';
                    var removeBtn = row.querySelector('.js-remove-sprint');
                    if (removeBtn) removeBtn.style.display = rows.length > 1 ? '' : 'none';
                });
                var addBtn = document.getElementById('btn-add-sprint');
                if (addBtn) addBtn.disabled = rows.length >= maxSprints;
            }

            function appendSprintRow(label, from, to) {
                if (!sprintTpl || !sprintRowsEl) return;
                var index = sprintRowsEl.querySelectorAll('.sprint-row').length;
                if (index >= maxSprints) return;
                var frag = sprintTpl.content.cloneNode(true);
                var labelInput = frag.querySelector('.js-sp-label');
                var fromInput = frag.querySelector('.js-sp-from');
                var toInput = frag.querySelector('.js-sp-to');
                labelInput.value = label || ('Sprint ' + (index + 1));
                fromInput.value = from || '';
                toInput.value = to || '';
                sprintRowsEl.appendChild(frag);
                reindexSprintRows();
            }

            document.getElementById('btn-add-sprint')?.addEventListener('click', function () {
                var count = sprintRowsEl ? sprintRowsEl.querySelectorAll('.sprint-row').length : 0;
                appendSprintRow('Sprint ' + (count + 1), '', '');
            });

            sprintRowsEl?.addEventListener('click', function (e) {
                if (!e.target.classList.contains('js-remove-sprint')) return;
                var row = e.target.closest('.sprint-row');
                if (!row || sprintRowsEl.querySelectorAll('.sprint-row').length <= 1) return;
                row.remove();
                reindexSprintRows();
            });

            reindexSprintRows();

            document.getElementById('btn-save-threshold')?.addEventListener('click', function () {
                var saveForm = document.getElementById('threshold-save-form');
                var container = document.getElementById('threshold-save-fields');
                if (!saveForm || !container) return;
                container.innerHTML = '';
                document.querySelectorAll('.std-field').forEach(function (input) {
                    var hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'standards[' + input.getAttribute('data-std') + ']';
                    hidden.value = input.value;
                    container.appendChild(hidden);
                });
                saveForm.submit();
            });
        });
    </script>
</body>
</html>
