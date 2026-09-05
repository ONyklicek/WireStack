@extends('layouts.preview', [
    'title' => $title,
    'subtitle' => $subtitle,
])

@section('content')
    {{-- ADR 0027 open question 2, made lookable-at.

         One catalogue, one set of navigation entries, resolved twice. Nothing
         below declares membership: `business` routes invoices, `admin` routes
         invoices and tasks, documents declares no pages at all and the overview
         is a dashboard — so the difference in this page is entirely the
         difference between the two `Route::wireResources(only: …)` calls.

         The question this page settled: what should a menu do with an entry the
         zone cannot reach? Three of four went dead in `business`, which is the
         shape commit 51d7d5a called a defect — so `linkedOnly` exists, shown
         here beside the unfiltered menu.

         Opt-in rather than the rule, because the two reasons an entry has no URL
         are different and Workspace cannot tell them apart: `tasks` is routed,
         just in another zone; `documents` is routed nowhere. And a shell with a
         URL scheme of its own — the workspace preview — has every entry unlinked
         here and wants all of them. Filament reaches the same menu by
         registering resources per panel; this reaches it while registering
         once. --}}
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" data-testid="zone-menus">
        @foreach ($menus as $zone => $groups)
            <section
                data-testid="zone-menu"
                data-zone="{{ $zone }}"
                data-mode="{{ str_contains($zone, 'linkedOnly') ? 'linked-only' : 'as-registered' }}"
                class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm"
            >
                <p class="mb-1 font-mono text-xs uppercase tracking-widest text-slate-400">zone</p>
                <h2 class="mb-4 text-sm font-semibold text-slate-900">{{ $zone }}</h2>

                @if (! $groups)
                    <p class="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-400" data-testid="zone-empty">
                        nothing this zone can reach
                    </p>
                @endif

                @foreach ($groups as $group)
                    <div class="mb-4 last:mb-0" data-zone-group="{{ $group->getKey() }}">
                        @if ($group->hasVisibleLabel())
                            <p class="flex items-center gap-2 px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                                @if ($group->getIcon())
                                    {!! icon($group->getIcon(), 'h-4 w-4') !!}
                                @endif
                                {{ $group->getLabel() }}
                            </p>
                        @endif

                        <ul class="space-y-1">
                            @foreach ($group->getItems() as $key => $item)
                                @php($url = $item->getUrl())
                                <li>
                                    {{-- The entry carries its own URL now, filled by
                                         Workspace from ResolvesPageUrls for the zone
                                         it was asked about. No map anywhere. --}}
                                    <a
                                        @if ($url) href="{{ $url }}" wire:navigate @endif
                                        data-testid="zone-nav-item"
                                        data-key="{{ $key }}"
                                        data-linked="{{ $url ? 'true' : 'false' }}"
                                        @class([
                                            'flex items-center gap-3 rounded-xl px-3 py-2 text-sm',
                                            'text-slate-700 hover:bg-slate-50' => (bool) $url,
                                            'text-slate-400' => ! $url,
                                        ])
                                    >
                                        @if ($item->getIcon())
                                            {!! icon($item->getIcon(), 'h-4 w-4 shrink-0') !!}
                                        @endif
                                        <span class="flex-1">{{ $item->getLabel() ?? $key }}</span>
                                        @if ($item->getBadge())
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                                                {{ $item->getBadge() }}
                                            </span>
                                        @endif
                                        <span
                                            data-testid="zone-nav-url"
                                            class="font-mono text-[10px] text-slate-400"
                                        >{{ $url ? parse_url($url, PHP_URL_PATH) : '— not in this zone' }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </section>
        @endforeach
    </div>
@endsection
