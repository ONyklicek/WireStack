@php

    use NyonCode\WireForms\Components\ColorPicker;

    assert($field instanceof ColorPicker);

    $wireModifier = $field->getWireModelModifier();
    $wireAttr = 'wire:model' . ($wireModifier ? ".$wireModifier" : '');
@endphp

@include('wire-forms::partials.field-wrapper-start')

<div
    x-data="{ color: @entangle($field->getWireModelAttribute()){{ $wireModifier ? '.' . $wireModifier : '' }} }"
    class="flex items-center gap-2"
>
    <input
            type="color"
            id="{{ $field->getId() }}"
            x-model="color"
            @if($field->isDisabled()) disabled @endif
            class="h-10 w-14 rounded border-gray-300 p-1 cursor-pointer dark:border-gray-600"
    />
    <input
            type="text"
            x-model="color"
            @if($field->getPlaceholder()) placeholder="{{ $field->getPlaceholder() }}" @endif
            @if($field->isDisabled()) disabled @endif
            @if($field->isReadOnly()) readonly @endif
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white text-sm"
    />
</div>

@include('wire-forms::partials.field-wrapper-end')
