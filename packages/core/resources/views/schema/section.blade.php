@php
    use NyonCode\WireCore\Foundation\Schema\Section;

    assert($layout instanceof Section);

    $columns = $layout->getColumns();
    $columnsClass = is_array($columns) ? \NyonCode\WireCore\Foundation\Support\ResponsiveGrid::cols($columns) : '';
    $isCollapsible = $layout->isCollapsible();
    $isCollapsed = $layout->isCollapsed();
    $headerActions = $layout->getHeaderActions();
@endphp

<div
        @if($isCollapsible) x-data="{ open: {{ $isCollapsed ? 'false' : 'true' }} }" @endif
class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 {{ $layout->isCompact() ? 'p-3' : 'p-4 sm:p-6' }}"
>
    @if($layout->getLabel() || $layout->getDescription() || $headerActions !== [])
        <div @class([
            'flex items-start justify-between',
            'mb-4' => !$isCollapsible || !$isCollapsed,
            'cursor-pointer' => $isCollapsible,
        ]) @if($isCollapsible) @click="open = !open" @endif>
            <div>
                @if($layout->getLabel())
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        @if($layout->getIcon())
                            <x-wire::icon :name="$layout->getIcon()" class="w-5 h-5 text-gray-400"/>
                        @endif
                        {{ $layout->getLabel() }}
                    </h3>
                @endif
                @if($layout->getDescription())
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $layout->getDescription() }}
                    </p>
                @endif
            </div>

            <div class="ml-4 flex items-center gap-2">
                @foreach($headerActions as $headerAction)
                    <div @click.stop>
                        @include('wire-core::partials.component-action', ['action' => $headerAction])
                    </div>
                @endforeach

                @if($isCollapsible)
                    <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <x-wire::icon name="outline:chevron-down" class="w-5 h-5 transition-transform"
                                      x-bind:class="{ 'rotate-180': open }"/>
                    </button>
                @endif
            </div>
        </div>
    @endif

    <div
            @if($isCollapsible) x-show="open" x-collapse @endif
            @class([
                'grid gap-4',
                $columnsClass,
                'sm:grid-cols-1' => $columns === 1,
                'sm:grid-cols-2' => $columns === 2,
                'sm:grid-cols-2 md:grid-cols-3' => $columns === 3,
                'sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4' => $columns === 4,
            ])
    >
        @foreach($layout->getSchema() as $component)
            @if($component->isVisible())
                {{ $component }}
            @endif
        @endforeach
    </div>
</div>
