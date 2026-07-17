@php
    use Illuminate\Database\Eloquent\Model;
    use NyonCode\WireCore\Actions\ActionGroup;
    use NyonCode\WireCore\Foundation\Support\MobileSheet;

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
            data-testid="action-group-trigger"
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

        {{-- Teleported to <body>. From sm up it floats next to the trigger
             (Floating UI); on a phone it becomes a bottom sheet (max-sm: classes,
             Floating UI skipped by wireDropdown) with a dimming backdrop. --}}
        @php $sheetOnMobile = $group->usesSheetOnMobile(); $sheetBp = $group->getMobileBreakpoint(); @endphp
        <template x-teleport="body">
            <div>
                @if($sheetOnMobile)
                    {{-- Backdrop: mobile-only (sm:hidden), taps to close. --}}
                    <div
                        x-show="open"
                        x-cloak
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        @click="close()"
                        class="fixed inset-0 z-40 bg-gray-500/60 dark:bg-gray-900/70 {{ MobileSheet::backdropHide($sheetBp) }}"
                    ></div>
                @endif

                {{-- Base classes are the original desktop floating panel (kept
                     byte-identical); max-sm: overrides turn it into a bottom sheet
                     on a phone. Scale-pop transition on desktop, slide-up on
                     mobile — same x-transition, breakpoint-scoped classes. --}}
                <div
                    x-ref="panel"
                    x-show="open"
                    x-cloak
                    @click.outside="$clickedInside($event) || close()"
                    @if($sheetOnMobile) x-focus-trap="open" tabindex="-1" data-sheet-bp="{{ MobileSheet::px($sheetBp) }}" @endif
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95 {{ $sheetOnMobile ? MobileSheet::motion($sheetBp) : '' }}"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                    x-transition:leave-end="opacity-0 scale-95 {{ $sheetOnMobile ? MobileSheet::motion($sheetBp) : '' }}"
                    @class([
                        'absolute top-0 left-0 z-50 rounded-lg bg-white dark:bg-gray-800 shadow-lg ring-1 ring-black/5 dark:ring-white/10 focus:outline-none',
                        $group->getDropdownOriginClass(),
                        $group->getDropdownWidth(),
                        MobileSheet::panel($sheetBp) => $sheetOnMobile,
                    ])
                    style="display: none;"
                    role="menu"
                >
                    @if($sheetOnMobile)
                        @include('wire-core::partials.sheet-grabber', ['dismiss' => 'close()', 'breakpoint' => $sheetBp])
                    @endif
                    <div class="py-1">
                        {{ $group->getDropdownItemsHtml($record) }}
                    </div>
                </div>
            </div>
        </template>
    </div>
@endif
