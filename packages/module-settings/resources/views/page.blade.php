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

    @if (count($groups) > 1)
        {{-- One group is not a choice, so it is not drawn as one. --}}
        <nav class="flex flex-wrap gap-2" data-testid="settings-groups" @wireEl('settings-groups') aria-label="{{ __('wire-module-settings::messages.settings') }}">
            @foreach ($groups as $key => $class)
                @php($icon = \NyonCode\WireModuleSettings\Support\SettingsGroups::icon($class))
                <a
                    href="{{ \NyonCode\WireModuleSettings\Resources\SettingsResource::urlForGroup($key) ?? '#' }}"
                    wire:navigate
                    data-testid="settings-group-link" @wireEl('settings-group-link')
                    data-group="{{ $key }}"
                    @if ($key === $current) aria-current="page" @endif
                    @class([
                        'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm transition',
                        'bg-primary-50 font-medium text-primary-800 dark:bg-primary-950 dark:text-primary-200' => $key === $current,
                        'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800' => $key !== $current,
                    ])
                >
                    @if ($icon)
                        <x-wire::icon :name="$icon" />
                    @endif

                    {{ $class::label() }}
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

            <x-wire::button type="submit" data-testid="settings-save">
                {{ __('wire-module-settings::messages.save') }}
            </x-wire::button>
        </form>
    @endif
</div>
