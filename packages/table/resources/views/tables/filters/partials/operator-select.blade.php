{{-- The comparison a filter makes, when it offers a choice (->operators()).
     A native <select>: a handful of words, picked once, on a phone the
     system's own wheel — a combobox here would only be slower to use.
     Bound live, so the value control beside it can appear or go away with
     the operator ("is empty" takes no value). --}}
{{-- Variables: $filter, $raw (the filter's state), $statePath (without
     `.operator`), $controlId, $label --}}
@php
    $current = $filter->operatorFromState($raw ?? null);
@endphp
<label for="{{ $controlId }}" class="sr-only">{{ __('wire-table::messages.operator_label', ['filter' => $label]) }}</label>
<select
    id="{{ $controlId }}"
    wire:model.live="{{ $statePath }}.operator"
    data-testid="{{ $controlId }}"
    @wireEl('filter-operator')
    class="{{ \NyonCode\WireTable\Support\FilterControl::classes() }} pr-8"
>
    @foreach($filter->getOperators() as $operator)
        <option value="{{ $operator->value }}" @selected($operator === $current)>{{ $operator->label() }}</option>
    @endforeach
</select>
