{{-- The company switcher, registered with PageChrome::TOPBAR by wire-panels.

     Everything drawn is TenantSwitcher's answer: absent outside a tenant and
     for someone with a single company, and otherwise each company linked to
     the same page in it — or to that resource's list on a record's page,
     since a record of one company is not a record of another.

     A plain `wireDropdown` rather than a component tag: this is an engine
     package (AI_CODING_STANDARD § Rendering, rule 5). --}}
@php($switcher = app(\NyonCode\WirePanels\Tenancy\TenantSwitcher::class)->forRequest(request()))

@if ($switcher !== null)
    @include('wire-core::partials.floating-assets')

    <div
        x-data="wireDropdown({ placement: 'bottom-start', offset: 4 })"
        x-on:keydown.escape.window="open && (close(), $refs.trigger.focus())"
        class="relative"
        data-testid="panels-tenant-switcher" @wireEl('panels-tenant-switcher')
    >
        <button
            type="button"
            x-ref="trigger"
            x-on:click="toggle()"
            x-bind:aria-expanded="open ? 'true' : 'false'"
            aria-label="{{ __('wire-panels::messages.switch_tenant') }}: {{ $switcher['current'] }}"
            data-testid="panels-tenant-switcher-trigger"
            class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800"
        >
            {!! icon('outline:building-office-2', 'h-4 w-4 text-gray-400') !!}
            <span class="max-w-[12rem] truncate">{{ $switcher['current'] }}</span>
            {!! icon('outline:chevron-up-down', 'h-4 w-4 text-gray-500 dark:text-gray-400') !!}
        </button>

        <template x-teleport="body">
            <div
                x-ref="panel"
                x-show="open"
                x-cloak
                x-on:click.outside="$clickedInside($event) || close()"
                class="absolute top-0 left-0 z-50 w-64 rounded-lg bg-white p-1 shadow-lg ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10"
                style="display: none;"
            >
                <ul class="space-y-0.5">
                    @foreach ($switcher['tenants'] as $tenant)
                        <li>
                            <a
                                href="{{ $tenant['url'] }}"
                                @if ($tenant['current']) aria-current="true" @endif
                                data-testid="panels-tenant-switcher-item" @wireEl('panels-tenant-switcher-item')
                                @class([
                                    'flex items-center justify-between gap-2 rounded-md px-3 py-2 text-sm',
                                    'bg-primary-50 font-medium text-primary-700 dark:bg-primary-950/60 dark:text-primary-200' => $tenant['current'],
                                    'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700' => ! $tenant['current'],
                                ])
                            >
                                <span class="truncate">{{ $tenant['label'] }}</span>
                                @if ($tenant['current'])
                                    {!! icon('outline:check', 'h-4 w-4 shrink-0') !!}
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </template>
    </div>
@endif
