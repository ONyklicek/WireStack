@php
    use NyonCode\WireForms\Components\MorphToSelect;

    assert($field instanceof MorphToSelect);

    $wireModifier = $field->getWireModelModifier();
    $wireAttr = 'wire:model' . ($wireModifier ? ".{$wireModifier}" : '');
    $typeOptions = $field->getTypeOptions();
    $typeStatePath = $field->getTypeStatePath();
    $idStatePath = $field->getIdStatePath();
@endphp

@include('wire-forms::partials.field-wrapper-start')

<div
    x-data="{
        selectedType: $wire.entangle('{{ $typeStatePath }}'),
        typeOptions: @js(collect($field->getTypes())->mapWithKeys(fn ($type) => [$type->getModelClass() => $type->getOptions()])->all()),
        get idOptions() {
            return this.typeOptions[this.selectedType] || {};
        }
    }"
    x-init="$watch('selectedType', () => { $wire.set('{{ $idStatePath }}', null) })"
    class="grid grid-cols-2 gap-3"
>
    {{-- Type selector --}}
    <select
        id="{{ $field->getId() }}_type"
        {{ $wireAttr }}.live="{{ $typeStatePath }}"
        aria-label="{{ $field->getLabel() ? $field->getLabel() . ' — ' . __('Type') : __('Select type') }}"
        @if($field->isDisabled()) disabled @endif
        @class([
            'block w-full rounded-md border-gray-300 shadow-sm',
            'focus:border-primary-500 focus:ring-primary-500',
            'hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-150',
            'dark:bg-gray-800 dark:border-gray-600 dark:text-white text-sm',
        ])
    >
        <option value="">{{ $field->getPlaceholder() ?? __('Select type...') }}</option>
        @foreach($typeOptions as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
    </select>

    {{-- ID selector (dynamic based on type) --}}
    <select
        id="{{ $field->getId() }}_id"
        {{ $wireAttr }}="{{ $idStatePath }}"
        aria-label="{{ $field->getLabel() ? $field->getLabel() . ' — ' . __('Record') : __('Select record') }}"
        x-bind:disabled="!selectedType"
        @if($field->isDisabled()) disabled @endif
        @class([
            'block w-full rounded-md border-gray-300 shadow-sm',
            'focus:border-primary-500 focus:ring-primary-500',
            'hover:border-gray-400 dark:hover:border-gray-500 transition-all duration-150',
            'dark:bg-gray-800 dark:border-gray-600 dark:text-white text-sm',
            'disabled:opacity-50 disabled:cursor-not-allowed',
        ])
    >
        <option value="">{{ __('Select record...') }}</option>
        <template x-for="[value, label] in Object.entries(idOptions)" :key="value">
            <option :value="value" x-text="label"></option>
        </template>
    </select>
</div>

@include('wire-forms::partials.field-wrapper-end')
