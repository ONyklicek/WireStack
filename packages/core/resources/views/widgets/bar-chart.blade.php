@php
    // Safe allow-list for the card corner radius (no arbitrary class injection
    // from the owner-supplied rounded() value).
    $cardRadius = match ($rounded) {
        'none' => 'rounded-none',
        'sm' => 'rounded-sm',
        'md' => 'rounded-md',
        'lg' => 'rounded-lg',
        'xl' => 'rounded-xl',
        '3xl' => 'rounded-3xl',
        'full' => 'rounded-3xl',
        default => 'rounded-2xl',
    };

    // Pick the rendering partial from the (already validated) type + variant.
    $partial = match (true) {
        $variant === 'finance' => 'vertical-finance',
        $type === 'horizontal' => 'horizontal-system',
        default => 'vertical-system',
    };
@endphp

<div class="wire-bar-chart-widget {{ $cardRadius }} border border-gray-100 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    @if($widget->getHeading() || $widget->getDescription() || $showMenu)
        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                @if($widget->getHeading())
                    <h3 class="text-xl font-semibold text-slate-900 dark:text-white">{{ $widget->getHeading() }}</h3>
                @endif
                @if($widget->getDescription())
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $widget->getDescription() }}</p>
                @endif
            </div>

            @if($showMenu)
                <div class="relative" x-data="{ open: false }">
                    <button type="button"
                            x-on:click="open = ! open"
                            x-on:click.outside="open = false"
                            class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700"
                            aria-label="{{ __('Options') }}">
                        <x-wire::icon name="ellipsis-horizontal" class="h-5 w-5" />
                    </button>
                    <div x-show="open" x-cloak x-transition
                         class="absolute right-0 z-10 mt-2 w-40 rounded-xl border border-gray-100 bg-white py-1 text-sm text-slate-600 shadow-lg dark:border-gray-700 dark:bg-gray-800 dark:text-slate-300">
                        {{-- Menu affordance; host dashboards can wire actions here. --}}
                        <button type="button" class="block w-full px-3 py-1.5 text-left hover:bg-slate-50 dark:hover:bg-slate-700">{{ __('Refresh') }}</button>
                    </div>
                </div>
            @endif
        </div>
    @endif

    @include("wire-core::widgets.bar-chart.{$partial}", ['items' => $items])
</div>
