{{-- Interactive action button for infolist entries and schema section headers.
     Dispatches to the host's callInfolistAction() by action name, optionally with
     a repeatable row index.
     Expects: $action (NyonCode\WireCore\Actions\Action). Optional: $rowKey (int). --}}
@php
    $actionIcon = $action->getIcon();
    $actionLabel = $action->getLabel();
    $actionRowKey = $rowKey ?? null;
@endphp
<button
    type="button"
    wire:click="callInfolistAction('{{ $action->getName() }}'@if($actionRowKey !== null), {{ $actionRowKey }}@endif)"
    wire:loading.attr="disabled"
    wire:target="callInfolistAction"
    @if($action->getTooltip()) title="{{ $action->getTooltip() }}" @endif
    @class([
        'inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium transition-colors',
        $action->getButtonColorClasses(),
    ])
>
    @include('wire-core::partials.spinner', ['wireTarget' => 'callInfolistAction', 'class' => 'h-4 w-4'])
    @if($actionIcon)
        <x-wire::icon :name="$actionIcon" class="w-4 h-4"/>
    @endif
    @unless($action->isHideLabel())
        <span>{{ $actionLabel }}</span>
    @endunless
</button>
