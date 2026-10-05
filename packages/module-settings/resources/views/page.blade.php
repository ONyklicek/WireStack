{{-- The settings screen. One group's form, with the other groups beside it.

     The switcher is links rather than tabs: a group is a URL, so a person can
     bookmark "mail settings" and land on it — which a tab state in a snapshot
     cannot do. It carries only the groups this user may open, so a link here
     never leads to a refusal the page would then have to explain. --}}
<div class="wire-settings-page space-y-4 sm:space-y-6">
    @include('wire-panels::pages.partials.header')

    @if ($description)
        <p class="max-w-2xl text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
    @endif

    @if (count($links) > 1)
        {{-- One group is not a choice, so it is not drawn as one. Links either
             way — a group is a URL — drawn as buttons or as the tab bar
             <x-wire::tabs> draws, for a section that already looks like one. --}}
        <nav
            @class([
                'flex flex-wrap',
                'gap-2' => $switcher === 'links',
                'gap-1 border-b border-gray-200 dark:border-gray-700' => $switcher === 'tabs',
            ])
            @if ($switcher === 'tabs') role="tablist" @endif
            data-testid="settings-groups" @wireEl('settings-groups')
            data-switcher="{{ $switcher }}"
            aria-label="{{ __('wire-module-settings::messages.settings') }}"
        >
            @foreach ($links as $key => $link)
                <a
                    href="{{ $link['url'] ?? '#' }}"
                    wire:navigate
                    data-testid="settings-group-link" @wireEl('settings-group-link')
                    data-group="{{ $key }}"
                    @if ($switcher === 'tabs') role="tab" aria-selected="{{ $link['current'] ? 'true' : 'false' }}" @endif
                    @if ($link['current']) aria-current="page" @endif
                    @class([
                        'inline-flex items-center gap-1.5 text-sm transition',
                        'rounded-lg px-3 py-1.5' => $switcher === 'links',
                        'bg-primary-50 font-medium text-primary-800 dark:bg-primary-950 dark:text-primary-200' => $switcher === 'links' && $link['current'],
                        'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800' => $switcher === 'links' && ! $link['current'],
                        '-mb-px border-b-2 px-4 py-2 font-medium focus:outline-none' => $switcher === 'tabs',
                        'border-primary-500 text-primary-600 dark:text-primary-400' => $switcher === 'tabs' && $link['current'],
                        'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $switcher === 'tabs' && ! $link['current'],
                    ])
                >
                    @if ($link['icon'])
                        <x-wire::icon :name="$link['icon']" />
                    @endif

                    {{ $link['label'] }}
                </a>
            @endforeach
        </nav>
    @endif

    @if ($groups === [])
        {{-- No group declared: the module has storage and a screen, and the
             application has not said what is configurable yet. --}}
        {{-- Wrapped rather than attributed: the shared empty state renders a
             partial with named variables and forwards no arbitrary attributes,
             so the test hook goes on a container of our own. --}}
        <div data-testid="settings-empty" @wireEl('settings-empty')>
            <x-wire::empty-state
                :heading="__('wire-module-settings::messages.empty_heading')"
                :description="__('wire-module-settings::messages.empty_description')"
            />
        </div>
    @elseif ($screenComponent)
        {{-- The group's own screen: the module keeps the switcher, the heading
             and who may open it; what is inside is the component's. --}}
        <div data-testid="settings-component" @wireEl('settings-component')>
            @livewire($screenComponent, ['group' => $current], key('settings-'.$current))
        </div>
    @else
        <form wire:submit="save" class="space-y-4 sm:space-y-6" data-testid="settings-form" @wireEl('settings-form')>
            {{-- A card, unless the group brought its own layout. A flat schema
                 rendered bare is the one screen in a panel where inputs sit
                 directly on the page background; a card around a group that
                 already declared a Section is a border inside a border. --}}
            @if ($surface)
                <x-wire::section data-testid="settings-surface">{{ $this->form }}</x-wire::section>
            @else
                {{ $this->form }}
            @endif

            {{-- A reading of the fields — a preview, a sum — from the state the
                 form holds now, so a live field redraws it as it changes. --}}
            @if ($extension)
                <div data-testid="settings-extension" @wireEl('settings-extension')>{{ $extension }}</div>
            @endif

            <x-wire::button type="submit" data-testid="settings-save">
                {{ __('wire-module-settings::messages.save') }}
            </x-wire::button>
        </form>
    @endif
</div>
