@php
    use NyonCode\WireForms\Components\Select;

    assert($field instanceof Select);

    $wireModifier = $field->getWireModelModifier();
    $wireAttr = 'wire:model' . ($wireModifier ? ".{$wireModifier}" : '');
    $options = $field->getOptions();
    // Every non-native select renders through the canonical combobox so searchable
    // and non-searchable selects share one design; the search input is simply
    // hidden when the field is not searchable. Native <select> is opt-in only.
    $useCombobox = ! $field->isNative();
    $isSearchable = $field->isSearchable();
    $isRemoteSearch = $field->isRemoteSearch();
    $livewire = $field->getLivewire();
    $hasCreateOption = $field->hasCreateOptionForm() && $livewire !== null;
    // Editing targets the single selected option; multi-selects have no single target.
    $hasEditOption = $field->hasEditOptionForm() && ! $field->isMultiple() && $livewire !== null;

    if ($useCombobox) {
        // Seed the combobox with the render-time option list, plus the label(s)
        // for the current selection so a remotely-searched value still shows.
        $currentValue = $livewire ? data_get($livewire, $field->getStatePath()) : null;
        $comboboxOptions = $field->getSelectedOptionLabels($currentValue) + $field->getPreloadedOptions();

        $panelFooter = ($hasCreateOption || $hasEditOption)
            ? view('wire-forms::partials.select-option-actions', [
                'statePath' => $field->getStatePath(),
                'hasCreate' => $hasCreateOption,
                'hasEdit' => $hasEditOption,
                'createLabel' => $field->getCreateOptionModalHeading(),
                'editLabel' => $field->getEditOptionModalHeading(),
            ])->render()
            : null;
    }

@endphp

@include('wire-forms::partials.field-wrapper-start')

@if($useCombobox)
    {{-- Combobox: delegate to the canonical shared owner. The search input is
         shown only when the field is searchable, so both variants match. --}}
    @include('wire-core::partials.searchable-select', [
        'selectId' => $field->getId(),
        'statePath' => $field->getWireModelAttribute(),
        'options' => $comboboxOptions,
        'placeholder' => $field->getPlaceholder(),
        'multiple' => $field->isMultiple(),
        'searchable' => $isSearchable,
        'searchPrompt' => $field->getSearchPrompt(),
        'noResultsMessage' => $field->getNoSearchResultsMessage(),
        'disabled' => $field->isDisabled(),
        'hasError' => $errors->has($field->getStatePath()),
        'remoteSearch' => $isRemoteSearch,
        'loadingMessage' => $field->getLoadingMessage(),
        'panelFooter' => $panelFooter,
        'sheetOnMobile' => $field->usesSheetOnMobile(),
        'mobileBreakpoint' => $field->getMobileBreakpoint(),
        // Honor live(): a live select must sync its selection to the server on
        // click (afterStateUpdated, visibleWhen siblings), not on the next
        // unrelated roundtrip.
        'live' => $field->isLive() || $field->isLiveOnBlur(),
    ])
@else
<select
        id="{{ $field->getId() }}"
        {{ $wireAttr }}="{{ $field->getWireModelAttribute() }}"
        @if($field->isMultiple())
            multiple
        @endif
        @if($field->isDisabled())
            disabled
        @endif
        @if($field->isRequired())
            required
        @endif
        {{-- Match the searchable combobox trigger (wire-core::partials.searchable-select)
             so searchable and non-searchable selects share one visual design. --}}
        @class([
            'block w-full rounded-md border border-gray-300 bg-white shadow-sm text-sm',
            'focus:border-primary-500 focus:ring-1 focus:ring-primary-500',
            'hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-150',
            'disabled:opacity-50 disabled:cursor-not-allowed',
            'dark:bg-gray-800 dark:border-gray-600 dark:text-white',
            'border-red-500' => $errors->has($field->getStatePath()),
        ])
>

    @if($field->getPlaceholder() && !$field->isMultiple())
        <option value="">{{ $field->getPlaceholder() }}</option>
    @endif

    @php $disabledValues = $field->getDisabledOptionValues(); @endphp
        @foreach($options as $value => $label)
            <option value="{{ $value }}" @if(in_array($value, $disabledValues, true)) disabled @endif>{{ $label }}</option>
        @endforeach
</select>
@endif

    @include('wire-forms::partials.field-wrapper-end')

@if($livewire !== null)
    {{-- Isolated create/edit option modals (one open at a time, keyed by state path). --}}
    @include('wire-forms::partials.select-option-modals')
@endif
