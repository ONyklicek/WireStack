@php

    use NyonCode\WireForms\Components\TextInput;

    assert($field instanceof TextInput);
    $wireModifier = $field->getWireModelModifier();
    $debounceModifier = $field->getDebounceModifier();
    $wireAttr = 'wire:model' . ($wireModifier ? ".{$wireModifier}" : '') . $debounceModifier;
    $hasAffix = $field->hasAffix();
@endphp

@include('wire-forms::partials.field-wrapper-start')

@if($hasAffix)
    <div class="flex rounded-md shadow-sm">
        @if($field->getPrefixIcon())
            <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400 text-sm">
                    <x-wire::icon :name="$field->getPrefixIcon()" class="w-4 h-4"/>
                </span>
        @elseif($field->getPrefix())
            <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400 text-sm">
                    {{ $field->getPrefix() }}
                </span>
        @endif
        @endif

        <input
                type="{{ $field->getInputType() }}"
                id="{{ $field->getId() }}"
        {{ $wireAttr }}="{{ $field->getWireModelAttribute() }}"
        @if($field->getPlaceholder())
            placeholder="{{ $field->getPlaceholder() }}"
        @endif
        @if($field->getMinLength())
            minlength="{{ $field->getMinLength() }}"
        @endif
        @if($field->getMaxLength())
            maxlength="{{ $field->getMaxLength() }}"
        @endif
        @if($field->getMinValue() !== null)
            min="{{ $field->getMinValue() }}"
        @endif
        @if($field->getMaxValue() !== null)
            max="{{ $field->getMaxValue() }}"
        @endif
        @if($field->getStep())
            step="{{ $field->getStep() }}"
        @endif
        @if($field->getInputMode())
            inputmode="{{ $field->getInputMode() }}"
        @endif
        @if($field->getAutocomplete())
            autocomplete="{{ $field->getAutocomplete() }}"
        @endif
        @if($field->isDisabled())
            disabled
        @endif
        @if($field->isReadOnly())
            readonly
        @endif
        @if($field->hasAutofocus())
            autofocus
        @endif
        @if($field->isRequired())
            required
        @endif
        @if($field->getDatalistOptions())
            list="{{ $field->getId() }}-datalist"
        @endif
        @if($field->getMask())
            x-mask="{{ $field->getMask() }}"
        @endif
        @class([
            'block w-full rounded-md border-gray-300 shadow-sm',
            'focus:border-primary-500 focus:ring-primary-500',
            'dark:bg-gray-800 dark:border-gray-600 dark:text-white text-sm',
            'border-red-500 focus:border-red-500 focus:ring-red-500' => $errors->has($field->getStatePath()),
            'rounded-l-none' => $hasAffix && ($field->getPrefix() || $field->getPrefixIcon()),
            'rounded-r-none' => $hasAffix && ($field->getSuffix() || $field->getSuffixIcon()),
        ])
        />

        @if($hasAffix)
            @if($field->getSuffixIcon())
                <span class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400 text-sm">
                    <x-wire::icon :name="$field->getSuffixIcon()" class="w-4 h-4"/>
                </span>
            @elseif($field->getSuffix())
                <span class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400 text-sm">
                    {{ $field->getSuffix() }}
                </span>
            @endif
    </div>
@endif

@if($field->getDatalistOptions())
    <datalist id="{{ $field->getId() }}-datalist">
        @foreach($field->getDatalistOptions() as $option)
            <option value="{{ $option }}"></option>
        @endforeach
    </datalist>
@endif

@include('wire-forms::partials.field-wrapper-end')
