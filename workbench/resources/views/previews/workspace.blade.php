@extends('layouts.preview', [
    'title' => $title,
    'subtitle' => $subtitle,
])

@section('content')
    {{-- The first consumer of Workspace::navigation() in this repository.

         Deliberately the application's own markup: the registry holds no URL
         shell and NavigationItem holds no route (ADR 0020 Q4), so what a menu
         entry links to is decided here, by the same array that registers the
         routes. What the framework hands over is the *arrangement* — which
         group, in what order, under what name, with what badge — and this page
         exists to prove that arrangement survives all the way to a browser. --}}
    <div class="grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
        <aside
            data-testid="workspace-nav"
            class="h-max rounded-3xl border border-slate-200 bg-white p-3 shadow-sm"
        >
            @foreach ($groups as $group)
                <div class="mb-4 last:mb-0" data-testid="workspace-group" data-group="{{ $group->getKey() }}">
                    {{-- The heading is the group's, not its key: the slug stays
                         stable while the text is free to be translated. --}}
                    @if ($group->hasVisibleLabel())
                        <p
                            data-testid="workspace-group-heading"
                            class="flex items-center gap-2 px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400"
                        >
                            @if ($group->getIcon())
                                {!! icon($group->getIcon(), 'h-4 w-4') !!}
                            @endif
                            {{ $group->getLabel() }}
                        </p>
                    @endif

                    <ul class="space-y-1">
                        @foreach ($group->getItems() as $key => $item)
                            @php($url = $urls[$key] ?? null)
                            <li>
                                {{-- A registered resource with no page of its own still
                                     belongs in the menu; it simply is not a link. --}}
                                <a
                                    @if ($url) href="{{ $url }}" wire:navigate @endif
                                    data-testid="workspace-nav-item"
                                    data-resource="{{ $key }}"
                                    @if ($key === $active) data-active="true" aria-current="page" @endif
                                    @class([
                                        'flex items-center gap-3 rounded-xl px-3 py-2 text-sm transition',
                                        'bg-sky-50 font-medium text-sky-800' => $key === $active,
                                        'text-slate-600 hover:bg-slate-50' => $key !== $active,
                                        'cursor-default opacity-60' => ! $url,
                                    ])
                                >
                                    @if ($item->getIcon())
                                        {!! icon($item->getIcon(), 'h-5 w-5 shrink-0 text-slate-400') !!}
                                    @endif

                                    <span class="flex-1 truncate" data-testid="workspace-nav-label">{{ $item->getLabel() }}</span>

                                    @if ($item->getBadge())
                                        <x-wire::badge
                                            :color="$item->getBadgeColor() ?? 'gray'"
                                            data-testid="workspace-nav-badge"
                                        >{{ $item->getBadge() }}</x-wire::badge>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </aside>

        <main class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm" data-testid="workspace-content">
            @livewire($component, [], key($component))
        </main>
    </div>
@endsection
