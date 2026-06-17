<div class="form-group" style="border: 1px solid #d1d5db; border-radius: 6px; padding: 14px; background: #f9fafb;">
    <div class="checkbox-item" style="display: flex; align-items: flex-start; gap: 10px;">
        <input type="checkbox" id="save_report" name="save_report" value="1"
            {{ old('save_report', true) ? 'checked' : '' }}
            style="margin-top: 3px;">
        <label for="save_report" style="font-weight: 600; margin: 0; cursor: pointer;">
            Save to my library
            <span style="display: block; font-weight: 400; color: #6b7280; font-size: 0.875rem; margin-top: 4px;">
                {{ $exportHint ?? 'Store this report so you can open it later in the browser or export as CSV, Word, or HTML.' }}
            </span>
        </label>
    </div>
</div>
