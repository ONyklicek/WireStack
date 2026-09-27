{{-- A cluster's screens, on every one of them: the way across that the menu
     no longer offers, because the menu shows the cluster as one entry.

     Everything drawn is decided by `ClusterNavigation` — which members, what
     they are called, where they go — and which one is current is a comparison
     of registered keys made in `ClusterSubNavigation`, so a member resource
     stays marked on its edit page, not only on its list.

     One element, two shapes. Below `lg` it is a row of tabs whatever the
     cluster asked for: a column on a phone is a column nobody scrolls to. From
     `lg` up a cluster that asked for `start` or `end` gets a column beside the
     content — the page's root carries `data-cluster-frame` and the grid is the
     plain CSS below, so the layout does not depend on a utility existing in the
     application's Tailwind build. --}}
@php
    use NyonCode\WireCore\Foundation\View\Badge;
    use NyonCode\WireCore\Foundation\View\ComponentRenderer;
@endphp

@if (($clusterNavigation ?? null) !== null && ! $clusterNavigation->isEmpty())
    @php($column = $clusterNavigation->frame() !== null)

    @once
        <style>
            @media (min-width: 64rem) {
                [data-cluster-frame] { display: grid; column-gap: 2rem; align-items: start; }
                [data-cluster-frame="start"] { grid-template-columns: 13rem minmax(0, 1fr); }
                [data-cluster-frame="end"] { grid-template-columns: minmax(0, 1fr) 13rem; }
                [data-cluster-frame="start"] > * { grid-column: 2; }
                [data-cluster-frame="end"] > * { grid-column: 1; }
                [data-cluster-frame="start"] > [data-cluster-nav] { grid-column: 1; grid-row: 1 / span 100; }
                [data-cluster-frame="end"] > [data-cluster-nav] { grid-column: 2; grid-row: 1 / span 100; }
                {{-- The column is the first child, so a sibling spacing rule would
                     push the heading below the top of the column it sits beside. --}}
                [data-cluster-frame] > [data-cluster-nav] + * { margin-top: 0 !important; }
            }
        </style>
    @endonce

    <nav
        aria-label="{{ $clusterNavigation->label }}"
        data-cluster-nav
        data-position="{{ $clusterNavigation->position->value }}"
        data-testid="panels-cluster-nav" @wireEl('panels-cluster-nav')
        @class([
            'flex gap-1 overflow-x-auto border-b border-gray-200 dark:border-gray-700',
            'lg:sticky lg:top-4 lg:flex-col lg:gap-0.5 lg:overflow-visible lg:border-b-0' => $column,
        ])
    >
        @foreach ($clusterNavigation->items as $key => $item)
            @php($isCurrent = $clusterNavigation->isCurrent($key))

            <a
                @if ($item->getUrl()) href="{{ $item->getUrl() }}" wire:navigate @endif
                @if ($isCurrent) aria-current="page" data-active="true" @endif
                data-testid="panels-cluster-nav-item" @wireEl('panels-cluster-nav-item')
                data-member="{{ $key }}"
                @class([
                    '-mb-px flex shrink-0 items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors duration-150',
                    'lg:mb-0 lg:rounded-lg lg:border-b-0 lg:px-3' => $column,
                    'border-primary-500 text-primary-600 dark:text-primary-400' => $isCurrent,
                    'lg:bg-primary-50 lg:text-primary-700 dark:lg:bg-primary-950/60 dark:lg:text-primary-200' => $isCurrent && $column,
                    'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => ! $isCurrent,
                    'lg:hover:bg-gray-100 dark:lg:hover:bg-gray-800' => ! $isCurrent && $column,
                ])
            >
                @if ($item->getIcon())
                    {!! icon($item->getIcon(), 'h-4 w-4 shrink-0') !!}
                @endif

                <span class="truncate">{{ $item->getLabel() }}</span>

                @if ($item->getBadge() !== null)
                    {{-- The canonical badge, by object rather than by tag: this is
                         an engine view (rule 5). --}}
                    {!! ComponentRenderer::render(new Badge($item->getBadgeColor() ?? 'gray'), $item->getBadge(), ['class' => 'ms-auto']) !!}
                @endif
            </a>
        @endforeach
    </nav>
@endif
