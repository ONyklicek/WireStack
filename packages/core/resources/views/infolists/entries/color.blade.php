@php
    use NyonCode\WireCore\Infolists\Components\ColorEntry;

    assert($field instanceof ColorEntry);

    $spanClass = $field->getColumnSpanClass();
    $value = $field->getFormattedState();
@endphp

<div class="{{ $spanClass }}">
    @if($field->getLabel())
        @include('wire-core::partials.entry-label', ['text' => $field->getLabel()])
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

    @if($field->hasActions())@include('wire-core::infolists.entry-actions')@endif
</div>
