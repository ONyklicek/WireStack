{{-- The tabs above a list: each one the same records, narrowed.

     A button per tab setting the page's `activeTab`, which the URL carries as
     `?tab=`. Everything drawn here — label, icon, count, which one is active —
     is resolved by InteractsWithListTabs; this file draws a row and marks one.
     Scrollable on a narrow screen rather than wrapping, like the record tabs.

     Variables: $listTabs — array of [name, label, icon, count, badgeClasses, active]. --}}
@if(($listTabs ?? []) !== [])
    <nav
        aria-label="{{ __('wire-panels::messages.list_tabs') }}"
        data-testid="panels-list-tabs" @wireEl('panels-list-tabs')
        class="flex gap-1 overflow-x-auto border-b border-gray-200 dark:border-gray-700"
    >
        @foreach($listTabs as $tab)
            <button
                type="button"
                wire:click="$set('activeTab', '{{ $loop->first ? '' : $tab['name'] }}')"
                data-testid="list-tab-{{ $tab['name'] }}"
                @if($tab['active']) aria-current="true" @endif
                @class([
                    '-mb-px inline-flex shrink-0 items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap',
                    'border-primary-600 text-primary-700 dark:border-primary-400 dark:text-primary-300' => $tab['active'],
                    'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => ! $tab['active'],
                ])
            >
                @if($tab['icon'])
                    {!! icon($tab['icon'], 'h-4 w-4') !!}
                @endif
                <span>{{ $tab['label'] }}</span>
                @if($tab['count'] !== null)
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $tab['badgeClasses'] }}">{{ $tab['count'] }}</span>
                @endif
            </button>
        @endforeach
    </nav>
@endif
