@if($boardReport->exists)
    <div style="background: #d1fae5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px; font-size: 0.9rem;">
        Saved to your library.
        <a href="{{ route('trello.saved-reports') }}" style="color: #047857; font-weight: 700;">View all saved reports</a>
    </div>
@else
    <div style="background: #fef3c7; border: 1px solid #fde68a; color: #92400e; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px; font-size: 0.9rem;">
        Preview only — not saved. Regenerate with <strong>Save to my library</strong> checked to open or export this report later.
    </div>
@endif
