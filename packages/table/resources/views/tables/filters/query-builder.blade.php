{{-- QueryBuilderFilter: conditions combined with "all of" / "any of", and
     groups of them one level deep. Each condition is a field (one of the
     filter's constraints), the operator that field's filter offers, and the
     value control that field takes. The structure — field, operator, the
     combinator, add / remove — is live, because it decides what is drawn; the
     values follow the panel (live, or on Apply when it is deferred). --}}
{{-- Variables: $filter, $value --}}
@php
    use NyonCode\WireTable\Support\QueryBuilderState;
    use NyonCode\WireTable\Support\FilterControl;

    $name = $filter->getName();
    $state = QueryBuilderState::normalize($value);
    $base = 'tableState.filters.'.$name;
    $control = FilterControl::classes();
@endphp

<div class="flex flex-col gap-3" data-testid="query-builder-{{ $name }}" @wireEl('query-builder')>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $filter->getLabel() }}</span>
        @include('wire-table::tables.filters.partials.query-builder-combinator', ['path' => $base.'.combinator', 'combinator' => $state['combinator']])
    </div>

    @forelse($state['rules'] as $index => $rule)
        @if(isset($rule['rules']))
            <div class="rounded-lg border border-dashed border-gray-300 p-3 dark:border-gray-600" data-testid="query-builder-group">
                <div class="mb-2 flex items-center justify-between gap-2">
                    @include('wire-table::tables.filters.partials.query-builder-combinator', ['path' => $base.'.rules.'.$index.'.combinator', 'combinator' => $rule['combinator']])
                </div>
                <div class="flex flex-col gap-2">
                    @foreach($rule['rules'] as $inner => $innerRule)
                        @include('wire-table::tables.filters.partials.query-builder-rule', [
                            'rule' => $innerRule,
                            'path' => $base.'.rules.'.$index.'.rules.'.$inner,
                            'removeCall' => 'queryBuilderRemove('.\Illuminate\Support\Js::from($name).', '.$inner.', '.$index.')',
                        ])
                    @endforeach
                </div>
                <button type="button" wire:click="queryBuilderAddRule(@js($name), {{ $index }})" class="mt-2 text-xs font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">
                    + {{ __('wire-table::messages.query_builder_add_rule') }}
                </button>
            </div>
        @else
            @include('wire-table::tables.filters.partials.query-builder-rule', [
                'rule' => $rule,
                'path' => $base.'.rules.'.$index,
                'removeCall' => 'queryBuilderRemove('.\Illuminate\Support\Js::from($name).', '.$index.')',
            ])
        @endif
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('wire-table::messages.query_builder_empty') }}</p>
    @endforelse

    <div class="flex flex-wrap gap-2">
        <button type="button" wire:click="queryBuilderAddRule(@js($name))" data-testid="query-builder-add-rule" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
            {!! icon('outline:plus', 'h-4 w-4') !!} {{ __('wire-table::messages.query_builder_add_rule') }}
        </button>
        <button type="button" wire:click="queryBuilderAddGroup(@js($name))" data-testid="query-builder-add-group" class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
            {!! icon('outline:plus', 'h-4 w-4') !!} {{ __('wire-table::messages.query_builder_add_group') }}
        </button>
    </div>
</div>
