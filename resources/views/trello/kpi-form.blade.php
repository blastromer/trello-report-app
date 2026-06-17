<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Individual KPI — {{ $boardName }}</title>
    <link href="https://fonts.bunny.net/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Nunito', sans-serif; background: #f3f4f6; margin: 0; padding: 20px; }
        .container { max-width: 980px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h1 { color: #1f2937; margin: 0 0 6px; }
        .subtitle { color: #6b7280; margin: 0 0 22px; }
        .info { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 14px; color: #1e40af; margin-bottom: 18px; font-size: 0.9rem; }
        .alert-error { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 16px; }
        .form-group { margin-bottom: 18px; }
        label { display: block; font-weight: 600; color: #374151; margin-bottom: 6px; font-size: 0.875rem; }
        input[type="text"], input[type="month"], input[type="date"], select { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem; box-sizing: border-box; }
        .checkbox-group { max-height: 220px; overflow-y: auto; border: 1px solid #d1d5db; border-radius: 6px; padding: 8px; }
        .checkbox-item { padding: 6px; display: flex; align-items: center; gap: 8px; }
        .checkbox-item label { margin: 0; font-weight: 400; cursor: pointer; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .sprint-row { display: grid; grid-template-columns: 1fr 150px 150px; gap: 10px; align-items: end; margin-bottom: 12px; padding: 12px; background: #f9fafb; border-radius: 6px; border: 1px solid #e5e7eb; }
        .sprint-row small { color: #6b7280; grid-column: 1 / -1; }
        .capacity-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .capacity-table th, .capacity-table td { border-bottom: 1px solid #e5e7eb; padding: 10px 8px; text-align: left; vertical-align: middle; }
        .capacity-table th { background: #f9fafb; font-weight: 700; color: #374151; }
        .prof-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.875rem; }
        .prof-table th, .prof-table td { border-bottom: 1px solid #e5e7eb; padding: 8px 6px; text-align: center; vertical-align: middle; }
        .prof-table th:first-child, .prof-table td:first-child { text-align: left; }
        .prof-table input[type="number"] { width: 100%; max-width: 4.25rem; padding: 8px; box-sizing: border-box; }
        .prof-sum { font-weight: 700; color: #1f2937; }
        details.pro-rubric { margin-top: 14px; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 14px; background: #fafafa; }
        details.pro-rubric summary { cursor: pointer; font-weight: 600; color: #374151; }
        .actions { display: flex; gap: 10px; margin-top: 22px; flex-wrap: wrap; position: relative; z-index: 2; }
        .btn { padding: 12px 20px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; font-size: 1rem; position: relative; z-index: 2; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-secondary { background: #6b7280; color: #fff; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Individual KPI report</h1>
        <p class="subtitle">{{ $boardName }}</p>

        @if(session('error'))
            <div class="alert-error">{{ session('error') }}</div>
        @endif

        <div class="info">
            <strong>Metrics implemented:</strong>
            Productivity (30%) = sum(completed story points × severity multiplier) ÷ required points × 30.
            Efficiency (30%) = completed story points ÷ required points × 30.
            Severity comes from the Trello custom field named <strong>Severity</strong> (P1..P4).
            <br>
            <strong>Professionalism (25):</strong> For each selected member, score five categories from 0–5 each (see rubric below). The same scores apply to every sprint in this report.
        </div>

        <form method="POST" action="{{ route('trello.kpi', $boardId) }}">
            @csrf

            @php
                $selectedAssignees = old('assignees', $defaultAssignees ?? []);
                $profDims = \App\Support\ProfessionalismKpiReference::dimensionKeys();
                $profLabels = \App\Support\ProfessionalismKpiReference::dimensionShortLabels();
            @endphp

            <div class="form-group">
                <label for="date_coverage">Date coverage</label>
                <select id="date_coverage" name="date_coverage">
                    @php $cov = old('date_coverage', 'month'); @endphp
                    <option value="month" {{ $cov === 'month' ? 'selected' : '' }}>Single month</option>
                    <option value="q1" {{ $cov === 'q1' ? 'selected' : '' }}>Quarter 1 (Jan–Mar)</option>
                    <option value="q2" {{ $cov === 'q2' ? 'selected' : '' }}>Quarter 2 (Apr–Jun)</option>
                    <option value="q3" {{ $cov === 'q3' ? 'selected' : '' }}>Quarter 3 (Jul–Sep)</option>
                    <option value="q4" {{ $cov === 'q4' ? 'selected' : '' }}>Quarter 4 (Oct–Dec)</option>
                </select>
                <small style="color:#6b7280;display:block;margin-top:6px;">Quarters use calendar Q1–Q4 for the year you choose. Choosing a quarter fills three sprint rows (one per month); you can still edit labels and dates.</small>
                @error('date_coverage')
                    <div style="color:#b91c1c;font-size:0.875rem;margin-top:6px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group" id="coverage-month-wrap">
                <label for="report_month">Report month</label>
                <input type="month" id="report_month" name="report_month" value="{{ old('report_month', now()->format('Y-m')) }}">
                @error('report_month')
                    <div style="color:#b91c1c;font-size:0.875rem;margin-top:6px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group" id="coverage-quarter-wrap" style="display: none;">
                <label for="coverage_year">Year (for quarter)</label>
                <input type="number" id="coverage_year" name="coverage_year" min="2000" max="2100" step="1"
                    value="{{ old('coverage_year', now()->year) }}">
                @error('coverage_year')
                    <div style="color:#b91c1c;font-size:0.875rem;margin-top:6px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label>Team members on this report <span style="color:#b91c1c;">*</span></label>
                @include('trello.partials.team-member-checkboxes', [
                    'members' => $members,
                    'boardId' => $boardId,
                    'defaultAssignees' => $defaultAssignees ?? [],
                    'required' => true,
                ])
                <small style="color:#6b7280;">Only checked members appear in the KPI report.</small>
            </div>

            <div class="form-group">
                <label>Required points (tier capacity) per member</label>
                <small style="color:#6b7280;">Tier examples: 12 / 16 / 20. These values are used as the denominator for Productivity and Efficiency.</small>
                <table class="capacity-table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th style="width: 180px;">Required points</th>
                            <th style="width: 180px;">Quality %</th>
                            <th style="width: 210px;">Collaboration %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($members as $member)
                            @php $mid = $member['id']; @endphp
                            <tr class="capacity-row" data-member-id="{{ $mid }}" style="{{ in_array($mid, $selectedAssignees, true) ? '' : 'display:none;' }}">
                                <td>{{ $member['fullName'] ?? $member['username'] ?? 'Unknown' }}</td>
                                <td>
                                    <select name="required_points[{{ $mid }}]">
                                        @php $val = old('required_points.' . $mid, 16); @endphp
                                        <option value="0" {{ (string)$val === '0' ? 'selected' : '' }}>0 (exclude from scoring)</option>
                                        <option value="4" {{ (string)$val === '4' ? 'selected' : '' }}>4</option>
                                        <option value="8" {{ (string)$val === '8' ? 'selected' : '' }}>8</option>
                                        <option value="10" {{ (string)$val === '10' ? 'selected' : '' }}>10</option>
                                        <option value="12" {{ (string)$val === '12' ? 'selected' : '' }}>12</option>
                                        <option value="14" {{ (string)$val === '14' ? 'selected' : '' }}>14</option>
                                        <option value="16" {{ (string)$val === '16' ? 'selected' : '' }}>16</option>
                                        <option value="{{ $val }}" {{ !in_array((string)$val, ['0','4','8','10','12','14','16'], true) ? 'selected' : '' }}>{{ $val }}</option>
                                    </select>
                                </td>
                                <td>
                                    <input
                                        type="number"
                                        name="quality_percent[{{ $mid }}]"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        value="{{ old('quality_percent.' . $mid) }}"
                                        placeholder="0–100"
                                    >
                                </td>
                                <td>
                                    <input
                                        type="number"
                                        name="collaboration_percent[{{ $mid }}]"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        value="{{ old('collaboration_percent.' . $mid) }}"
                                        placeholder="0–100"
                                    >
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div id="capacityEmpty" style="{{ !empty($selectedAssignees) ? 'display:none;' : '' }} color:#6b7280;margin-top:10px;font-size:0.875rem;">
                    Select at least one team member above to set their required points and professionalism scores.
                </div>
            </div>

            <h2 style="font-size:1.1rem;color:#1f2937;margin:26px 0 12px;border-bottom:1px solid #e5e7eb;padding-bottom:8px;">Professionalism (25 points)</h2>
            <p style="color:#6b7280;font-size:0.875rem;margin:0 0 10px;">Each category is 0–5. Total per member is the sum (max 25). Applies to all sprints in this submission.</p>
            <table class="prof-table capacity-table">
                <thead>
                    <tr>
                        <th>Member</th>
                        @foreach($profDims as $dk)
                            <th title="{{ $dk }}">{{ $profLabels[$dk] ?? $dk }}</th>
                        @endforeach
                        <th>Σ 25</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($members as $member)
                        @php $mid = $member['id']; @endphp
                        <tr class="prof-row" data-member-id="{{ $mid }}" style="{{ in_array($mid, $selectedAssignees, true) ? '' : 'display:none;' }}">
                            <td>{{ $member['fullName'] ?? $member['username'] ?? 'Unknown' }}</td>
                            @foreach($profDims as $dk)
                                <td>
                                    <input
                                        type="number"
                                        name="professionalism[{{ $mid }}][{{ $dk }}]"
                                        class="js-prof-input"
                                        data-member-id="{{ $mid }}"
                                        min="0"
                                        max="5"
                                        step="1"
                                        value="{{ old('professionalism.'.$mid.'.'.$dk) }}"
                                    >
                                </td>
                            @endforeach
                            <td><span class="prof-sum" data-member-id="{{ $mid }}">—</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div id="profEmpty" style="{{ !empty($selectedAssignees) ? 'display:none;' : '' }} color:#6b7280;margin-top:10px;font-size:0.875rem;">
                Select team members to enter professionalism scores.
            </div>

            <details class="pro-rubric">
                <summary>Professionalism scoring rubric (reference)</summary>
                @include('trello.partials.professionalism-kpi')
            </details>

            <h2 style="font-size:1.1rem;color:#1f2937;margin:26px 0 12px;border-bottom:1px solid #e5e7eb;padding-bottom:8px;">Sprints</h2>
            <template id="sprint-row-template">
                <div class="sprint-row">
                    <div>
                        <label>Sprint label</label>
                        <input type="text" class="js-sp-label">
                    </div>
                    <div>
                        <label>From</label>
                        <input type="date" class="js-sp-from">
                    </div>
                    <div>
                        <label>To</label>
                        <input type="date" class="js-sp-to">
                    </div>
                    <small>Skip a row by leaving both dates empty.</small>
                </div>
            </template>
            @php
                $oldSprints = old('sprints', [
                    ['label' => 'Sprint 1', 'date_from' => '', 'date_to' => ''],
                    ['label' => 'Sprint 2', 'date_from' => '', 'date_to' => ''],
                ]);
            @endphp
            <div id="sprint-rows" data-preserve="{{ $errors->any() ? '1' : '0' }}">
                @foreach($oldSprints as $i => $s)
                    <div class="sprint-row">
                        <div>
                            <label>Sprint label</label>
                            <input type="text" name="sprints[{{ $i }}][label]" value="{{ $s['label'] ?? ('Sprint ' . ($i + 1)) }}">
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
            </div>

            @include('trello.partials.save-report-option')

            <div class="actions">
                <button type="submit" class="btn btn-primary">Generate KPI report</button>
                <a href="{{ route('trello.board.dashboard', $boardId) }}" class="btn btn-secondary">Back to board</a>
            </div>
        </form>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function updateProfSums() {
                var dims = @json($profDims);
                document.querySelectorAll('.prof-row').forEach(function (tr) {
                    var mid = tr.getAttribute('data-member-id');
                    if (!mid) return;
                    var sum = 0;
                    var ok = true;
                    dims.forEach(function (dk) {
                        var inp = tr.querySelector('input[name="professionalism[' + mid + '][' + dk + ']"]');
                        if (!inp || inp.value === '') { ok = false; return; }
                        var n = parseInt(inp.value, 10);
                        if (isNaN(n)) { ok = false; return; }
                        sum += n;
                    });
                    var el = document.querySelector('.prof-sum[data-member-id="' + mid + '"]');
                    if (el) el.textContent = ok ? String(sum) : '—';
                });
            }

            function updateCapacityRows() {
                var checked = Array.from(document.querySelectorAll('input[name="assignees[]"]:checked'))
                    .map(function (el) { return el.value; });

                var rows = Array.from(document.querySelectorAll('.capacity-row'));
                var profRows = Array.from(document.querySelectorAll('.prof-row'));
                var any = false;
                rows.forEach(function (tr) {
                    var id = tr.getAttribute('data-member-id');
                    var show = checked.indexOf(id) !== -1;
                    tr.style.display = show ? '' : 'none';
                    if (show) any = true;
                });
                profRows.forEach(function (tr) {
                    var id = tr.getAttribute('data-member-id');
                    var show = checked.indexOf(id) !== -1;
                    tr.style.display = show ? '' : 'none';
                    tr.querySelectorAll('.js-prof-input').forEach(function (inp) {
                        if (show) {
                            inp.setAttribute('required', 'required');
                        } else {
                            inp.removeAttribute('required');
                        }
                    });
                });

                var empty = document.getElementById('capacityEmpty');
                if (empty) empty.style.display = any ? 'none' : '';
                var profEmpty = document.getElementById('profEmpty');
                if (profEmpty) profEmpty.style.display = any ? 'none' : '';
                updateProfSums();
            }

            Array.from(document.querySelectorAll('input[name="assignees[]"]')).forEach(function (el) {
                el.addEventListener('change', updateCapacityRows);
            });
            document.querySelectorAll('.js-prof-input').forEach(function (inp) {
                inp.addEventListener('input', updateProfSums);
                inp.addEventListener('change', updateProfSums);
            });
            updateCapacityRows();

            function syncCoverageUI() {
                var sel = document.getElementById('date_coverage');
                var mode = sel ? sel.value : 'month';
                var monthWrap = document.getElementById('coverage-month-wrap');
                var quarterWrap = document.getElementById('coverage-quarter-wrap');
                var rm = document.getElementById('report_month');
                var cy = document.getElementById('coverage_year');
                if (!monthWrap || !quarterWrap || !rm || !cy) return;
                if (mode === 'month') {
                    monthWrap.style.display = '';
                    quarterWrap.style.display = 'none';
                    rm.setAttribute('required', 'required');
                    cy.removeAttribute('required');
                } else {
                    monthWrap.style.display = 'none';
                    quarterWrap.style.display = '';
                    rm.removeAttribute('required');
                    cy.setAttribute('required', 'required');
                }
            }
            var covSel = document.getElementById('date_coverage');
            var sprintRowsEl = document.getElementById('sprint-rows');
            var sprintTpl = document.getElementById('sprint-row-template');

            var MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

            function isoDate(year, month1, day) {
                return year + '-' + String(month1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            }

            function lastDayOfMonth(year, month1) {
                var dt = new Date(year, month1, 0);
                return isoDate(dt.getFullYear(), dt.getMonth() + 1, dt.getDate());
            }

            function appendSprintRow(container, index, label, from, to) {
                if (!sprintTpl || !container) return;
                var frag = sprintTpl.content.cloneNode(true);
                var labelInput = frag.querySelector('.js-sp-label');
                var fromInput = frag.querySelector('.js-sp-from');
                var toInput = frag.querySelector('.js-sp-to');
                labelInput.name = 'sprints[' + index + '][label]';
                fromInput.name = 'sprints[' + index + '][date_from]';
                toInput.name = 'sprints[' + index + '][date_to]';
                labelInput.value = label;
                fromInput.value = from;
                toInput.value = to;
                container.appendChild(frag);
            }

            function fillSprintRowsMonthDefaults() {
                if (!sprintRowsEl) return;
                sprintRowsEl.innerHTML = '';
                appendSprintRow(sprintRowsEl, 0, 'Sprint 1', '', '');
                appendSprintRow(sprintRowsEl, 1, 'Sprint 2', '', '');
            }

            function fillSprintRowsForQuarter(mode, year) {
                if (!sprintRowsEl) return;
                var q = parseInt(String(mode).replace(/^q/i, ''), 10);
                if (q < 1 || q > 4) q = 1;
                var startM = (q - 1) * 3 + 1;
                sprintRowsEl.innerHTML = '';
                for (var k = 0; k < 3; k++) {
                    var m = startM + k;
                    var label = MONTH_NAMES[m - 1] + ' ' + year;
                    appendSprintRow(sprintRowsEl, k, label, isoDate(year, m, 1), lastDayOfMonth(year, m));
                }
            }

            function syncSprintsToDateCoverage() {
                if (!sprintRowsEl || !covSel) return;
                var mode = covSel.value;
                var cy = document.getElementById('coverage_year');
                var year = cy && cy.value !== '' ? parseInt(cy.value, 10) : new Date().getFullYear();
                if (isNaN(year)) year = new Date().getFullYear();
                if (mode === 'month') {
                    fillSprintRowsMonthDefaults();
                } else {
                    fillSprintRowsForQuarter(mode, year);
                }
            }

            if (covSel) {
                covSel.addEventListener('change', function () {
                    syncCoverageUI();
                    syncSprintsToDateCoverage();
                });
                syncCoverageUI();
                if (sprintRowsEl && sprintRowsEl.getAttribute('data-preserve') !== '1' && covSel.value !== 'month') {
                    syncSprintsToDateCoverage();
                }
            }

            var cyEl = document.getElementById('coverage_year');
            if (cyEl && covSel) {
                cyEl.addEventListener('change', function () {
                    if (covSel.value === 'month') return;
                    syncSprintsToDateCoverage();
                });
            }
        });
    </script>
</body>
</html>

