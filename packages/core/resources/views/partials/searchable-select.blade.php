{{-- Canonical searchable <select> replacement (Teleport + Floating UI combobox).

     One shared owner for the searchable dropdown UI consumed by forms (Select)
     and the table SelectFilter. Supports both single and multiple selection and
     binds to a Livewire property path via $wire.entangle so the host component
     owns the state.

     Expected variables:
       $selectId         string                    DOM id for the trigger button
       $statePath        string                    Wire property path to entangle
       $options          array<array-key, string>  value => label map
       $placeholder      string|null               empty-state / clear label
       $multiple         bool                      multi-select mode (default false)
       $searchPrompt     string                    search input placeholder
       $noResultsMessage string                    shown when search matches nothing
       $disabled         bool                      disable the trigger (default false)
       $searchable       bool                      show the in-panel search input
                                                   (default true). When false the panel
                                                   is a plain option list, so searchable
                                                   and non-searchable selects share one
                                                   design. Ignored when $remoteSearch is
                                                   set (remote search needs the input).
       $hasError         bool                      apply error styling (default false)
       $panelFooter      string|null               extra HTML rendered at the bottom
                                                   of the dropdown panel (optional slot,
                                                   e.g. a "create new option" button)
       $remoteSearch     bool                      fetch options from the server as the
                                                   user types instead of filtering the
                                                   preloaded $options client-side. Calls
                                                   $wire.searchSelectOptions($statePath, term),
                                                   so the host must expose that method
                                                   (wire-forms WithForms). Default false.
       $loadingMessage   string                    shown while a remote search is in flight
       $live             bool                      entangle with the .live modifier so a
                                                   selection syncs to the server immediately
                                                   (fields with live()/afterStateUpdated,
                                                   table filters). Default false (deferred).
--}}
@php
    $selectId ??= 'searchable-select';
    $placeholder ??= null;
    $multiple ??= false;
    $disabled ??= false;
    $hasError ??= false;
    $panelFooter ??= null;
    $remoteSearch ??= false;
    $loadingMessage ??= null;
    $searchable ??= true;
    $live ??= false;
    // Below sm the listbox becomes a bottom sheet (comfortable native-style
    // picker) instead of a trigger-anchored floating panel. Callers pass the
    // resolved value; a bare include falls back to the global config.
    $sheetOnMobile ??= (bool) config('wire-core.mobile.sheet', true);
    $mobileBreakpoint ??= \NyonCode\WireCore\Foundation\Support\MobileSheet::breakpoint();
    $sheetBpPx = \NyonCode\WireCore\Foundation\Support\MobileSheet::px($mobileBreakpoint);
    $sheetPanel = \NyonCode\WireCore\Foundation\Support\MobileSheet::panel($mobileBreakpoint);
    $sheetMotion = \NyonCode\WireCore\Foundation\Support\MobileSheet::motion($mobileBreakpoint);
    $sheetBackdrop = \NyonCode\WireCore\Foundation\Support\MobileSheet::backdropHide($mobileBreakpoint);
    // Remote search cannot work without the input; force it on in that mode.
    $showSearch = $searchable || $remoteSearch;
@endphp

@include('wire-core::partials.floating-assets')

