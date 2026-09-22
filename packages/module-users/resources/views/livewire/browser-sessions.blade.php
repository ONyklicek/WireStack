{{-- Browser sessions: where this account is signed in, and a way out for all
     of them but this one. The list needs the database session driver; the
     button does not, so the card says which half this installation has. --}}
<div data-testid="profile-browser-sessions" @wireEl('profile-browser-sessions')>
    <x-wire::section
        :heading="__('wire-module-users::messages.browser_sessions')"
        :description="__('wire-module-users::messages.browser_sessions_hint')"
    >
        <div class="space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                {{ __('wire-module-users::messages.browser_sessions_explain') }}
            </p>

            @if (! $listable)
                <p class="text-sm text-gray-500 dark:text-gray-400" data-testid="browser-sessions-unlisted">
                    {{ __('wire-module-users::messages.browser_sessions_unlisted') }}
                </p>
            @elseif ($sessions->isNotEmpty())
                <ul class="divide-y divide-gray-200 dark:divide-gray-700" data-testid="browser-sessions-list">
                    @foreach ($sessions as $session)
                        <li class="flex items-center gap-3 py-2" wire:key="browser-session-{{ $session->id }}">
                            <x-wire::icon
                                :name="$session->agent->desktop ? 'outline:computer-desktop' : 'outline:device-phone-mobile'"
                                {{-- The component's own prop: a size passed as a
                                     class merges with its `w-4 h-4` default and
                                     whichever wins is the stylesheet's order. --}}
                                size="size-8"
                                class="shrink-0 text-gray-500 dark:text-gray-400"
                            />

                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $session->agent->platform ?? __('wire-module-users::messages.browser_sessions_unknown') }}
                                    –
                                    {{ $session->agent->browser ?? __('wire-module-users::messages.browser_sessions_unknown') }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $session->ipAddress }},
                                    @if ($session->current)
                                        <span class="font-semibold text-green-600 dark:text-green-400" data-testid="browser-session-current">
                                            {{ __('wire-module-users::messages.browser_sessions_this_device') }}
                                        </span>
                                    @else
                                        {{ __('wire-module-users::messages.browser_sessions_last_active', ['time' => $session->lastActive->diffForHumans()]) }}
                                    @endif
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            <x-wire::button wire:click="confirm" data-testid="browser-sessions-open">
                {{ __('wire-module-users::messages.browser_sessions_logout') }}
            </x-wire::button>
        </div>
    </x-wire::section>

    <x-wire::modal
        wire:model="confirming"
        :heading="__('wire-module-users::messages.browser_sessions_logout')"
        :description="__('wire-module-users::messages.browser_sessions_confirm')"
        width="md"
        close-action="cancel"
    >
        {{ $this->form }}

        <x-slot:footer>
            <div class="flex items-center justify-end gap-2">
                <x-wire::button color="gray" outlined wire:click="cancel">
                    {{ __('wire-module-users::messages.cancel') }}
                </x-wire::button>

                <x-wire::button wire:click="logoutOtherSessions" data-testid="browser-sessions-confirm">
                    {{ __('wire-module-users::messages.browser_sessions_logout') }}
                </x-wire::button>
            </div>
        </x-slot:footer>
    </x-wire::modal>
</div>
