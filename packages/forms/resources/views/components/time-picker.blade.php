@php
    use NyonCode\WireCore\Foundation\Support\DateBoundary;
    use NyonCode\WireCore\Foundation\Support\MobileSheet;
    use NyonCode\WireForms\Components\TimePicker;

    assert($field instanceof TimePicker);

    $wireModifier = $field->getWireModelModifier();
    $wireAttr = 'wire:model' . ($wireModifier ? ".{$wireModifier}" : '');
    $fieldId = $field->getId();
    // Bounds arrive in the widget's own format; only the clock half means anything
    // to a time picker, and it is padded to H:i:s so a slot can be compared as a
    // plain string.
    $minBound = $field->getMinDate();
    $maxBound = $field->getMaxDate();
    $minTime = DateBoundary::timePart($minBound);
    $maxTime = DateBoundary::timePart($maxBound);
    $sheetOnMobile = $field->usesSheetOnMobile();
    $sheetBp = $field->getMobileBreakpoint();
    $sheetBpPx = MobileSheet::px($sheetBp);
    $sheetPanel = MobileSheet::panelPadded($sheetBp);
    $sheetScroll = MobileSheet::scrollArea($sheetBp);
    $sheetMotion = MobileSheet::motion($sheetBp);
    $sheetBackdrop = MobileSheet::backdropHide($sheetBp);
@endphp

@include('wire-forms::partials.field-wrapper-start')

@unless($field->isNative())
    @once
        @include('wire-core::partials.floating-assets')
    @endonce
@endunless

@if($field->isNative())
    @include('wire-forms::partials.date-time-native-input')
