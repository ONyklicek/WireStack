{{-- The record's other pages, as tabs: the way across that neither the menu nor
     the breadcrumb trail can offer.

     A menu knows resources, not records, so it cannot say where the *other*
     pages of this invoice are; breadcrumbs lead up, and this leads sideways.
     Until this existed the only way from an edit screen to the read-only one was
     back through the list.

     What is drawn here is decided entirely by `LinksToRecordPages` — which
     pages are about one record, what they are called, who may open them and
     where they are. This file draws a row of tabs and marks one, and the marking
     is a comparison of **page kinds**, not of URLs: `Zone::currentPage()` reads
     the kind off the route name, so no trailing slash or query string can make a
     tab fail to light up.

     Absent rather than empty when there is nothing to draw. One tab is the
     page's own heading written a second time, so the component returns none —
     the rule the breadcrumb trail already follows for a trail of length one. --}}
@if (($subNavigation ?? []) !== [])
    <nav
        aria-label="{{ __('wire-panels::messages.record_pages') }}"
        data-testid="panels-sub-nav" @wireEl('panels-sub-nav')
        {{-- Scrollable on a narrow screen rather than wrapping. Two ragged rows
             of tabs push the form down by a line the moment a record has one
             page more than the viewport is wide. --}}
        class="flex gap-1 overflow-x-auto border-b border-gray-200 dark:border-gray-700"
    >
        @foreach ($subNavigation as $pageKind => $tab)
            @php($isCurrent = $pageKind === ($currentPage ?? null))

            <a
                href="{{ $tab->getUrl() }}"
                wire:navigate
                @if ($isCurrent) aria-current="page" @endif
                data-testid="panels-sub-nav-item" @wireEl('panels-sub-nav-item')
                data-page="{{ $pageKind }}"
                @if ($isCurrent) data-active="true" @endif
                @class([
                    '-mb-px flex shrink-0 items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors duration-150',
                    'border-primary-500 text-primary-600 dark:text-primary-400' => $isCurrent,
                    'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => ! $isCurrent,
                ])
            >
                @if ($tab->getIcon())
                    {!! icon($tab->getIcon(), 'h-4 w-4 shrink-0') !!}
                @endif

                <span>{{ $tab->getLabel() }}</span>
            </a>
        @endforeach
    </nav>
@endif
