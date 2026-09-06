@php
    use NyonCode\WireForms\Components\DateRangePicker;

    assert($layout instanceof DateRangePicker);

    $from = $layout->getFromPicker();
    $until = $layout->getUntilPicker();
    $presets = $layout->getPresets();
@endphp

<div class="space-y-2">
    @if($layout->getLabel())
        <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $layout->getLabel() }}</span>
    @endif

    @if($presets !== [])
        {{-- One click writes both ends. The values were resolved in PHP, so the
             browser never has to agree with the server about what "this month"
             means — and `.live` is what makes the two pickers redraw with them. --}}
        <div
            x-data="{
                from: @entangle($from->getStatePath()).live,
                to: @entangle($until->getStatePath()).live,
            }"
            class="flex flex-wrap gap-2"
        >
            @foreach($presets as $label => $range)
                <button
                    type="button"
                    @click="from = @js($range[0]); to = @js($range[1])"
                    data-testid="form-date-range-{{ $from->getStatePath() }}-preset-{{ $loop->index }}"
                    class="rounded-full border border-gray-300 px-3 py-1 text-xs text-gray-600 hover:border-primary-500 hover:text-primary-600 dark:border-gray-600 dark:text-gray-300 dark:hover:text-primary-400"
                >{{ $label }}</button>
            @endforeach
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        @if($from->isVisible())
            {{ $from }}
        @endif
        @if($until->isVisible())
            {{ $until }}
        @endif
    </div>
</div>
