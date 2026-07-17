@php
    use NyonCode\WireCore\Panels\Components\TextInputEntry;

    assert($field instanceof TextInputEntry);

    $span = $field->getColumnSpan();
    $spanClass = match (true) {
        $span === 'full' => 'col-span-full',
        $span === 2 => 'sm:col-span-2',
        $span === 3 => 'sm:col-span-3',
        $span === 4 => 'sm:col-span-4',
        default => '',
    };

    $name = $field->getName();
    $value = (string) ($field->getState() ?? '');
    $disabled = ! $field->isEditable() || $field->isDisabled();
@endphp

<div class="{{ $spanClass }}">
    @if($field->getLabel())
        <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
            {{ $field->getLabel() }}
        </div>
    @endif

    {{-- Optimistic value + rollback + optimistic-lock conflict handling all live in
         the shared wireEditableCell engine; this cell adds save-on-blur/enter and
         escape-to-revert. --}}
    <div
        wire:key="panel-txt-{{ $name }}"
        wire:ignore.self
        x-data="wireEditableCell({
            value: {{ \Illuminate\Support\Js::from($value) }},
            recordVersion: '{{ $field->getRecordVersion() }}',
            commitMethod: 'updatePanelEntry',
            saveOnBlur: true,
            saveOnEnter: true,
        })"
        data-record-key="{{ $field->getRecordKey() }}"
        data-column-name="{{ $name }}"
        data-testid="panel-editable-{{ $name }}"
        data-server-value="{{ $value }}"
        data-record-version="{{ $field->getRecordVersion() }}"
        data-msg-error="{{ __('wire-core::messages.error') }}"
        data-msg-save-failed="{{ __('wire-core::messages.save_failed') }}"
    >
        <input
            type="{{ $field->getInputType() }}"
            x-model="value"
            @focus="onFocus()"
            @blur="onBlur()"
            @keydown.enter.prevent="onEnter()"
            @keydown.escape="onEscape()"
            aria-label="{{ $field->getLabel() ?? $name }}"
            @if($disabled) disabled @else :disabled="saving" @endif
            :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': error, 'border-green-500': success }"
            x-ref="input"
            class="block w-full rounded-md border border-gray-300 bg-white shadow-sm text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-150 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-800 dark:border-gray-600 dark:text-white"
        >

        <p x-show="error" x-cloak x-text="error" class="mt-1 text-xs text-red-600 dark:text-red-400"></p>
    </div>
</div>
