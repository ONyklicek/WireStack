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
    // readOnly() and disabled() outrank typeable(); resolved once rather than
    // re-spelled down the markup.
    $typeable = $field->acceptsTypedInput();
@endphp

@include('wire-forms::partials.field-assets')

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
    {{-- Body registered as `wireTimePicker`
         (packages/forms/resources/js/fields/time-picker.js); only the
         per-instance config is markup. `state` is built here because
         `$wire.entangle` is an Alpine magic, in scope only in an x-data
         expression. --}}
            x-data="wireTimePicker({
            state: $wire.entangle('{{ $field->getWireModelAttribute() }}'){{ $wireModifier ? '.' . $wireModifier : '' }},
            hasSeconds: @js($field->hasSeconds()),
            interval: @js($field->getMinutesStep()),
            minTime: @js($minTime),
            maxTime: @js($maxTime),
            displayFormat: @js($field->getDisplayFormat()),
            typedFormat: @js($field->getTypedFormat()),
            typeable: @js($typeable),
            sheetOnMobile: @js($sheetOnMobile),
            sheetBreakpoint: @js($sheetBpPx),
        })"
            class="relative"
    >
        {{-- Input trigger --}}
        <div class="relative" x-ref="trigger">
            <input
                    type="text"
                    {!! $field->getExtraInputAttributesHtml() !!}
                    id="{{ $fieldId }}"
                    {{-- Mid-edit the box shows what is being typed; otherwise the
                         formatted value. --}}
                    :value="{{ $typeable ? 'typing ? typed : displayValue' : 'displayValue' }}"
                    data-testid="form-time-{{ $field->getStatePath() }}-trigger"
                    aria-haspopup="dialog"
                    :aria-expanded="open ? 'true' : 'false'"
                    @if($typeable)
                        @input="onTyped($event.target.value)"
                        @blur="commitTyped()"
                        @keydown.enter.prevent="commitTyped(); open = false"
                    @elseif(! $field->isReadOnly())
                        @keydown.enter.prevent="open = ! open"
                    @endif
                    @unless($field->isReadOnly())
                        {{-- Opens, never toggles: clicking to place the caret must
                             not close the list. The chevron is the toggle. --}}
                        @click="open = true"
                        @keydown.down.prevent="open = true"
                    @endunless
                    @keydown.escape="cancelTyped(); open = false"
                    @unless($typeable) readonly @endunless
                    @if($field->getPlaceholder()) placeholder="{{ $field->getPlaceholder() }}" @endif
                    @if($field->isDisabled()) disabled @endif
                    @if($field->hasAutofocus()) autofocus @endif
                    @if($field->isRequired()) required @endif
                    @class([
                        'block w-full rounded-md border-gray-300 shadow-sm',
                        'cursor-pointer' => ! $typeable,
                        'focus:border-primary-500 focus:ring-primary-500',
                        'hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-150',
                        'dark:bg-gray-800 dark:border-gray-600 dark:text-white text-sm',
                        'border-red-500 focus:border-red-500 focus:ring-red-500' => $errors->has($field->getStatePath()),
                    ])
            />
            {{-- A real button rather than decoration, and out of the tab order:
                 see the DateTimePicker view for why. --}}
            <button
                    type="button"
                    tabindex="-1"
                    data-testid="form-time-{{ $field->getStatePath() }}-toggle"
                    aria-label="{{ __('Open clock') }}"
                    @click="open = ! open"
                    @if($field->isDisabled() || $field->isReadOnly()) disabled @endif
                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 disabled:pointer-events-none transition-colors duration-150"
            >
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                     stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </button>
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
                {{-- The trigger is excluded explicitly: Alpine runs .outside in the
                     capture phase, so it would close the list a beat before the
                     chevron's own handler toggles it. --}}
                @click.outside="$clickedInside($event) || $refs.trigger.contains($event.target) || (open = false)"
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
