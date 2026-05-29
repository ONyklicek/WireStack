{{-- Generic filter wrapper for filters with multi-field getFormFields().
     Wire:model paths are built as: tableState.filters.{filterName}.{fieldName}.
     Used by NumberRangeFilter and DateFilter (range mode). --}}
{{-- Variables: $filter, $value --}}
@php
    $name = $filter->getName();
    $label = $filter->getLabel();
@endphp

<div class="flex flex-col gap-1">
    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</span>
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
                $currentValue = is_array($value) ? ($value[$fieldName] ?? '') : '';
            @endphp
            <div class="flex-1">
                <label for="filter-{{ $name }}-{{ $fieldName }}" class="sr-only">{{ $placeholder !== '' ? $placeholder : $fieldName }}</label>
                <input
                    type="{{ $type }}"
                    id="filter-{{ $name }}-{{ $fieldName }}"
                    wire:model.live.debounce.500ms="tableState.filters.{{ $name }}.{{ $fieldName }}"
                    value="{{ $currentValue }}"
                    placeholder="{{ $placeholder }}"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
            </div>
        @endforeach
    </div>
</div>
