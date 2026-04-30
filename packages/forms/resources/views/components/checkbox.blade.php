@php /** @var \NyonCode\WireForms\Components\Checkbox $field */
    $wireModifier = $field->getWireModelModifier();
    $wireAttr = 'wire:model' . ($wireModifier ? ".{$wireModifier}" : '');
@endphp

@include('wire-forms::partials.field-wrapper-start', ['hideLabel' => true])

    <div class="flex items-start gap-2">
        <input
            type="checkbox"
            id="{{ $field->getId() }}"
            {{ $wireAttr }}="{{ $field->getWireModelAttribute() }}"
            @if($field->isDisabled()) disabled @endif
            @if($field->isRequired()) required @endif
            class="mt-0.5 rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-600"
        />
        <div>
            @if($field->getLabel())
                <label for="{{ $field->getId() }}" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ $field->getLabel() }}
                    @if($field->isRequired())
                        <span class="text-red-500">*</span>
                    @endif
                </label>
            @endif
            @if($field->getDescription())
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $field->getDescription() }}</p>
            @endif
        </div>
    </div>

@include('wire-forms::partials.field-wrapper-end')
