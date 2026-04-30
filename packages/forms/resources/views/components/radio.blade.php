@php
    use NyonCode\WireForms\Components\Radio;

    assert($field instanceof Radio);

    $wireModifier = $field->getWireModelModifier();
    $wireAttr = 'wire:model' . ($wireModifier ? ".{$wireModifier}" : '');
    $options = $field->getOptions();
    $descriptions = $field->getDescriptions();
@endphp

@include('wire-forms::partials.field-wrapper-start')

<div @class([
        'space-y-2' => !$field->isInline(),
        'flex flex-wrap gap-4' => $field->isInline(),
    ])>
    @foreach($options as $value => $label)
        <div class="flex items-start gap-2">
            <input
                    type="radio"
                    id="{{ $field->getId() }}-{{ $value }}"
            {{ $wireAttr }}="{{ $field->getWireModelAttribute() }}"
            value="{{ $value }}"
            @if($field->isDisabled())
                disabled
            @endif
            class="mt-0.5 border-gray-300 text-primary-600 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-600"
            />
            <div>
                <label for="{{ $field->getId() }}-{{ $value }}" class="text-sm text-gray-700 dark:text-gray-300">
                    {{ $label }}
                </label>
                @if(isset($descriptions[$value]))
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $descriptions[$value] }}</p>
                @endif
            </div>
        </div>
    @endforeach
</div>

@include('wire-forms::partials.field-wrapper-end')
