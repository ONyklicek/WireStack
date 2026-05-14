@php

    use Illuminate\Database\Eloquent\Model;
    use NyonCode\WireCore\Actions\Action;use NyonCode\WireCore\Actions\ActionGroup;

    assert($group instanceof ActionGroup);
    assert($record instanceof Model);

    $visibleActions = $group->getVisibleActions($record);
@endphp

@if(count($visibleActions) === 1)
    @php $singleAction = reset($visibleActions); @endphp
    @if($singleAction instanceof Action)
        {!! $singleAction->render($record) !!}
    @endif
@elseif(count($visibleActions) > 1)
    <div class="relative inline-block text-left" x-data="{ open: false }">
        <button
                type="button"
                @click="open = !open"
                @click.outside="open = false"
                class="{{ $group->getTriggerClasses() }}"
                @if($group->getTooltip()) title="{{ $group->getTooltip() }}" @endif
        >
            {!! $group->getTriggerIconHtml() !!}
            @if($group->getLabel())
                <span>{{ $group->getLabel() }}</span>
                {!! $group->getChevronSvg() !!}
            @endif
        </button>

        <div
                x-show="open"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="transform opacity-0 scale-95"
                x-transition:enter-end="transform opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="transform opacity-100 scale-100"
                x-transition:leave-end="transform opacity-0 scale-95"
                class="absolute {{ $group->getDropdownPositionClasses() }} z-50 mt-1 w-48 rounded-md bg-white dark:bg-gray-800 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
                style="display: none;"
        >
            <div class="py-1" role="menu">
                @foreach($visibleActions as $dropAction)
                    @if($dropAction instanceof Action)
                        @include('wire-table::tables.actions.dropdown-item', ['action' => $dropAction, 'record' => $record])
                    @endif
                @endforeach
            </div>
        </div>
    </div>
@endif