<div
    x-data="{
        open: false,
        search: '',
        multiple: @js($multiple),
        remote: @js($remoteSearch),
        loading: false,
        initialOptions: @js((object) $options),
        options: @js((object) $options),
        placeholder: @js($placeholder ?? ''),
        selected: $wire.entangle('{{ $statePath }}'){!! $live ? '.live' : '' !!},
        activeIndex: -1,
        _float: null,
        init() {
            // Teleport + Floating UI: pin the listbox to the trigger while open,
            // tearing the auto-updater down on close.
            this.$watch('open', (open) => {
                if (open) {
                    this.$nextTick(() => {
                        this._float = this.$float(this.$refs.trigger, this.$refs.panel, { placement: 'bottom-start', offset: 4, matchWidth: true{{ $sheetOnMobile ? ', sheetOnMobile: true, sheetBreakpoint: '.$sheetBpPx : '' }} });
                        this.$refs.searchInput?.focus();
                    });
                } else if (this._float) {
                    this._float();
                    this._float = null;
                }
            });

            // Remote search: ask the server for matches as the term changes, always
            // keeping the initial seed (which carries the current selection's label)
            // so the trigger stays readable.
            if (this.remote) {
                this.$watch('search', (value) => this.fetchRemote(value));
            }
        },
        async fetchRemote(search) {
            this.loading = true;
            this.activeIndex = -1;
            try {
                const results = await this.$wire.searchSelectOptions('{{ $statePath }}', search ?? '');
                this.options = { ...this.initialOptions, ...(results ?? {}) };
            } finally {
                this.loading = false;
            }
        },
        get filteredOptions() {
            // The server already narrowed remote results; never re-filter locally.
            if (this.remote) return this.options;
            if (!this.search) return this.options;
            const s = this.search.toLowerCase();
            return Object.fromEntries(
                Object.entries(this.options).filter(([k, v]) => String(v).toLowerCase().includes(s))
            );
        },
        get filteredKeys() {
            return Object.keys(this.filteredOptions);
        },
        isSelected(value) {
            if (this.multiple) {
                return Array.isArray(this.selected) && this.selected.map(String).includes(String(value));
            }
            return this.selected !== null && this.selected !== undefined && String(this.selected) === String(value);
        },
        get selectedLabel() {
            if (this.multiple) {
                const list = Array.isArray(this.selected) ? this.selected : [];
                return list.map((v) => this.options[v]).filter(Boolean).join(', ');
            }
            return this.options[this.selected] || '';
        },
        upsertOption(detail) {
            // Merge a freshly created/edited option into both option maps so the
            // new choice is selectable — and its label readable on the trigger —
            // without a page refresh (Alpine never re-reads render-time seeds on morph).
            if (!detail || detail.statePath !== '{{ $statePath }}' || detail.value === null || detail.value === undefined) return;
            this.initialOptions = { ...this.initialOptions, [detail.value]: detail.label };
            this.options = { ...this.options, [detail.value]: detail.label };
        },
        select(value) {
            if (this.multiple) {
                let list = Array.isArray(this.selected) ? [...this.selected] : [];
                const idx = list.map(String).indexOf(String(value));
                if (idx === -1) {
                    list.push(value);
                } else {
                    list.splice(idx, 1);
                }
                this.selected = list;
                return;
            }
            this.selected = value;
            this.open = false;
            this.search = '';
            this.activeIndex = -1;
        },
        clear() {
            this.selected = this.multiple ? [] : null;
            this.search = '';
            this.activeIndex = -1;
            if (!this.multiple) {
                this.open = false;
            }
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
            return '{{ $selectId }}-option-' + this.filteredKeys[this.activeIndex];
        }
    }"
    class="relative"
    @select-option-created.window="upsertOption($event.detail)"
    @select-option-updated.window="upsertOption($event.detail)"
