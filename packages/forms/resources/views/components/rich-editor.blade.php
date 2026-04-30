@php /** @var \NyonCode\WireForms\Components\RichEditor $field */
    $wireModifier = $field->getWireModelModifier();
    $wireAttr = 'wire:model' . ($wireModifier ? ".{$wireModifier}" : '');
@endphp

@include('wire-forms::partials.field-wrapper-start')

    {{-- Rich editor requires JS integration (TipTap, Trix, etc.) --}}
    {{-- This provides the basic textarea fallback with wire:model --}}
    <textarea
        id="{{ $field->getId() }}"
        {{ $wireAttr }}="{{ $field->getWireModelAttribute() }}"
        @if($field->getPlaceholder()) placeholder="{{ $field->getPlaceholder() }}" @endif
        @if($field->getMaxLength()) maxlength="{{ $field->getMaxLength() }}" @endif
        @if($field->isDisabled()) disabled @endif
        @if($field->isReadOnly()) readonly @endif
        @if($field->isRequired()) required @endif
        rows="6"
        @class([
            'block w-full rounded-md border-gray-300 shadow-sm',
            'focus:border-primary-500 focus:ring-primary-500',
            'dark:bg-gray-800 dark:border-gray-600 dark:text-white text-sm',
            'border-red-500 focus:border-red-500 focus:ring-red-500' => $errors->has($field->getStatePath()),
        ])
    ></textarea>

@include('wire-forms::partials.field-wrapper-end')
