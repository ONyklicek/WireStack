{{-- The filter panel's contents — the same in the toolbar dropdown and above
     the rows (Table::filtersLayout()): the filters in Table::filtersFormColumns()
     columns, then Reset and, for a deferred panel (Table::deferFilters()), Apply.

     Deferred, the controls bind without `.live`, so nothing is sent until a
     request goes — Apply is that request. --}}
{{-- Variables: $table, $filters, $tableFilters --}}
@php $deferred = $table->hasDeferredFilters(); @endphp
<div class="{{ $table->getFiltersGridClass() }}">
    @foreach($filters as $filter)
        @if($filter->canView())
            <div @class(['min-w-0', 'col-span-full' => $filter->spansFullWidth()])>{!! $filter->render($tableFilters[$filter->getName()] ?? null) !!}</div>
        @endif
    @endforeach
</div>

<div @class([
    'mt-4 flex items-center gap-3 border-t border-gray-100 pt-3 dark:border-gray-700',
    'justify-between' => $deferred,
    'justify-center' => ! $deferred,
])>
    <button
        type="button"
        wire:click="resetTableFilters"
        data-testid="table-filters-reset"
        @class([
            'text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200',
            'w-full text-center' => ! $deferred,
        ])
    >
        {{ __('wire-table::messages.filter_reset') }}
    </button>

    @if($deferred)
        <button
            type="button"
            wire:click="applyTableFilters"
            wire:loading.attr="disabled"
            wire:target="applyTableFilters"
            data-testid="table-filters-apply" @wireEl('table-filters-apply')
            @if(! $table->getFiltersLayout()->isAboveContent()) x-on:click="close()" @endif
            class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 disabled:opacity-60"
        >
            {{ __('wire-table::messages.filter_apply') }}
        </button>
    @endif
</div>
