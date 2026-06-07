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
    $hoursStep = $field->getHoursStep() ?? 1;
    $minutesStep = $field->getMinutesStep() ?? 1;
    $hasSeconds = $field->hasSeconds();
    $secondsStep = $field->getSecondsStep() ?? 1;
    $fieldId = $field->getId();
@endphp

@include('wire-forms::partials.field-wrapper-start')

@if($field->isNative())
    <input
            type="{{ $field->getNativeInputType() }}"
            id="{{ $fieldId }}"
    {{ $wireAttr }}="{{ $field->getWireModelAttribute() }}"
    @if($field->getPlaceholder())
        placeholder="{{ $field->getPlaceholder() }}"
    @endif
    @if($field->getMinDate())
        min="{{ $field->getMinDate() }}"
    @endif
    @if($field->getMaxDate())
        max="{{ $field->getMaxDate() }}"
    @endif
    @if($field->isDisabled())
        disabled
    @endif
    @if($field->isReadOnly())
        readonly
    @endif
    @if($field->hasAutofocus())
        autofocus
    @endif
    @if($field->isRequired())
        required
    @endif
    @class([
        'block w-full rounded-md border-gray-300 shadow-sm',
        'focus:border-primary-500 focus:ring-primary-500',
        'hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-150',
        'dark:bg-gray-800 dark:border-gray-600 dark:text-white text-sm',
        'border-red-500 focus:border-red-500 focus:ring-red-500' => $errors->has($field->getStatePath()),
    ])
    />
@else
    <div
            x-data="{
            open: false,
            value: $wire.entangle('{{ $field->getWireModelAttribute() }}'),
            hasDate: @js($hasDate),
            hasTime: @js($hasTime),
            hasSeconds: @js($hasSeconds),
            firstDayOfWeek: @js($firstDayOfWeek),
            disabledDates: @js($disabledDates),
            minDate: @js($field->getMinDate()),
            maxDate: @js($field->getMaxDate()),
            hoursStep: @js($hoursStep),
            minutesStep: @js($minutesStep),
            secondsStep: @js($secondsStep),

            currentMonth: null,
            currentYear: null,
            hours: 0,
            minutes: 0,
            seconds: 0,

            dayNames: [],
            days: [],

            init() {
                const today = new Date();
                if (this.value) {
                    const parsed = this.parseValue(this.value);
                    this.currentMonth = parsed.getMonth();
                    this.currentYear = parsed.getFullYear();
                    this.hours = parsed.getHours();
                    this.minutes = parsed.getMinutes();
                    this.seconds = parsed.getSeconds();
                } else {
                    this.currentMonth = today.getMonth();
                    this.currentYear = today.getFullYear();
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

            prevMonth() {
                if (this.currentMonth === 0) {
                    this.currentMonth = 11;
                    this.currentYear--;
                } else {
                    this.currentMonth--;
                }
                this.buildCalendar();
            },

            nextMonth() {
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
                if (this.minDate && dateStr < this.minDate) return true;
                if (this.maxDate && dateStr > this.maxDate) return true;
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
                if (!this.hasTime) {
                    this.open = false;
                }
            },

            commitValue(dateStr = null) {
                if (this.hasDate && this.hasTime) {
                    const d = dateStr || (this.value ? this.value.split(/[T ]/)[0] : this.formatDateStr(this.currentYear, this.currentMonth + 1, 1));
                    const timePart = String(this.hours).padStart(2, '0') + ':' + String(this.minutes).padStart(2, '0') + (this.hasSeconds ? ':' + String(this.seconds).padStart(2, '0') : '');
                    this.value = d + ' ' + timePart;
                } else if (this.hasDate) {
                    this.value = dateStr;
                } else {
                    this.value = String(this.hours).padStart(2, '0') + ':' + String(this.minutes).padStart(2, '0') + (this.hasSeconds ? ':' + String(this.seconds).padStart(2, '0') : '');
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
                return this.value;
            },

            get monthYearLabel() {
                const d = new Date(this.currentYear, this.currentMonth);
                return d.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
            },

            clear() {
                this.value = null;
            }
        }"
            @click.outside="open = false"
            class="relative"
    >
        {{-- Input trigger --}}
        <div class="relative">
            <input
                    type="text"
                    id="{{ $fieldId }}"
                    :value="displayValue"
                    @click="open = !open"
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

        {{-- Dropdown panel --}}
        <div
                x-show="open"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-1"
                class="absolute z-50 mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg p-4"
                @keydown.escape="open = false"
        >
            @if($hasDate)
                {{-- Month/year navigation --}}
                <div class="flex items-center justify-between mb-3">
                    <button type="button" @click="prevMonth()"
                            class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition-colors duration-150">
                        <x-wire::icon name="chevron-left" class="h-4 w-4"/>
                    </button>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white" x-text="monthYearLabel"></span>
                    <button type="button" @click="nextMonth()"
                            class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition-colors duration-150">
                        <x-wire::icon name="chevron-right" class="h-4 w-4"/>
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
                        <button type="button" @click="adjustHours(1)"
                                class="p-0.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors duration-150">
                            <x-wire::icon name="chevron-up" class="h-4 w-4"/>
                        </button>
                        <span class="w-8 text-center text-sm font-medium text-gray-900 dark:text-white tabular-nums"
                              x-text="String(hours).padStart(2, '0')"></span>
                        <button type="button" @click="adjustHours(-1)"
                                class="p-0.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors duration-150">
                            <x-wire::icon name="chevron-down" class="h-4 w-4"/>
                        </button>
                    </div>

                    <span class="text-gray-400 text-sm font-medium">:</span>

                    {{-- Minutes --}}
                    <div class="flex flex-col items-center">
                        <button type="button" @click="adjustMinutes(1)"
                                class="p-0.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors duration-150">
                            <x-wire::icon name="chevron-up" class="h-4 w-4"/>
                        </button>
                        <span class="w-8 text-center text-sm font-medium text-gray-900 dark:text-white tabular-nums"
                              x-text="String(minutes).padStart(2, '0')"></span>
                        <button type="button" @click="adjustMinutes(-1)"
                                class="p-0.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors duration-150">
                            <x-wire::icon name="chevron-down" class="h-4 w-4"/>
                        </button>
                    </div>

                    @if($hasSeconds)
                        <span class="text-gray-400 text-sm font-medium">:</span>

                        {{-- Seconds --}}
                        <div class="flex flex-col items-center">
                            <button type="button" @click="adjustSeconds(1)"
                                    class="p-0.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors duration-150">
                                <x-wire::icon name="chevron-up" class="h-4 w-4"/>
                            </button>
                            <span class="w-8 text-center text-sm font-medium text-gray-900 dark:text-white tabular-nums"
                                  x-text="String(seconds).padStart(2, '0')"></span>
                            <button type="button" @click="adjustSeconds(-1)"
                                    class="p-0.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors duration-150">
                                <x-wire::icon name="chevron-down" class="h-4 w-4"/>
                            </button>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Footer --}}
            <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                <button type="button" @click="clear(); open = false"
                        class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors duration-150">
                    {{ __('Clear') }}
                </button>
                @if($hasDate && $hasTime)
                    <button type="button" @click="open = false"
                            class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 transition-colors duration-150">
                        {{ __('Done') }}
                    </button>
                @endif
            </div>
        </div>
    </div>
@endif

@include('wire-forms::partials.field-wrapper-end')
