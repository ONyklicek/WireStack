{{-- Which team you are looking at, in the chrome where it is always visible.

     In the top bar rather than on a settings page, because it scopes everything
     on every page: a person who cannot see which team they are in is a person
     who will eventually edit the wrong one. --}}
<div data-testid="team-switcher" @wireEl('team-switcher')>
    <x-wire::dropdown position="bottom-end" width="w-56" sheet-on-mobile>
        <x-slot:trigger>
            <button
                type="button"
                data-testid="team-switcher-trigger" @wireEl('team-switcher-trigger')
                class="inline-flex items-center gap-2 rounded-full border border-gray-200 px-3 py-2.5 text-sm text-gray-600 transition sm:py-1.5 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                {!! icon('outline:user-group', 'h-4 w-4 shrink-0') !!}
                <span class="hidden max-w-32 truncate sm:block">{{ $currentLabel ?? __('wire-module-users::messages.switch_team') }}</span>
                {!! icon('outline:chevron-down', 'h-4 w-4 text-gray-400') !!}
            </button>
        </x-slot:trigger>

        <div class="px-4 py-2 text-xs font-medium tracking-wide text-gray-400 uppercase">
            {{ __('wire-module-users::messages.switch_team') }}
        </div>

        @foreach ($teams as $teamId => $teamLabel)
            <button
                type="button"
                wire:click="switchTo('{{ $teamId }}')"
                data-testid="team-switcher-option" @wireEl('team-switcher-option')
                data-team="{{ $teamId }}"
                data-current="{{ $teamId == $current ? 'true' : 'false' }}"
                @class([
                    'flex w-full items-center justify-between gap-2 px-4 py-2 text-start text-sm transition',
                    'hover:bg-gray-50 dark:hover:bg-gray-700',
                    'font-medium text-gray-900 dark:text-gray-100' => $teamId == $current,
                    'text-gray-600 dark:text-gray-300' => $teamId != $current,
                ])
            >
                <span class="truncate">{{ $teamLabel }}</span>

                @if ($teamId == $current)
                    {!! icon('outline:check', 'text-primary-600 h-4 w-4 shrink-0') !!}
                @endif
            </button>
        @endforeach
    </x-wire::dropdown>
</div>