@else
    <div
            x-data="{
            open: false,
            {{-- Honor live(): mirror the other entangle-based fields. --}}
            value: $wire.entangle('{{ $field->getWireModelAttribute() }}'){{ $wireModifier ? '.' . $wireModifier : '' }},
            hasSeconds: @js($field->hasSeconds()),
            interval: @js($field->getMinutesStep()),
            minTime: @js($minTime),
            maxTime: @js($maxTime),
            displayFormat: @js($field->getDisplayFormat()),

            slots: [],
            _float: null,

            init() {
                this.buildSlots();

                this.$watch('open', (open) => {
                    if (open) {
                        this.$nextTick(() => {
                            this._float = this.$float(this.$refs.trigger, this.$refs.panel, { placement: 'bottom-start', offset: 4{{ $sheetOnMobile ? ', sheetOnMobile: true, sheetBreakpoint: '.$sheetBpPx : '' }} });
                            {{-- A list that always opened at 00:00 would make every
                                 afternoon a scroll; land on the current choice, or on
                                 the first slot the bounds allow. --}}
                            this.scrollToActive();
                        });
                    } else if (this._float) {
                        this._float();
                        this._float = null;
                    }
                });
            },

            pad(n) { return String(n).padStart(2, '0'); },

            {{-- Every slot of the day, at the field's interval. The interval is
                 clamped to >= 1 server-side, so this always terminates. --}}
            buildSlots() {
                const out = [];
                for (let m = 0; m < 24 * 60; m += this.interval) {
                    const value = this.pad(Math.floor(m / 60)) + ':' + this.pad(m % 60)
                        + (this.hasSeconds ? ':00' : '');
                    out.push({ value, label: this.format(value), disabled: this.isDisabled(value) });
                }
                this.slots = out;
            },

            {{-- Compare on a seconds-bearing string, the shape the bounds are in. --}}
            normalize(time) {
                if (! time) return null;
                const parts = String(time).split(':');
                return this.pad(parts[0] ?? 0) + ':' + this.pad(parts[1] ?? 0) + ':' + this.pad(parts[2] ?? 0);
            },

            isDisabled(time) {
                const t = this.normalize(time);
                if (this.minTime && t < this.normalize(this.minTime)) return true;
                if (this.maxTime && t > this.normalize(this.maxTime)) return true;
                return false;
            },

            {{-- A stored value need not sit on a slot boundary (the interval can
                 change under existing data), so this matches the instant, not the
                 string, and simply highlights nothing when it falls between slots. --}}
            isSelected(time) {
                if (! this.value) return false;
                return this.normalize(this.value) === this.normalize(time);
            },

            select(slot) {
                if (slot.disabled) return;
                this.value = slot.value;
                this.open = false;
            },

            scrollToActive() {
                const list = this.$refs.list;
                if (! list) return;
                const target = list.querySelector('[data-active=\'true\']')
                    ?? list.querySelector('button:not([disabled])');
                if (target) list.scrollTop = target.offsetTop - list.clientHeight / 2 + target.clientHeight / 2;
            },

            {{-- PHP date() tokens the picker can honour, on the time half only;
                 anything else passes through and \\x escapes a literal. Kept in step
                 with the DateTimePicker view's own formatter. --}}
            format(time) {
                if (! this.displayFormat) return time;

                const [h, mi, sec] = String(time).split(':');
                const num = (v) => String(parseInt(v ?? '0', 10) || 0);
                const tokens = { H: this.pad(h), G: num(h), i: this.pad(mi), s: this.pad(sec ?? '00') };

                let out = '';
                for (let i = 0; i < this.displayFormat.length; i++) {
                    const c = this.displayFormat[i];
                    if (c === '\\\\') { out += this.displayFormat[++i] ?? ''; continue; }
                    out += (c in tokens) ? tokens[c] : c;
                }

                return out;
            },

            get displayValue() {
                return this.value ? this.format(this.value) : '';
            },

            clear() {
                this.value = null;
            }
        }"
            class="relative"
    >
        {{-- Input trigger --}}
        <div class="relative" x-ref="trigger">
            <input
                    type="text"
                    {!! $field->getExtraInputAttributesHtml() !!}
                    id="{{ $fieldId }}"
                    :value="displayValue"
                    @click="open = !open" data-testid="form-time-{{ $field->getStatePath() }}-trigger"
                    @keydown.escape="open = false"
                    readonly
                    @if($field->getPlaceholder()) placeholder="{{ $field->getPlaceholder() }}" @endif
                    @if($field->isDisabled()) disabled @endif
                    @if($field->hasAutofocus()) autofocus @endif
                    @if($field->isRequired()) required @endif
                    @class([
                        'block w-full rounded-md border-gray-300 shadow-sm cursor-pointer',
                        'focus:border-primary-500 focus:ring-primary-500',
                        'hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-150',
                        'dark:bg-gray-800 dark:border-gray-600 dark:text-white text-sm',
                        'border-red-500 focus:border-red-500 focus:ring-red-500' => $errors->has($field->getStatePath()),
                    ])
            />
            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                     stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>

        {{-- Slot panel: floating from sm up, bottom sheet on a phone. --}}
        <template x-teleport="body">
        <div>
            @if($sheetOnMobile)
                <div
                        x-show="open"
                        x-cloak
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        @click="open = false"
                        class="fixed inset-0 z-40 bg-gray-500/60 dark:bg-gray-900/70 {{ $sheetBackdrop }}"
                ></div>
            @endif

            <div
                x-ref="panel"
                x-show="open"
                @click.outside="$clickedInside($event) || (open = false)"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1 {{ $sheetOnMobile ? $sheetMotion : '' }}"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-1 {{ $sheetOnMobile ? $sheetMotion : '' }}"
                @class([
                    'absolute top-0 left-0 z-50 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg p-2',
                    $sheetPanel => $sheetOnMobile,
                ])
                @if($sheetOnMobile) x-focus-trap="open" tabindex="-1" data-sheet-bp="{{ $sheetBpPx }}" @endif
                @keydown.escape="open = false"
                x-cloak
                style="display: none;"
        >
            @if($sheetOnMobile)
                @include('wire-core::partials.sheet-grabber', ['dismiss' => 'open = false', 'breakpoint' => $sheetBp])
            @endif

            {{-- The slot list. Capped in height so a 24-hour day scrolls inside the
                 panel instead of running off the viewport; on a sheet the panel owns
                 its own height, so the cap lifts.

                 The cap is 7 rows exactly (max-h-56 = 224px against a 32px row:
                 py-1.5 twice plus text-sm's 20px line-height). A cap that is not a
                 whole multiple leaves a row sliced in half against the footer rule,
                 which reads as a clipping bug rather than as "there is more". --}}
            <div
                x-ref="list"
                data-testid="form-time-{{ $field->getStatePath() }}-list"
                @class([
                    'overflow-y-auto overscroll-contain max-h-56 w-36',
                    // In a sheet the panel owns the height; this cap would only
                    // strand a 144px column inside it.
                    $sheetScroll => $sheetOnMobile,
                ])
            >
                <template x-for="slot in slots" :key="slot.value">
                    <button
                            type="button"
                            @click="select(slot)"
                            :disabled="slot.disabled"
                            :data-active="isSelected(slot.value)"
                            :data-testid="'form-time-{{ $field->getStatePath() }}-slot-' + slot.value"
                            :class="{
                                'bg-primary-500 text-white hover:bg-primary-600': isSelected(slot.value),
                                'opacity-40 cursor-not-allowed': slot.disabled,
                                'text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700': ! slot.disabled && ! isSelected(slot.value),
                            }"
                            class="block w-full text-left px-3 py-1.5 text-sm rounded transition-colors duration-150 tabular-nums"
                            x-text="slot.label"
                    ></button>
                </template>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-200 dark:border-gray-600">
                <button type="button" @click="clear(); open = false" data-testid="form-time-{{ $field->getStatePath() }}-clear"
                        class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors duration-150">
                    {{ __('Clear') }}
                </button>
            </div>
        </div>
        </div>
        </template>
    </div>
@endif

@include('wire-forms::partials.field-wrapper-end')
