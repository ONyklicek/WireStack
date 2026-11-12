{{-- Generic filter wrapper for filters with multi-field getFormFields().
     Wire:model paths are built as: tableState.filters.{filterName}.{fieldName}.
     Used by NumberRangeFilter and DateFilter (range mode). --}}
{{-- Variables: $filter, $value --}}
@php
    $name = $filter->getName();
    $label = $filter->getLabel();
    // With a choice of comparison the operator sits above the value, and an
    // operator that takes none ("is empty") leaves the value out entirely.
    $hasPicker = $filter->hasOperatorPicker();
    $showsValue = ! $hasPicker || $filter->operatorFromState($value)->requiresValue();
@endphp

<div class="flex flex-col gap-1">
    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</span>
    @if($hasPicker)
        @include('wire-table::tables.filters.partials.operator-select', [
            'raw' => $value,
            'statePath' => 'tableState.filters.'.$name,
            'controlId' => 'filter-'.$name.'-operator',
            'label' => $label,
        ])
    @endif
    @if($showsValue)
    <div class="flex gap-2">
        @foreach ($filter->getFormFields() as $field)
            @php
                $fieldName = $field->getName();
                $type = match (true) {
                    method_exists($field, 'getNativeInputType') => $field->getNativeInputType(),
                    method_exists($field, 'getInputType') => $field->getInputType(),
                    default => 'text',
                };
                $placeholder = method_exists($field, 'getPlaceholder') ? ($field->getPlaceholder() ?? '') : '';
                $fieldValue = is_array($value) ? ($value[$fieldName] ?? '') : '';
                // Guard the scalar input value against nested arrays in state.
                $currentValue = is_scalar($fieldValue) ? $fieldValue : '';
            @endphp
            <div class="flex-1">
                <label for="filter-{{ $name }}-{{ $fieldName }}" class="sr-only">{{ $placeholder !== '' ? $placeholder : $fieldName }}</label>
                <input
                    type="{{ $type }}"
                    id="filter-{{ $name }}-{{ $fieldName }}"
                    {{ $filter->wireModelDirective(500) }}="tableState.filters.{{ $name }}.{{ $fieldName }}"
                    value="{{ $currentValue }}"
                    placeholder="{{ $placeholder }}"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-base sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white placeholder:text-gray-500 dark:placeholder:text-gray-400"
                >
            </div>
        @endforeach
    </div>
    @endif
</div>
