{{-- SelectColumn editable cell. --}}
{{-- Rendered once per column shape with placeholder values and filled per row
     (SelectColumn::renderCell()), so nothing here may branch on the record:
     $recordKey, $value and $recordVersion are slots, not data. --}}
{{-- Variables: $column, $recordKey, $value, $recordVersion, $syncHtml, $disabled,
     $nativeMode, $panelId, $nativeOptions, $mobileBreakpoint --}}
@php
    $columnName = $column->getName();
    $multiple = $column->isMultiple();
    $placeholder = $column->getPlaceholder() ?? __('wire-table::messages.select_placeholder');
    // The cell's own state, never a Livewire property: wireEditableCell writes
    // it through updateTableCell(), whichever control the user touched.
    $cellAttributes = ':aria-busy="saving" :title="error" :class="{ \'border-red-500 focus:border-red-500 focus:ring-red-500\': error }"';
@endphp
<div
    wire:key="sel-{{ $recordKey }}-{{ $columnName }}"
    wire:ignore.self
    x-data="wireEditableCell({
        island: 'data-region',
        value: {{ json_encode($value) }},
        recordVersion: '{{ $recordVersion }}',{!! $multiple ? "\n        parse: (raw) => Array.isArray(raw) ? raw.map(String) : JSON.parse(raw || '[]')," : '' !!}
    })"
    data-record-key="{{ $recordKey }}"
    data-column-name="{{ $columnName }}"
    data-testid="table-editable-{{ $columnName }}"
    data-msg-error="{{ __('wire-table::messages.error') }}"
    data-msg-save-failed="{{ __('wire-table::messages.save_failed') }}"
    :data-error-lines="errorLines || null"
    class="relative"
>
    {!! $syncHtml !!}

    @include('wire-core::partials.select-control', [
        'part' => 'trigger',
        'panelId' => $panelId,
        'nativeMode' => $nativeMode,
        'selectId' => 'cell-'.$columnName.'-'.$recordKey,
        'statePath' => 'value',
        'stateExpression' => 'value',
        'commitExpression' => '(next) => change(next)',
        // The combobox reads its list from the shared panel; only the
        // browser's <select> carries options of its own.
        'options' => $nativeOptions,
        'placeholder' => $placeholder,
        'multiple' => $multiple,
        'disabled' => $disabled,
        'touch' => $column->usesTouchOnMobile(),
        'mobileBreakpoint' => $mobileBreakpoint,
        'wireModel' => 'x-model',
        // The native control: x-model has already set the value by the time
        // `change` runs. A single select passes the event so a refused change
        // can put the element back; a list follows `value` on its own.
        'extraInputAttributes' => ($nativeMode->rendersNative() ? ($multiple ? '@change="change(value)" ' : '@change="change($event.target.value, $event)" ') : '').$cellAttributes,
        'required' => ! $column->isPlaceholderSelectable(),
        'selectedValues' => [],
        'ariaLabel' => $column->getLabel(),
        'testId' => 'table-select-'.$columnName,
    ])

    {{-- The error, as on a TextInputColumn: under the control and as wide as
         it (`w-0 min-w-full` keeps a long message from widening the column),
         laid out of the flow in a table row so the select does not move — the
         row makes the room, in every cell alike (`tables.partials.cell-error-styles`,
         from `data-error-lines` above). --}}
    <p x-show="error" x-cloak x-text="error" x-ref="error"
       data-cell-error
       data-testid="table-editable-error"
       class="mt-1 w-0 min-w-full whitespace-normal break-words text-xs text-red-600 dark:text-red-400"></p>
</div>
