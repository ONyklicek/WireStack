{{-- SelectColumn editable cell --}}
{{-- Variables: $column, $record, $state --}}
@php
    $recordKey = $record->getKey();
    $columnName = $column->getName();
    $placeholder = $column->getPlaceholder() ?? __('wire-table::messages.select_placeholder');
    $options = $column->getOptions();
    $disabled = $column->isDisabled($record);
@endphp

<select
    wire:change="updateTableCell('{{ $recordKey }}', '{{ $columnName }}', $event.target.value)"
    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
    @if($disabled) disabled @endif
>
    @if($placeholder)
        <option value="" @if($state === null) selected @endif disabled>{{ $placeholder }}</option>
    @endif

    @foreach($options as $value => $label)
        <option value="{{ $value }}" @if((string) $state === (string) $value) selected @endif>{{ $label }}</option>
    @endforeach
</select>
