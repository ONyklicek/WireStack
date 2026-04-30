@php /** @var \NyonCode\WireForms\Components\DateTimePicker $field */
    $wireModifier = $field->getWireModelModifier();
    $wireAttr = 'wire:model' . ($wireModifier ? ".{$wireModifier}" : '');
@endphp

@include('wire-forms::partials.field-wrapper-start')

    <input
        type="{{ $field->getNativeInputType() }}"
        id="{{ $field->getId() }}"
        {{ $wireAttr }}="{{ $field->getWireModelAttribute() }}"
        @if($field->getPlaceholder()) placeholder="{{ $field->getPlaceholder() }}" @endif
        @if($field->getMinDate()) min="{{ $field->getMinDate() }}" @endif
        @if($field->getMaxDate()) max="{{ $field->getMaxDate() }}" @endif
        @if($field->isDisabled()) disabled @endif
        @if($field->isReadOnly()) readonly @endif
        @if($field->hasAutofocus()) autofocus @endif
        @if($field->isRequired()) required @endif
        @class([
            'block w-full rounded-md border-gray-300 shadow-sm',
            'focus:border-primary-500 focus:ring-primary-500',
            'dark:bg-gray-800 dark:border-gray-600 dark:text-white text-sm',
            'border-red-500 focus:border-red-500 focus:ring-red-500' => $errors->has($field->getStatePath()),
        ])
    />

@include('wire-forms::partials.field-wrapper-end')
