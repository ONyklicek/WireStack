@php
    use NyonCode\WireForms\Components\Tags;
    assert($field instanceof Tags);
    $wireModifier = $field->getWireModelModifier();
@endphp

@include('wire-forms::partials.field-wrapper-start')

<div
    x-data="{
        tags: @entangle($field->getWireModelAttribute()){{ $wireModifier ? '.' . $wireModifier : '' }},
        input: '',
        suggestions: @js($field->getSuggestions()),
        splitKeys: @js($field->getSplitKeys()),
        allowNew: @js($field->isAllowNew()),
        allowDuplicates: @js($field->isAllowDuplicates()),
        maxItems: @js($field->getMaxItems()),
        focused: false,
        activeIndex: -1,

        init() {
            if (!Array.isArray(this.tags)) this.tags = [];
        },

        get filteredSuggestions() {
            if (!this.input.trim() || !this.suggestions.length) return [];
            return this.suggestions.filter(s =>
                s.toLowerCase().includes(this.input.toLowerCase()) &&
                (this.allowDuplicates || !this.tags.includes(s))
            );
        },

        get showDropdown() {
            return this.focused && this.filteredSuggestions.length > 0;
        },

        get atLimit() {
            return this.maxItems !== null && this.tags.length >= this.maxItems;
        },

        addTag(value) {
            const tag = value.trim();
            if (!tag || this.atLimit) return;
            if (!this.allowDuplicates && this.tags.includes(tag)) { this.input = ''; return; }
            if (!this.allowNew && !this.suggestions.includes(tag)) return;
            this.tags = [...this.tags, tag];
            this.input = '';
            this.activeIndex = -1;
        },

        removeTag(index) {
            this.tags = this.tags.filter((_, i) => i !== index);
        },

        onKeydown(event) {
            if (this.splitKeys.includes(event.key)) {
                event.preventDefault();
                this.activeIndex >= 0 && this.filteredSuggestions[this.activeIndex]
                    ? this.addTag(this.filteredSuggestions[this.activeIndex])
                    : this.addTag(this.input);
            } else if (event.key === 'Backspace' && !this.input && this.tags.length) {
                this.removeTag(this.tags.length - 1);
            } else if (event.key === 'ArrowDown') {
                event.preventDefault();
                this.activeIndex = Math.min(this.activeIndex + 1, this.filteredSuggestions.length - 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                this.activeIndex = Math.max(this.activeIndex - 1, -1);
            } else if (event.key === 'Escape') {
                this.focused = false;
            }
        }
    }"
    @click.outside="focused = false"
    @class([
        'rounded-xl border bg-white dark:bg-gray-900',
        'border-gray-300 dark:border-gray-600',
        'transition-colors duration-150',
        'focus-within:border-primary-500 focus-within:ring-1 focus-within:ring-primary-500',
        'border-red-500 focus-within:border-red-500 focus-within:ring-red-500' => $errors->has($field->getStatePath()),
        'opacity-50 pointer-events-none select-none' => $field->isDisabled(),
    ])
>
    {{-- ─── Input row ───────────────────────────────────────────────── --}}
    @unless($field->isDisabled())
        <div class="relative">
            <input
                type="text"
                x-model="input"
                @keydown="onKeydown($event)"
                @focus="focused = true; activeIndex = -1"
                @input="focused = true; activeIndex = -1"
                :placeholder="atLimit
                    ? @js(__('Limit reached'))
                    : @js($field->getPlaceholder() ?? __('Add tags...'))"
                :disabled="atLimit"
                {{--
                    Inline style je záměrný: @tailwindcss/forms přidává border/shadow
                    přes CSS selektory s vyšší specificitou než Tailwind utility třídy.
                    Inline style má nejvyšší specificitu a spolehlivě vše přebije.
                --}}
                style="border: none; outline: none; box-shadow: none; background: transparent; font-size: 16px;"
                class="block w-full px-4 py-3 text-gray-900 placeholder-gray-400 dark:text-white dark:placeholder-gray-500"
                @if($field->isReadOnly()) readonly @endif
            />

            {{-- Suggestions dropdown --}}
            <ul
                x-show="showDropdown"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="absolute left-0 right-0 z-50 mt-2 max-h-56 overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-xl ring-1 ring-black ring-opacity-5 dark:border-gray-700 dark:bg-gray-800"
            >
                <template x-for="(suggestion, i) in filteredSuggestions" :key="suggestion">
                    <li>
                        <button
                            type="button"
                            @click.prevent="addTag(suggestion)"
                            @mouseenter="activeIndex = i"
                            :class="activeIndex === i
                                ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                                : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700/50'"
                            class="flex w-full items-center justify-between px-4 py-3 text-sm transition-colors duration-75"
                        >
                            <span x-text="suggestion"></span>
                            <x-wire::icon
                                name="check"
                                class="h-4 w-4 shrink-0 text-primary-500 transition-opacity"
                                ::class="tags.includes(suggestion) ? 'opacity-100' : 'opacity-0'"
                            />
                        </button>
                    </li>
                </template>
            </ul>
        </div>
    @endunless

    {{-- ─── Tags row ────────────────────────────────────────────────── --}}
    <div
        x-show="tags.length > 0"
        class="flex flex-wrap gap-2 border-t border-gray-100 px-3 py-3 dark:border-gray-800"
    >
        <template x-for="(tag, index) in tags" :key="tag + index">
            <span
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="inline-flex items-center gap-1.5 rounded-full border border-gray-300 bg-gray-0 py-1 pl-3 pr-1.5 text-sm font-medium text-gray-800 transition-colors duration-100 hover:border-gray-400 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:hover:border-gray-500 dark:hover:bg-gray-600"
            >
                <span x-text="tag" class="leading-none"></span>

                @unless($field->isDisabled())
                    {{--
                        Touch target: visually 20×20px icon, maar de button zelf is 32×32px
                        via padding — voldoet aan de 44px iOS-richtlijn via de chip hoogte.
                    --}}
                    <button
                        type="button"
                        @click.stop="removeTag(index)"
                        style="outline: none;"
                        class="-mr-0.5 flex h-6 w-6 items-center justify-center rounded-full text-gray-500 transition-colors duration-100 hover:bg-gray-300 hover:text-gray-800 active:bg-gray-400 dark:text-gray-400 dark:hover:bg-gray-600 dark:hover:text-gray-100"
                        :aria-label="'Remove ' + tag"
                    >
                        <x-wire::icon name="outline:x-mark" class="h-4 w-4" />
                    </button>
                @endunless
            </span>
        </template>
    </div>

</div>

@include('wire-forms::partials.field-wrapper-end')
