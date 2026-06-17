@php
    $oldKey = $oldKey ?? 'assignees';
    $selectedAssignees = old($oldKey, $defaultAssignees ?? []);
    $teamCount = count($defaultAssignees ?? []);
    $inputName = $inputName ?? 'assignees[]';
    $required = $required ?? false;
@endphp

@if($teamCount > 0)
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:10px 12px;margin-bottom:10px;font-size:0.875rem;color:#1e40af;">
        Your saved team (<strong>{{ $teamCount }}</strong> member{{ $teamCount === 1 ? '' : 's' }}) is pre-selected.
        <a href="{{ route('trello.team', $boardId) }}" style="color:#1d4ed8;font-weight:700;">Manage team &amp; charts</a>
    </div>
@else
    <div style="background:#fef3c7;border:1px solid #fde68a;border-radius:6px;padding:10px 12px;margin-bottom:10px;font-size:0.875rem;color:#92400e;">
        <a href="{{ route('trello.team', $boardId) }}" style="color:#b45309;font-weight:700;">Save your team</a>
        to auto-select members every time you generate a report.
    </div>
@endif

@if(!empty($members))
    <div class="checkbox-group">
        @foreach($members as $member)
            <div class="checkbox-item">
                <input type="checkbox" id="{{ $checkboxPrefix ?? 'm' }}_{{ $member['id'] }}" name="{{ $inputName }}" value="{{ $member['id'] }}"
                    {{ in_array($member['id'], $selectedAssignees, true) ? 'checked' : '' }}>
                <label for="{{ $checkboxPrefix ?? 'm' }}_{{ $member['id'] }}">{{ $member['fullName'] ?? $member['username'] ?? 'Unknown' }}</label>
            </div>
        @endforeach
    </div>
    @if($required)
        @error('assignees')
            <div style="color:#b91c1c;font-size:0.875rem;margin-top:6px;">{{ $message }}</div>
        @enderror
    @endif
@else
    <p style="color:#b91c1c;">This board has no members to select.</p>
@endif
