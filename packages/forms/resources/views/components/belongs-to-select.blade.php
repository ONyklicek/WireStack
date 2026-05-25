@php
    use NyonCode\WireForms\Components\BelongsToSelect;

    assert($field instanceof BelongsToSelect);

    $wireModifier = $field->getWireModelModifier();
    $wireAttr = 'wire:model' . ($wireModifier ? ".{$wireModifier}" : '');
    $options = $field->getOptions();
    $isSearchable = $field->isSearchable() && !$field->isNative();
@endphp

@include('wire-forms::partials.field-wrapper-start')

@if($isSearchable)
    <div
        x-data="{
            open: false,
            search: '',
            options: @js($options),
            selected: $wire.entangle('{{ $field->getWireModelAttribute() }}'),
            loading: false,
            get filteredOptions() {
                if (!this.search) return this.options;
                const s = this.search.toLowerCase();
                return Object.fromEntries(
                    Object.entries(this.options).filter(([k, v]) => v.toLowerCase().includes(s))
                );
            },
            get selectedLabel() {
                return this.options[this.selected] || '';
            },
            select(value) {
                this.selected = value;
                this.open = false;
                this.search = '';
            },
            clear() {
                this.selected = null;
                this.search = '';
            }
        }"
        @click.outside="open = false"
        class="relative"
    >
        <button
            type="button"
            @click="open = !open"
            @class([
                'flex items-center justify-between w-full rounded-md border border-gray-300 shadow-sm px-3 py-2 text-left text-sm',
                'bg-white dark:bg-gray-800 dark:border-gray-600 dark:text-white',
                'focus:border-primary-500 focus:ring-1 focus:ring-primary-500',
                'border-red-500' => $errors->has($field->getStatePath()),
            ])
            @if($field->isDisabled()) disabled @endif
        >
            <span x-text="selectedLabel || '{{ $field->getPlaceholder() ?? '' }}'"
                  :class="{ 'text-gray-400': !selectedLabel }"
            ></span>
            <svg class="w-4 h-4 text-gray-400 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
        </button>

        <div
            x-show="open"
            x-transition
            class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-60 overflow-auto"
        >
            <div class="p-2">
                <input
                    type="text"
                    x-model.debounce.300ms="search"
                    placeholder="{{ $field->getSearchPrompt() }}"
                    class="w-full rounded-md border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm focus:border-primary-500 focus:ring-primary-500"
                    @keydown.escape="open = false"
                />
            </div>

            <ul class="py-1">
                @if($field->getPlaceholder())
                    <li>
                        <button
                            type="button"
                            @click="clear()"
                            class="w-full px-3 py-2 text-left text-sm text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700"
                        >
                            {{ $field->getPlaceholder() }}
                        </button>
                    </li>
                @endif

                <template x-for="[value, label] in Object.entries(filteredOptions)" :key="value">
                    <li>
                        <button
                            type="button"
                            @click="select(value)"
                            class="w-full px-3 py-2 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-white"
                            :class="{ 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400': selected == value }"
                            x-text="label"
                        ></button>
                    </li>
                </template>

                <li x-show="Object.keys(filteredOptions).length === 0" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ $field->getNoSearchResultsMessage() }}
                </li>
            </ul>

            @if($field->hasCreateOptionForm())
                <div class="border-t border-gray-200 dark:border-gray-600 p-2">
                    <button
                        type="button"
                        wire:click="mountAction('{{ $field->getName() }}_create_option')"
                        class="w-full px-3 py-2 text-left text-sm text-primary-600 dark:text-primary-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded"
                    >
                        + {{ __('Create new') }}
                    </button>
                </div>
            @endif
        </div>
    </div>
@else
    <select
        id="{{ $field->getId() }}"
        {{ $wireAttr }}="{{ $field->getWireModelAttribute() }}"
        @if($field->isDisabled()) disabled @endif
        @if($field->isRequired()) required @endif
        @class([
            'block w-full rounded-md border-gray-300 shadow-sm',
            'focus:border-primary-500 focus:ring-primary-500',
            'dark:bg-gray-800 dark:border-gray-600 dark:text-white text-sm',
            'border-red-500 focus:border-red-500 focus:ring-red-500' => $errors->has($field->getStatePath()),
        ])
    >
        @if($field->getPlaceholder())
            <option value="">{{ $field->getPlaceholder() }}</option>
        @endif

        @foreach($options as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
    </select>
@endif

@include('wire-forms::partials.field-wrapper-end')
