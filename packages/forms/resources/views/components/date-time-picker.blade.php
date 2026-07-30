@php
    use NyonCode\WireForms\Components\DateTimePicker;

     assert($field instanceof DateTimePicker);

    $wireModifier = $field->getWireModelModifier();
    $wireAttr = 'wire:model' . ($wireModifier ? ".{$wireModifier}" : '');
    $mode = $field->getMode();
    $hasDate = in_array($mode, ['date', 'datetime']);
    $hasTime = in_array($mode, ['time', 'datetime']);
    $firstDayOfWeek = $field->getFirstDayOfWeek();
    $disabledDates = $field->getDisabledDates();
    // Bounds arrive already in the widget's own format. The calendar compares
    // days and the clock compares times, so each half is split out once here
    // rather than re-parsed on every cell.
    $minBound = $field->getMinDate();
    $maxBound = $field->getMaxDate();
    $minDay = \NyonCode\WireCore\Foundation\Support\DateBoundary::datePart($minBound);
    $maxDay = \NyonCode\WireCore\Foundation\Support\DateBoundary::datePart($maxBound);
    $minTime = $hasTime ? \NyonCode\WireCore\Foundation\Support\DateBoundary::timePart($minBound) : null;
    $maxTime = $hasTime ? \NyonCode\WireCore\Foundation\Support\DateBoundary::timePart($maxBound) : null;
    $hoursStep = $field->getHoursStep() ?? 1;
    $minutesStep = $field->getMinutesStep() ?? 1;
    $hasSeconds = $field->hasSeconds();
    $secondsStep = $field->getSecondsStep() ?? 1;
    $fieldId = $field->getId();
    // Below the configured breakpoint the calendar becomes a bottom sheet instead
    // of a floating panel — unless disabled via ->sheetOnMobile(false) or config.
    $sheetOnMobile = $field->usesSheetOnMobile();
    $sheetBp = $field->getMobileBreakpoint();
    $sheetBpPx = \NyonCode\WireCore\Foundation\Support\MobileSheet::px($sheetBp);
    $sheetPanel = \NyonCode\WireCore\Foundation\Support\MobileSheet::panelPadded($sheetBp);
    $sheetMotion = \NyonCode\WireCore\Foundation\Support\MobileSheet::motion($sheetBp);
    $sheetBackdrop = \NyonCode\WireCore\Foundation\Support\MobileSheet::backdropHide($sheetBp);
@endphp

@include('wire-forms::partials.field-wrapper-start')

