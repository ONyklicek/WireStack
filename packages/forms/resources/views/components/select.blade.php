@php
    use NyonCode\WireForms\Components\Select;

    assert($field instanceof Select);

    $wireModifier = $field->getWireModelModifier();
    $wireAttr = 'wire:model' . ($wireModifier ? ".{$wireModifier}" : '');
    $options = $field->getOptions();
@endphp

@include('wire-forms::partials.field-wrapper-start')

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
        @class([
            'block w-full rounded-md border-gray-300 shadow-sm',
            'focus:border-primary-500 focus:ring-primary-500',
            'hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-150',
            'dark:bg-gray-800 dark:border-gray-600 dark:text-white text-sm',
            'border-red-500 focus:border-red-500 focus:ring-red-500' => $errors->has($field->getStatePath()),
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

    @include('wire-forms::partials.field-wrapper-end')
