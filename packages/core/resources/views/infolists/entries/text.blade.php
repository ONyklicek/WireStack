@php
    use NyonCode\WireCore\Infolists\Components\TextEntry;

    assert($field instanceof TextEntry);

    $span = $field->getColumnSpan();
    $spanClass = match (true) {
        $span === 'full' => 'col-span-full',
        $span === 2 => 'sm:col-span-2',
        $span === 3 => 'sm:col-span-3',
        $span === 4 => 'sm:col-span-4',
        default => '',
    };
    $textColor = $field->getTextColorClass();
@endphp

<div class="{{ $spanClass }}">
    @if($field->getLabel())
        <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
            {{ $field->getLabel() }}
        </div>
    @endif

    <div @class(['text-sm', $field->getWeightClass()])
         @if($field->getTooltip()) title="{{ $field->getTooltip() }}" @endif
    >
        @if($field->isList())
            <ul @class(['space-y-0.5', 'list-disc list-inside' => $field->isBulleted()])>
                @foreach($field->getFormattedStates() as $item)
                    <li class="{{ $textColor }}">{{ $item }}</li>
                @endforeach
            </ul>
        @elseif($field->isBadge())
            <span @class([
                'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium',
                $field->getBadgeColorClass(),
            ])>
                @if($field->getIcon())
                    <x-wire::icon :name="$field->getIcon()" class="w-3.5 h-3.5"/>
                @endif
                {{ $field->getFormattedState() }}
            </span>
        @else
            <span
                    @class([
                        'inline-flex items-center gap-1',
                        $field->isProse() ? 'prose dark:prose-invert max-w-none' : $textColor,
                        'cursor-pointer hover:opacity-70' => $field->isCopyable(),
                    ])
                    @if($field->isCopyable())
                        x-data="{ copied: false }"
                    @click="navigator.clipboard.writeText(@js($field->getFormattedState())); copied = true; setTimeout(() => copied = false, 1500)"
                    role="button"
                    title="{{ __('Copy') }}"
                @endif
            >
                @if($field->getIcon())
                    <x-wire::icon :name="$field->getIcon()" class="w-4 h-4 text-gray-400"/>
                @endif
                {{ $field->getFormattedState() }}
                @if($field->isCopyable())
                    <x-wire::icon name="clipboard" class="w-3.5 h-3.5 text-gray-400" x-show="!copied"/>
                    <x-wire::icon name="check" class="w-3.5 h-3.5 text-emerald-500" x-show="copied" x-cloak/>
                @endif
            </span>
        @endif
    </div>
</div>
