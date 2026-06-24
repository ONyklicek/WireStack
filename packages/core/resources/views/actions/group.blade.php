@php
    use Illuminate\Database\Eloquent\Model;
    use NyonCode\WireCore\Actions\ActionGroup;

    assert($group instanceof ActionGroup);
    /** @var Model|null $record */

    // Resolve visible actions once (auto-dividers included) and count only the
    // executable ones so a lone action collapses to an inline button.
    $visibleActions = $record ? $group->getVisibleActionsWithDividers($record) : [];
    $actionCount = $group->countExecutableActions($visibleActions);
@endphp

@if($actionCount === 1)
    {{ $group->getSingleActionHtml($record) }}
@elseif($actionCount > 1)
    @include('wire-core::partials.floating-assets')

    <div
        class="relative inline-block text-left"
        x-data="wireDropdown(@js($group->getDropdownConfig()))"
        @keydown.escape.window="close()"
    >
        <button
            type="button"
            x-ref="trigger"
            @click="toggle()"
            :aria-expanded="open"
            aria-haspopup="menu"
            class="relative {{ $group->getTriggerClasses() }}"
            @if($group->getTooltip()) title="{{ $group->getTooltip() }}" @endif
        >
            {{ $group->getTriggerIconHtml() }}
            @if($group->getLabel())
                <span>{{ $group->getLabel() }}</span>
                {{ $group->getChevronSvg() }}
            @endif
            {{ $group->getBadgeHtml() }}
        </button>

        {{-- Teleported to <body> + positioned by Floating UI so the menu floats
             above the table instead of being clipped by its overflow. --}}
        <template x-teleport="body">
            <div
                x-ref="panel"
                x-show="open"
                x-cloak
                @click.outside="close()"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="absolute top-0 left-0 z-50 {{ $group->getDropdownOriginClass() }} {{ $group->getDropdownWidth() }} rounded-lg bg-white dark:bg-gray-800 shadow-lg ring-1 ring-black/5 dark:ring-white/10 focus:outline-none"
                style="display: none;"
                role="menu"
            >
                <div class="py-1">
                    {{ $group->getDropdownItemsHtml($record) }}
                </div>
            </div>
        </template>
    </div>
@endif
