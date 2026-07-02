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
    {{-- Match the searchable combobox trigger so all selects share one design. --}}
    class="block w-full rounded-md border border-gray-300 bg-white shadow-sm text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-150 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-800 dark:border-gray-600 dark:text-white"
    @if($disabled) disabled @endif
>
    @if($placeholder)
        <option value="" @if($state === null) selected @endif disabled>{{ $placeholder }}</option>
    @endif

    @foreach($options as $value => $label)
        <option value="{{ $value }}" @if((string) $state === (string) $value) selected @endif>{{ $label }}</option>
    @endforeach
</select>
