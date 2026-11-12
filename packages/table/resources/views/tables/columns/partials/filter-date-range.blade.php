{{-- Date range filter: from/to, and the named ranges when the filter offers
     them (->presets()). Drawn in the header row, in a column popover, and —
     for a filter with presets — in the filter panel too. --}}
{{-- Variables: $filter, $value, $statePath, $controlClasses; $column when in a header --}}
@php
    $minDate = $filter->getMinDate();
    $maxDate = $filter->getMaxDate();
    $fromValue = is_array($value) ? ($value['from'] ?? '') : '';
    $toValue = is_array($value) ? ($value['to'] ?? '') : '';
    $hasPresets = $filter->hasPresets();
    $preset = $hasPresets ? $filter->presetFromState($value) : null;
    $controlId = str_replace('.', '-', $statePath);
@endphp

<div @class(['flex flex-col gap-2' => $hasPresets])>
    @if($hasPresets)
        @include('wire-table::tables.filters.partials.date-preset-select', [
            'statePath' => $statePath,
            'controlId' => $controlId.'-preset',
            'label' => isset($column) ? $column->getLabel() : $filter->getLabel(),
        ])
    @endif

    @if($preset === null)
    <div class="flex gap-1">
        <input
            type="date"
            {{ $filter->wireModelDirective() }}="{{ $statePath }}.from"
            @if($minDate) min="{{ $minDate }}" @endif
            @if($maxDate) max="{{ $maxDate }}" @endif
            placeholder="{{ __('wire-table::messages.from') }}"
            title="{{ __('wire-table::messages.from') }}"
            class="{{ $controlClasses }}"
        >
        <input
            type="date"
            {{ $filter->wireModelDirective() }}="{{ $statePath }}.to"
            @if($minDate) min="{{ $minDate }}" @endif
            @if($maxDate) max="{{ $maxDate }}" @endif
            placeholder="{{ __('wire-table::messages.to') }}"
            title="{{ __('wire-table::messages.to') }}"
            class="{{ $controlClasses }}"
        >
    </div>
    @else
        @php $range = $filter->resolveRange($value); @endphp
        <p class="text-xs text-gray-500 dark:text-gray-400" data-testid="{{ $controlId }}-range">{{ $range['from'] }} – {{ $range['to'] }}</p>
    @endif
</div>
