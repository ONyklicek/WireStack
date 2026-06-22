@php
    /** @var \NyonCode\WireCore\Actions\ActionGroup $group */
    /** @var \Illuminate\Database\Eloquent\Model|null $record */

    // Resolve visible actions once (auto-dividers included) and count only the
    // executable ones so a lone action collapses to an inline button.
    $visibleActions = $record ? $group->getVisibleActionsWithDividers($record) : [];
    $actionCount = $group->countExecutableActions($visibleActions);
@endphp

@if($actionCount === 1)
    {!! $group->getSingleActionHtml($record) !!}
@elseif($actionCount > 1)
    <div class="relative inline-block text-left" x-data="{ open: false }">
        <button
            type="button"
            @click="open = !open"
            @click.outside="open = false"
            class="relative {{ $group->getTriggerClasses() }}"
            @if($group->getTooltip()) title="{{ $group->getTooltip() }}" @endif
        >
            {!! $group->getTriggerIconHtml() !!}
            @if($group->getLabel())
                <span>{{ $group->getLabel() }}</span>
                {!! $group->getChevronSvg() !!}
            @endif
            {!! $group->getBadgeHtml() !!}
        </button>

        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="transform opacity-0 scale-95"
            x-transition:enter-end="transform opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="transform opacity-100 scale-100"
            x-transition:leave-end="transform opacity-0 scale-95"
            class="absolute {{ $group->getDropdownPositionClasses() }} z-50 mt-2 {{ $group->getDropdownWidth() }} rounded-lg bg-white dark:bg-gray-800 shadow-lg ring-1 ring-black/5 dark:ring-white/10 focus:outline-none"
            style="display: none;"
            role="menu"
        >
            <div class="py-1">
                {!! $group->getDropdownItemsHtml($record) !!}
            </div>
        </div>
    </div>
@endif
