{{-- Custom modal footer actions (Action::modalFooterActions()).
     Expects: $footerActions (array of config), $position ('before'|'after'). --}}
@php
    use NyonCode\WireCore\Foundation\Concerns\HasColor;

    $footerActions = $footerActions ?? [];
    $position = $position ?? 'before';
@endphp
@foreach($footerActions as $footerAction)
    @continue(($footerAction['position'] ?? 'before') !== $position)
    @php
        $color = $footerAction['color'] ?? 'gray';
        $outlined = (bool) ($footerAction['outlined'] ?? false);
        $buttonClasses = $outlined
            ? 'rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600'
            : 'rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm '.HasColor::getModalSubmitButtonClasses($color);
    @endphp
    <button
        type="button"
        wire:click="callModalFooterAction('{{ $footerAction['name'] }}')"
        @if(! empty($footerAction['confirmMessage']))
            wire:confirm="{{ $footerAction['confirmMessage'] }}"
        @endif
        wire:loading.attr="disabled"
        wire:target="callModalFooterAction"
        @class(['inline-flex items-center gap-2', $buttonClasses])
    >
        @include('wire-core::partials.spinner', ['wireTarget' => 'callModalFooterAction', 'class' => 'h-4 w-4'])
        @if(! empty($footerAction['icon']))
            <x-wire::icon :name="$footerAction['icon']" class="h-4 w-4" />
        @endif
        <span>{{ $footerAction['label'] }}</span>
    </button>
@endforeach