>
    <button
        type="button"
        id="{{ $selectId }}"
        x-ref="trigger"
        @click="open = !open"
        @keydown.arrow-down.prevent="onArrowDown()"
        @keydown.arrow-up.prevent="onArrowUp()"
        @keydown.enter.prevent="onEnter()"
        @keydown.escape="open = false; activeIndex = -1"
        aria-haspopup="listbox"
        :aria-expanded="open"
        :aria-activedescendant="activeDescendant"
        @if($disabled) disabled @endif
        @class([
            'flex items-center justify-between w-full rounded-md border border-gray-300 shadow-sm px-3 py-2 text-left text-sm',
            'bg-white dark:bg-gray-800 dark:border-gray-600 dark:text-white',
            'focus:border-primary-500 focus:ring-1 focus:ring-primary-500',
            'hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-150',
            'disabled:opacity-50 disabled:cursor-not-allowed',
            'border-red-500' => $hasError,
        ])
    >
        <span x-text="selectedLabel || placeholder" :class="{ 'text-gray-400': !selectedLabel }"></span>
        <x-wire::icon name="chevron-down" class="w-4 h-4 text-gray-400 shrink-0 transition-transform duration-150" ::class="{ 'rotate-180': open }" />
    </button>

    {{-- Floating listbox from sm up; bottom sheet on a phone (max-sm: classes,
         $float skips Floating UI) with a dimming backdrop. --}}
    {{-- Floating listbox from sm up; bottom sheet on a phone (max-sm: classes,
         $float skips Floating UI) with a dimming backdrop. --}}
    <template x-teleport="body">
        <div>
            @if($sheetOnMobile)
                {{-- Backdrop: mobile-only, taps to close. --}}
                <div
                    x-show="open"
                    x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    @click="open = false; activeIndex = -1"
                    class="fixed inset-0 z-40 bg-gray-500/60 dark:bg-gray-900/70 {{ $sheetBackdrop }}"
                ></div>
            @endif

            <div
                x-ref="panel"
                x-show="open"
                @click.outside="open = false; activeIndex = -1"
                @if($sheetOnMobile) x-focus-trap="open" tabindex="-1" data-sheet-bp="{{ $sheetBpPx }}" @endif
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1 {{ $sheetOnMobile ? $sheetMotion : '' }}"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-1 {{ $sheetOnMobile ? $sheetMotion : '' }}"
                @class([
                    'absolute top-0 left-0 z-50 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-60 overflow-auto',
                    $sheetPanel => $sheetOnMobile,
                ])
            >
            @if($sheetOnMobile)
                @include('wire-core::partials.sheet-grabber', ['dismiss' => 'open = false; activeIndex = -1', 'breakpoint' => $mobileBreakpoint])
            @endif
            @if($showSearch)
            <div class="p-2">
                <input
                    type="text"
                    x-model.debounce.300ms="search"
                    @keydown.arrow-down.prevent="onArrowDown()"
                    @keydown.arrow-up.prevent="onArrowUp()"
                    @keydown.enter.prevent="onEnter()"
                    @keydown.escape="open = false; activeIndex = -1"
                    placeholder="{{ $searchPrompt }}"
                    aria-label="{{ $searchPrompt }}"
                    class="w-full rounded-md border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm focus:border-primary-500 focus:ring-primary-500 transition-colors duration-150"
                    x-ref="searchInput"
                />
            </div>
            @endif

            <ul class="py-1" role="listbox" :aria-activedescendant="activeDescendant" @if($multiple) aria-multiselectable="true" @endif>
                @if($placeholder !== null && $placeholder !== '')
                    <li role="option" aria-selected="false">
                        <button
                            type="button"
                            @click="clear()"
                            class="w-full px-3 py-2 text-left text-sm text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-150"
                        >
                            {{ $placeholder }}
                        </button>
                    </li>
                @endif

                <template x-for="([value, label], index) in Object.entries(filteredOptions)" :key="value">
                    <li role="option" :aria-selected="isSelected(value)" :id="'{{ $selectId }}-option-' + value">
                        <button
                            type="button"
                            @click="select(value)"
                            @mouseenter="activeIndex = index"
                            class="flex items-center justify-between gap-2 w-full px-3 py-2 text-left text-sm dark:text-white transition-colors duration-150"
                            :class="{
                                'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400': isSelected(value),
                                'bg-gray-100 dark:bg-gray-700': activeIndex === index && !isSelected(value),
                                'hover:bg-gray-100 dark:hover:bg-gray-700': activeIndex !== index && !isSelected(value),
                            }"
                        >
                            <span x-text="label"></span>
                            <x-wire::icon name="check" class="w-4 h-4 shrink-0" x-show="isSelected(value)" x-cloak />
                        </button>
                    </li>
                </template>

                @if($remoteSearch)
                    <li x-show="loading" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400" role="option" aria-disabled="true">
                        {{ $loadingMessage ?? __('Loading...') }}
                    </li>
                @endif

                <li x-show="!loading && Object.keys(filteredOptions).length === 0" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400" role="option" aria-disabled="true">
                    {{ $noResultsMessage }}
                </li>
            </ul>

            @if($panelFooter !== null && $panelFooter !== '')
                {!! $panelFooter !!}
            @endif
            </div>
        </div>
    </template>
</div>