@unless($field->isNative())
    {{-- Scaffolding is identical for every date picker; emit it once per request
         (matters when several date fields, or a repeater of them, render). --}}
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
            hasDate: @js($hasDate),
            hasTime: @js($hasTime),
            hasSeconds: @js($hasSeconds),
            firstDayOfWeek: @js($firstDayOfWeek),
            disabledDates: @js($disabledDates),
            minDay: @js($minDay),
            maxDay: @js($maxDay),
            minTime: @js($minTime),
            maxTime: @js($maxTime),
            hoursStep: @js($hoursStep),
            minutesStep: @js($minutesStep),
            secondsStep: @js($secondsStep),
            displayFormat: @js($field->getDisplayFormat()),
            closeOnDateSelection: @js($field->shouldCloseOnDateSelection()),

            currentMonth: null,
            currentYear: null,
            hours: 0,
            minutes: 0,
            seconds: 0,

            dayNames: [],
            days: [],
            _float: null,

            init() {
                // Teleport + Floating UI: pin the calendar panel to the input while
                // open so table/modal overflow can never clip it.
                this.$watch('open', (open) => {
                    if (open) {
                        this.$nextTick(() => {
                            this._float = this.$float(this.$refs.trigger, this.$refs.panel, { placement: 'bottom-start', offset: 4{{ $sheetOnMobile ? ', sheetOnMobile: true, sheetBreakpoint: '.$sheetBpPx : '' }} });
                        });
                    } else if (this._float) {
                        this._float();
                        this._float = null;
                    }
                });

                if (this.value) {
                    const parsed = this.parseValue(this.value);
                    this.currentMonth = parsed.getMonth();
                    this.currentYear = parsed.getFullYear();
                    this.hours = parsed.getHours();
                    this.minutes = parsed.getMinutes();
                    this.seconds = parsed.getSeconds();
                } else {
                    // Open on today, or on the nearest month the bounds allow —
                    // landing on a month where every day is greyed out reads as
                    // a broken calendar.
                    const today = new Date();
                    let anchor = this.formatDateStr(today.getFullYear(), today.getMonth() + 1, today.getDate());
                    if (this.minDay && anchor < this.minDay) anchor = this.minDay;
                    if (this.maxDay && anchor > this.maxDay) anchor = this.maxDay;
                    const [y, m] = anchor.split('-');
                    this.currentYear = parseInt(y, 10);
                    this.currentMonth = parseInt(m, 10) - 1;
                }
                this.buildDayNames();
                this.buildCalendar();
            },

            parseValue(val) {
                if (!val) return new Date();
                // Handle YYYY-MM-DD, YYYY-MM-DDTHH:mm, HH:mm formats
                if (/^\d{2}:\d{2}/.test(val)) {
                    const parts = val.split(':');
                    const d = new Date();
                    d.setHours(parseInt(parts[0]), parseInt(parts[1]), parts[2] ? parseInt(parts[2]) : 0);
                    return d;
                }
                return new Date(val.replace(' ', 'T'));
            },

            buildDayNames() {
                const names = [];
                const base = new Date(2024, 0, 1); // Monday = 2024-01-01
                const offset = this.firstDayOfWeek === 0 ? 6 : this.firstDayOfWeek - 1;
                for (let i = 0; i < 7; i++) {
                    const d = new Date(base);
                    d.setDate(d.getDate() + i - offset);
                    names.push(d.toLocaleDateString(undefined, { weekday: 'short' }).slice(0, 2));
                }
                this.dayNames = names;
            },

            buildCalendar() {
                const first = new Date(this.currentYear, this.currentMonth, 1);
                let startDay = first.getDay() - this.firstDayOfWeek;
                if (startDay < 0) startDay += 7;

                const daysInMonth = new Date(this.currentYear, this.currentMonth + 1, 0).getDate();
                const daysInPrevMonth = new Date(this.currentYear, this.currentMonth, 0).getDate();

                const cells = [];

                // Previous month padding
                for (let i = startDay - 1; i >= 0; i--) {
                    cells.push({ day: daysInPrevMonth - i, current: false, date: null });
                }

                // Current month
                for (let d = 1; d <= daysInMonth; d++) {
                    const dateStr = this.formatDateStr(this.currentYear, this.currentMonth + 1, d);
                    cells.push({ day: d, current: true, date: dateStr });
                }

                // Next month padding
                const remaining = 42 - cells.length;
                for (let d = 1; d <= remaining; d++) {
                    cells.push({ day: d, current: false, date: null });
                }

                this.days = cells;
            },

            formatDateStr(y, m, d) {
                return y + '-' + String(m).padStart(2, '0') + '-' + String(d).padStart(2, '0');
            },

            {{-- Nothing selectable lies before the bounds, so the arrows stop there. --}}
            get canGoPrev() {
                if (!this.minDay) return true;
                const last = new Date(this.currentYear, this.currentMonth, 0);
                return this.formatDateStr(last.getFullYear(), last.getMonth() + 1, last.getDate()) >= this.minDay;
            },

            get canGoNext() {
                if (!this.maxDay) return true;
                const first = new Date(this.currentYear, this.currentMonth + 1, 1);
                return this.formatDateStr(first.getFullYear(), first.getMonth() + 1, first.getDate()) <= this.maxDay;
            },

            prevMonth() {
                if (!this.canGoPrev) return;
                if (this.currentMonth === 0) {
                    this.currentMonth = 11;
                    this.currentYear--;
                } else {
                    this.currentMonth--;
                }
                this.buildCalendar();
            },

            nextMonth() {
                if (!this.canGoNext) return;
                if (this.currentMonth === 11) {
                    this.currentMonth = 0;
                    this.currentYear++;
                } else {
                    this.currentMonth++;
                }
                this.buildCalendar();
            },

            isDisabled(dateStr) {
                if (!dateStr) return true;
                if (this.disabledDates.includes(dateStr)) return true;
                if (this.minDay && dateStr < this.minDay) return true;
                if (this.maxDay && dateStr > this.maxDay) return true;
                return false;
            },

            isSelected(dateStr) {
                if (!this.value || !dateStr) return false;
                return this.value.startsWith(dateStr);
            },

            isToday(dateStr) {
                if (!dateStr) return false;
                const today = new Date();
                return dateStr === this.formatDateStr(today.getFullYear(), today.getMonth() + 1, today.getDate());
            },

            selectDate(dateStr) {
                if (this.isDisabled(dateStr)) return;
                this.commitValue(dateStr);
                // A date-only picker has nothing left to ask, so it always closes.
                // With a time part the panel stays open to pick it — unless the
                // owner opted out via closeOnDateSelection().
                if (!this.hasTime || this.closeOnDateSelection) {
                    this.open = false;
                }
            },

            {{-- The value's own time, in the shape the state is written in. --}}
            timeValue() {
                return String(this.hours).padStart(2, '0') + ':' + String(this.minutes).padStart(2, '0')
                    + (this.hasSeconds ? ':' + String(this.seconds).padStart(2, '0') : '');
            },

            {{-- Pull the clock back inside the bounds before it reaches the value.
                 A bound's time only binds on its own day — 08:30 as a minimum
                 says nothing about the days after it — so a datetime picker
                 checks the day first, while a time-only one is always on its
                 own day. --}}
            clampTime(day) {
                if (!this.hasTime) return;

                const lower = (!this.hasDate || (this.minDay && day === this.minDay)) ? this.minTime : null;
                const upper = (!this.hasDate || (this.maxDay && day === this.maxDay)) ? this.maxTime : null;
                const current = String(this.hours).padStart(2, '0') + ':' + String(this.minutes).padStart(2, '0') + ':' + String(this.seconds).padStart(2, '0');

                let target = null;
                if (lower && current < lower) target = lower;
                else if (upper && current > upper) target = upper;
                if (!target) return;

                const [h, m, s] = target.split(':');
                this.hours = parseInt(h, 10);
                this.minutes = parseInt(m, 10);
                this.seconds = this.hasSeconds ? parseInt(s, 10) : 0;
            },

            commitValue(dateStr = null) {
                if (this.hasDate && this.hasTime) {
                    const d = dateStr || (this.value ? this.value.split(/[T ]/)[0] : this.formatDateStr(this.currentYear, this.currentMonth + 1, 1));
                    this.clampTime(d);
                    this.value = d + ' ' + this.timeValue();
                } else if (this.hasDate) {
                    this.value = dateStr;
                } else {
                    this.clampTime(null);
                    this.value = this.timeValue();
                }
            },

            adjustHours(dir) {
                this.hours = ((this.hours + dir * this.hoursStep) % 24 + 24) % 24;
                this.commitValue();
            },
            adjustMinutes(dir) {
                this.minutes = ((this.minutes + dir * this.minutesStep) % 60 + 60) % 60;
                this.commitValue();
            },
            adjustSeconds(dir) {
                this.seconds = ((this.seconds + dir * this.secondsStep) % 60 + 60) % 60;
                this.commitValue();
            },

            get displayValue() {
                if (!this.value) return '';
                if (!this.displayFormat) return this.value;

                // State is always a widget-parseable string (Y-m-d, Y-m-d\TH:i,
                // H:i, Y-m). Read it without Date(), which would drag the
                // browser's timezone into a value that carries none.
                const [datePart = '', timePart = ''] = String(this.value).split(/[T ]/);
                const [y, mo, d] = datePart.split('-');
                const [h, mi, sec] = timePart.split(':');

                const pad = (v) => String(v ?? '').padStart(2, '0');
                const num = (v) => String(parseInt(v ?? '0', 10) || 0);

                // PHP date() tokens the picker can honour; anything else is
                // passed through, and \\x escapes a literal.
                const tokens = {
                    d: pad(d), j: num(d),
                    m: pad(mo), n: num(mo),
                    Y: y ?? '', y: (y ?? '').slice(-2),
                    H: pad(h), G: num(h),
                    i: pad(mi), s: pad(sec),
                };

                let out = '';
                for (let i = 0; i < this.displayFormat.length; i++) {
                    const c = this.displayFormat[i];
                    if (c === '\\\\') { out += this.displayFormat[++i] ?? ''; continue; }
                    out += (c in tokens) ? tokens[c] : c;
                }

                return out;
            },

            get monthYearLabel() {
                const d = new Date(this.currentYear, this.currentMonth);
                return d.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
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
                    id="{{ $fieldId }}"
                    :value="displayValue"
                    @click="open = !open" data-testid="form-datetime-{{ $field->getStatePath() }}-trigger"
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
                    @if($hasDate)
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
                    @else
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    @endif
                </svg>
            </div>
        </div>

        {{-- Calendar panel: floating from sm up, bottom sheet on a phone (max-sm:
             classes, $float skips Floating UI) with a dimming backdrop. --}}
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
                    'absolute top-0 left-0 z-50 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg p-4',
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
            @if($hasDate)
                {{-- Month/year navigation --}}
                <div class="flex items-center justify-between mb-3">
                    <button type="button" @click="prevMonth()" :disabled="!canGoPrev"
                            :class="canGoPrev ? 'hover:bg-gray-100 dark:hover:bg-gray-700' : 'opacity-40 cursor-not-allowed'"
                            data-testid="form-datetime-{{ $field->getStatePath() }}-prev-month" aria-label="Previous month"
                            class="p-1 rounded text-gray-600 dark:text-gray-300 transition-colors duration-150">
                        {!! icon('chevron-left', 'w-4 h-4', 'h-4 w-4') !!}
                    </button>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white" x-text="monthYearLabel"></span>
                    <button type="button" @click="nextMonth()" :disabled="!canGoNext"
                            :class="canGoNext ? 'hover:bg-gray-100 dark:hover:bg-gray-700' : 'opacity-40 cursor-not-allowed'"
                            data-testid="form-datetime-{{ $field->getStatePath() }}-next-month" aria-label="Next month"
                            class="p-1 rounded text-gray-600 dark:text-gray-300 transition-colors duration-150">
                        {!! icon('chevron-right', 'w-4 h-4', 'h-4 w-4') !!}
                    </button>
                </div>

                {{-- Day names --}}
                <div class="grid grid-cols-7 gap-0 mb-1">
                    <template x-for="name in dayNames" :key="name">
                        <div class="text-center text-xs font-medium text-gray-500 dark:text-gray-400 py-1"
                             x-text="name"></div>
                    </template>
                </div>

                {{-- Calendar grid --}}
                <div class="grid grid-cols-7 gap-0">
                    <template x-for="(cell, idx) in days" :key="idx">
                        <button
                                type="button"
                                @click="cell.current && selectDate(cell.date)"
                                :data-testid="cell.current ? 'form-datetime-{{ $field->getStatePath() }}-day-' + cell.day : null"
                                :disabled="!cell.current || isDisabled(cell.date)"
                                :class="{
                                'text-gray-900 dark:text-white': cell.current && !isDisabled(cell.date),
                                'text-gray-300 dark:text-gray-600': !cell.current,
                                'opacity-40 cursor-not-allowed': cell.current && isDisabled(cell.date),
                                'bg-primary-500 text-white hover:bg-primary-600': isSelected(cell.date),
                                'ring-1 ring-primary-500': isToday(cell.date) && !isSelected(cell.date),
                                'hover:bg-gray-100 dark:hover:bg-gray-700': cell.current && !isDisabled(cell.date) && !isSelected(cell.date),
                            }"
                                class="w-8 h-8 text-sm rounded-full flex items-center justify-center transition-colors duration-150"
                                x-text="cell.day"
                        ></button>
                    </template>
                </div>
            @endif

            @if($hasTime)
                {{-- Time selector --}}
                <div @class(['flex items-center justify-center gap-2 pt-3 border-t border-gray-200 dark:border-gray-600 mt-3' => $hasDate, 'flex items-center justify-center gap-2' => !$hasDate])>
                    {{-- Hours --}}
                    <div class="flex flex-col items-center">
                        <button type="button" @click="adjustHours(1)" data-testid="form-datetime-{{ $field->getStatePath() }}-hours-up" aria-label="Hours up"
                                class="p-0.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors duration-150">
                            {!! icon('chevron-up', 'w-4 h-4', 'h-4 w-4') !!}
                        </button>
                        <span class="w-8 text-center text-sm font-medium text-gray-900 dark:text-white tabular-nums"
                              x-text="String(hours).padStart(2, '0')"></span>
                        <button type="button" @click="adjustHours(-1)" data-testid="form-datetime-{{ $field->getStatePath() }}-hours-down" aria-label="Hours down"
                                class="p-0.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors duration-150">
                            {!! icon('chevron-down', 'w-4 h-4', 'h-4 w-4') !!}
                        </button>
                    </div>

                    <span class="text-gray-400 text-sm font-medium">:</span>

                    {{-- Minutes --}}
                    <div class="flex flex-col items-center">
                        <button type="button" @click="adjustMinutes(1)" data-testid="form-datetime-{{ $field->getStatePath() }}-minutes-up" aria-label="Minutes up"
                                class="p-0.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors duration-150">
                            {!! icon('chevron-up', 'w-4 h-4', 'h-4 w-4') !!}
                        </button>
                        <span class="w-8 text-center text-sm font-medium text-gray-900 dark:text-white tabular-nums"
                              x-text="String(minutes).padStart(2, '0')"></span>
                        <button type="button" @click="adjustMinutes(-1)" data-testid="form-datetime-{{ $field->getStatePath() }}-minutes-down" aria-label="Minutes down"
                                class="p-0.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors duration-150">
                            {!! icon('chevron-down', 'w-4 h-4', 'h-4 w-4') !!}
                        </button>
                    </div>

                    @if($hasSeconds)
                        <span class="text-gray-400 text-sm font-medium">:</span>

                        {{-- Seconds --}}
                        <div class="flex flex-col items-center">
                            <button type="button" @click="adjustSeconds(1)" data-testid="form-datetime-{{ $field->getStatePath() }}-seconds-up" aria-label="Seconds up"
                                    class="p-0.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors duration-150">
                                {!! icon('chevron-up', 'w-4 h-4', 'h-4 w-4') !!}
                            </button>
                            <span class="w-8 text-center text-sm font-medium text-gray-900 dark:text-white tabular-nums"
                                  x-text="String(seconds).padStart(2, '0')"></span>
                            <button type="button" @click="adjustSeconds(-1)" data-testid="form-datetime-{{ $field->getStatePath() }}-seconds-down" aria-label="Seconds down"
                                    class="p-0.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors duration-150">
                                {!! icon('chevron-down', 'w-4 h-4', 'h-4 w-4') !!}
                            </button>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Footer --}}
            <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                <button type="button" @click="clear(); open = false" data-testid="form-datetime-{{ $field->getStatePath() }}-clear"
                        class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors duration-150">
                    {{ __('Clear') }}
                </button>
                @if($hasDate && $hasTime)
                    <button type="button" @click="open = false" data-testid="form-datetime-{{ $field->getStatePath() }}-done"
                            class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 transition-colors duration-150">
                        {{ __('Done') }}
                    </button>
                @endif
            </div>
        </div>
        </div>
        </template>
    </div>
@endif

@include('wire-forms::partials.field-wrapper-end')
