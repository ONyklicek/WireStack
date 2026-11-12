{{-- The named ranges a date filter offers (->presets()): "Custom dates" and
     then today, last 7 days, this month… A native <select> for the same reason
     as the operator picker. Choosing one stores its NAME, so the range stays
     relative in a saved view or a shared link; the from/to boxes are only
     drawn for "Custom dates". --}}
{{-- Variables: $filter, $value (the range state), $statePath, $controlId, $label --}}
@php
    $current = $filter->presetFromState($value ?? null);
@endphp
<label for="{{ $controlId }}" class="sr-only">{{ __('wire-table::messages.date_preset_label', ['filter' => $label]) }}</label>
<select
    id="{{ $controlId }}"
    wire:model.live="{{ $statePath }}.preset"
    data-testid="{{ $controlId }}"
    @wireEl('filter-date-preset')
    class="{{ \NyonCode\WireTable\Support\FilterControl::classes() }} pr-8"
>
    <option value="" @selected($current === null)>{{ __('wire-table::messages.date_preset_custom') }}</option>
    @foreach($filter->getPresets() as $preset)
        <option value="{{ $preset->value }}" @selected($preset === $current)>{{ $preset->label() }}</option>
    @endforeach
</select>
