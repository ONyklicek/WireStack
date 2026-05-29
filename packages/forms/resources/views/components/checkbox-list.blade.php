@php /** @var \NyonCode\WireForms\Components\CheckboxList $field */
    $wireModifier = $field->getWireModelModifier();
    $wireAttr = 'wire:model' . ($wireModifier ? ".{$wireModifier}" : '');
    $options = $field->getOptions();
    $columns = $field->getColumns();
@endphp

@include('wire-forms::partials.field-wrapper-start')

    <div
        x-data="{
            search: '',
            selectAll() { @this.set('{{ $field->getWireModelAttribute() }}', {{ json_encode(array_keys($options)) }}) },
            deselectAll() { @this.set('{{ $field->getWireModelAttribute() }}', []) },
        }"
        class="border border-gray-300 dark:border-gray-600 rounded-md overflow-hidden"
    >
        @if($field->isSearchable())
            <div class="p-2 border-b border-gray-200 dark:border-gray-700">
                <input
                    type="text"
                    x-model="search"
                    placeholder="{{ $field->getSearchPrompt() }}"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-150 dark:bg-gray-800 dark:border-gray-600 dark:text-white text-sm"
                />
            </div>
        @endif

        @if($field->isBulkToggleable())
            <div class="flex gap-2 px-3 py-2 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                <button type="button" @click="selectAll()" class="text-xs text-primary-600 hover:text-primary-700 font-medium">
                    {{ $field->getSelectAllLabel() }}
                </button>
                <span class="text-gray-300 dark:text-gray-600">|</span>
                <button type="button" @click="deselectAll()" class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 font-medium">
                    {{ $field->getDeselectAllLabel() }}
                </button>
            </div>
        @endif

        <div class="max-h-60 overflow-y-auto p-3">
            <div @class([
                'grid gap-2',
                'grid-cols-1' => $columns === 1,
                'grid-cols-2' => $columns === 2,
                'grid-cols-3' => $columns === 3,
                'grid-cols-4' => $columns === 4,
            ])>
                @foreach($options as $value => $label)
                    <div
                        class="flex items-center gap-2"
                        @if($field->isSearchable()) x-show="!search || @js(strtolower($label)).includes(search.toLowerCase())" @endif
                    >
                        <input
                            type="checkbox"
                            id="{{ $field->getId() }}-{{ $value }}"
                            {{ $wireAttr }}="{{ $field->getWireModelAttribute() }}"
                            value="{{ $value }}"
                            @if($field->isDisabled()) disabled @endif
                            class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 transition-colors duration-150 dark:bg-gray-800 dark:border-gray-600"
                        />
                        <label for="{{ $field->getId() }}-{{ $value }}" class="text-sm text-gray-700 dark:text-gray-300">
                            {{ $label }}
                        </label>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

@include('wire-forms::partials.field-wrapper-end')
