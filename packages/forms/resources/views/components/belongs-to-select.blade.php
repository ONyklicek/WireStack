@php
    use NyonCode\WireForms\Components\BelongsToSelect;

    assert($field instanceof BelongsToSelect);

    $wireModifier = $field->getWireModelModifier();
    $wireAttr = 'wire:model' . ($wireModifier ? ".{$wireModifier}" : '');
    $options = $field->getOptions();
    $isSearchable = $field->isSearchable() && !$field->isNative();
    $fieldId = $field->getId();
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
            activeIndex: -1,
            get filteredOptions() {
                if (!this.search) return this.options;
                const s = this.search.toLowerCase();
                return Object.fromEntries(
                    Object.entries(this.options).filter(([k, v]) => v.toLowerCase().includes(s))
                );
            },
            get filteredKeys() {
                return Object.keys(this.filteredOptions);
            },
            get selectedLabel() {
                return this.options[this.selected] || '';
            },
            select(value) {
                this.selected = value;
                this.open = false;
                this.search = '';
                this.activeIndex = -1;
            },
            clear() {
                this.selected = null;
                this.search = '';
                this.activeIndex = -1;
            },
            onArrowDown() {
                if (!this.open) { this.open = true; return; }
                if (this.activeIndex < this.filteredKeys.length - 1) this.activeIndex++;
            },
            onArrowUp() {
                if (this.activeIndex > 0) this.activeIndex--;
            },
            onEnter() {
                if (this.activeIndex >= 0 && this.activeIndex < this.filteredKeys.length) {
                    this.select(this.filteredKeys[this.activeIndex]);
                }
            },
            get activeDescendant() {
                if (this.activeIndex < 0) return null;
                return '{{ $fieldId }}-option-' + this.filteredKeys[this.activeIndex];
            }
        }"
        @click.outside="open = false; activeIndex = -1"
        class="relative"
    >
        <button
            type="button"
            id="{{ $fieldId }}"
            @click="open = !open"
            @keydown.arrow-down.prevent="onArrowDown()"
            @keydown.arrow-up.prevent="onArrowUp()"
            @keydown.enter.prevent="onEnter()"
            @keydown.escape="open = false; activeIndex = -1"
            aria-haspopup="listbox"
            :aria-expanded="open"
            :aria-activedescendant="activeDescendant"
            @if($field->isDisabled()) disabled @endif
            @class([
                'flex items-center justify-between w-full rounded-md border border-gray-300 shadow-sm px-3 py-2 text-left text-sm',
                'bg-white dark:bg-gray-800 dark:border-gray-600 dark:text-white',
                'focus:border-primary-500 focus:ring-1 focus:ring-primary-500',
                'hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-150',
                'disabled:opacity-50 disabled:cursor-not-allowed',
                'border-red-500' => $errors->has($field->getStatePath()),
            ])
        >
            <span x-text="selectedLabel || '{{ $field->getPlaceholder() ?? '' }}'"
                  :class="{ 'text-gray-400': !selectedLabel }"
            ></span>
            <x-wire::icon name="chevron-down" class="w-4 h-4 text-gray-400 shrink-0 transition-transform duration-150" ::class="{ 'rotate-180': open }" />
        </button>

        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-1"
            class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-60 overflow-auto"
        >
            <div class="p-2">
                <input
                    type="text"
                    x-model.debounce.300ms="search"
                    @keydown.arrow-down.prevent="onArrowDown()"
                    @keydown.arrow-up.prevent="onArrowUp()"
                    @keydown.enter.prevent="onEnter()"
                    @keydown.escape="open = false; activeIndex = -1"
                    placeholder="{{ $field->getSearchPrompt() }}"
                    aria-label="{{ $field->getSearchPrompt() }}"
                    class="w-full rounded-md border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm focus:border-primary-500 focus:ring-primary-500 transition-colors duration-150"
                    x-ref="searchInput"
                />
            </div>

            <ul class="py-1" role="listbox" :aria-activedescendant="activeDescendant">
                @if($field->getPlaceholder())
                    <li role="option" aria-selected="false">
                        <button
                            type="button"
                            @click="clear()"
                            class="w-full px-3 py-2 text-left text-sm text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-150"
                        >
                            {{ $field->getPlaceholder() }}
                        </button>
                    </li>
                @endif

                <template x-for="([value, label], index) in Object.entries(filteredOptions)" :key="value">
                    <li role="option" :aria-selected="selected == value" :id="'{{ $fieldId }}-option-' + value">
                        <button
                            type="button"
                            @click="select(value)"
                            @mouseenter="activeIndex = index"
                            class="w-full px-3 py-2 text-left text-sm dark:text-white transition-colors duration-150"
                            :class="{
                                'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400': selected == value,
                                'bg-gray-100 dark:bg-gray-700': activeIndex === index && selected != value,
                                'hover:bg-gray-100 dark:hover:bg-gray-700': activeIndex !== index && selected != value,
                            }"
                            x-text="label"
                        ></button>
                    </li>
                </template>

                <li x-show="Object.keys(filteredOptions).length === 0" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400" role="option" aria-disabled="true">
                    {{ $field->getNoSearchResultsMessage() }}
                </li>
            </ul>

            @if($field->hasCreateOptionForm())
                <div class="border-t border-gray-200 dark:border-gray-600 p-2">
                    <button
                        type="button"
                        wire:click="mountAction('{{ $field->getName() }}_create_option')"
                        class="w-full px-3 py-2 text-left text-sm text-primary-600 dark:text-primary-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors duration-150"
                    >
                        + {{ __('Create new') }}
                    </button>
                </div>
            @endif
        </div>
    </div>
@else
    <select
        id="{{ $fieldId }}"
        {{ $wireAttr }}="{{ $field->getWireModelAttribute() }}"
        @if($field->isDisabled()) disabled @endif
        @if($field->isRequired()) required @endif
        @class([
            'block w-full rounded-md border-gray-300 shadow-sm',
            'focus:border-primary-500 focus:ring-primary-500',
            'hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-150',
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
