@php
    use NyonCode\WireCore\Infolists\Components\ColorEntry;

    assert($field instanceof ColorEntry);

    $span = $field->getColumnSpan();
    $spanClass = match (true) {
        $span === 'full' => 'col-span-full',
        $span === 2 => 'sm:col-span-2',
        $span === 3 => 'sm:col-span-3',
        $span === 4 => 'sm:col-span-4',
        default => '',
    };
    $value = $field->getFormattedState();
@endphp

<div class="{{ $spanClass }}">
    @if($field->getLabel())
        <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
            {{ $field->getLabel() }}
        </div>
    @endif

    <div class="text-sm">
        @if($value !== '-' && $value !== '')
            <span
                    @class(['inline-flex items-center gap-2', 'cursor-pointer hover:opacity-70' => $field->isCopyable()])
                    @if($field->isCopyable())
                        x-data="{ copied: false }"
                    @click="navigator.clipboard.writeText(@js($value)); copied = true; setTimeout(() => copied = false, 1500)"
                    role="button"
                    title="{{ __('Copy') }}"
                @endif
            >
                <span class="w-5 h-5 rounded ring-1 ring-gray-200 dark:ring-gray-700"
                      style="background-color: {{ $value }};"></span>
                <span class="font-mono text-gray-700 dark:text-gray-300">{{ $value }}</span>
            </span>
        @else
            <span class="text-gray-400">{{ $field->getPlaceholder() ?? '-' }}</span>
        @endif
    </div>
</div>
