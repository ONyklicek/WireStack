@php
    use NyonCode\WireForms\Components\BelongsToSelect;

    assert($field instanceof BelongsToSelect);

    $wireModifier = $field->getWireModelModifier();
    $wireAttr = 'wire:model' . ($wireModifier ? ".{$wireModifier}" : '');
    $options = $field->getOptions();
    // Every non-native select renders through the canonical combobox so searchable
    // and non-searchable selects share one design; native <select> is opt-in only.
    $useCombobox = ! $field->isNative();
    $fieldId = $field->getId();
@endphp

@include('wire-forms::partials.field-wrapper-start')

@if($useCombobox)
    {{-- Combobox: delegate to the canonical shared owner, contributing the
         create-option button through its panel-footer slot. The search input is
         shown only when the field is searchable, so both variants match. --}}
    @include('wire-core::partials.searchable-select', [
        'selectId' => $fieldId,
        'statePath' => $field->getWireModelAttribute(),
        'options' => $options,
        'placeholder' => $field->getPlaceholder(),
        'multiple' => $field->isMultiple(),
        'searchable' => $field->isSearchable(),
        'searchPrompt' => $field->getSearchPrompt(),
        'noResultsMessage' => $field->getNoSearchResultsMessage(),
        'disabled' => $field->isDisabled(),
        'hasError' => $errors->has($field->getStatePath()),
        'panelFooter' => $field->hasCreateOptionForm()
            ? view('wire-forms::partials.belongs-to-create-option', ['field' => $field])->render()
            : null,
    ])
@else
    <select
        id="{{ $fieldId }}"
        {{ $wireAttr }}="{{ $field->getWireModelAttribute() }}"
        @if($field->isDisabled()) disabled @endif
        @if($field->isRequired()) required @endif
        {{-- Match the searchable combobox trigger. --}}
        @class([
            'block w-full rounded-md border border-gray-300 bg-white shadow-sm text-sm',
            'focus:border-primary-500 focus:ring-1 focus:ring-primary-500',
            'hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-150',
            'disabled:opacity-50 disabled:cursor-not-allowed',
            'dark:bg-gray-800 dark:border-gray-600 dark:text-white',
            'border-red-500' => $errors->has($field->getStatePath()),
        ])
    >
        @if($field->getPlaceholder())
            <option value="">{{ $field->getPlaceholder() }}</option>
        @endif

        @foreach($options as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
    </select>
@endif

@include('wire-forms::partials.field-wrapper-end')
