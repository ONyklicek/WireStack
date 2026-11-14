{{-- One query-builder condition: field, operator, value, remove. The operator
     list and the value control come from the chosen field's own filter, so a
     date field offers "is before" and a day, a select field "is any of" and its
     options. An operator that takes no value ("is empty") draws none. --}}
{{-- Variables: $filter (the QueryBuilderFilter), $rule, $path, $removeCall, $control --}}
@php
    $constraint = $filter->getConstraint($rule['field'] ?? null) ?? $filter->getFirstConstraint();
    $operator = $constraint?->operatorFromState(['operator' => $rule['operator'] ?? null]);
    $controlId = str_replace('.', '-', $path);
@endphp
@if($constraint !== null)
<div class="flex flex-col gap-2 sm:flex-row sm:items-start" data-testid="query-builder-rule">
    <label for="{{ $controlId }}-field" class="sr-only">{{ __('wire-table::messages.query_builder_field') }}</label>
    <select id="{{ $controlId }}-field" wire:model.live="{{ $path }}.field" class="{{ $control }} pr-8 sm:w-40">
        @foreach($filter->getConstraints() as $key => $candidate)
            <option value="{{ $key }}" @selected($key === $constraint->getName())>{{ $candidate->getLabel() }}</option>
        @endforeach
    </select>

    @if($constraint->getOperators() !== [])
        <label for="{{ $controlId }}-operator" class="sr-only">{{ __('wire-table::messages.operator_label', ['filter' => $constraint->getLabel()]) }}</label>
        <select id="{{ $controlId }}-operator" wire:model.live="{{ $path }}.operator" class="{{ $control }} pr-8 sm:w-44">
            @foreach($constraint->getOperators() as $candidate)
                <option value="{{ $candidate->value }}" @selected($candidate === $operator)>{{ $constraint->operatorLabel($candidate) }}</option>
            @endforeach
        </select>
    @endif

    <div class="min-w-0 flex-1">
        @if($operator === null || $operator->requiresValue())
            @switch($constraint->builderInput())
                @case('select')
                    @include('wire-core::partials.select-control', [
                        'nativeMode' => $constraint->getNativeControlMode(),
                        'mobileBreakpoint' => $constraint->getMobileBreakpoint(),
                        'selectId' => $controlId.'-value',
                        'statePath' => $path.'.value',
                        'options' => $constraint->getDisplayOptions(),
                        'placeholder' => $constraint->getPlaceholder(),
                        'multiple' => $constraint->isMultiple(),
                        'searchable' => $constraint->isSearchable(),
                        'searchPrompt' => __('wire-table::messages.filter_search'),
                        'noResultsMessage' => __('wire-table::messages.filter_no_results'),
                        'sheetOnMobile' => $constraint->usesSheetOnMobile(),
                        'touch' => $constraint->usesTouchOnMobile(),
                        'sheetTitle' => $constraint->getLabel(),
                        'live' => ! $filter->isDeferred(),
                        'ariaLabel' => $constraint->getLabel(),
                    ])
                    @break
                @case('boolean')
                    <select {{ $filter->wireModelDirective() }}="{{ $path }}.value" aria-label="{{ $constraint->getLabel() }}" class="{{ $control }} pr-8">
                        <option value="">—</option>
                        @foreach($constraint->getOptions() as $optionValue => $optionLabel)
                            <option value="{{ $optionValue }}" @selected((string) ($rule['value'] ?? '') === (string) $optionValue)>{{ $optionLabel }}</option>
                        @endforeach
                    </select>
                    @break
                @case('date')
                    <input type="date" {{ $filter->wireModelDirective() }}="{{ $path }}.value" aria-label="{{ $constraint->getLabel() }}" class="{{ $control }}">
                    @break
                @default
                    <input type="text" {{ $filter->wireModelDirective(400) }}="{{ $path }}.value" aria-label="{{ $constraint->getLabel() }}" placeholder="{{ __('wire-table::messages.filter_placeholder') }}" class="{{ $control }}">
            @endswitch
        @endif
    </div>

    <button
        type="button"
        wire:click="{{ $removeCall }}"
        aria-label="{{ __('wire-table::messages.query_builder_remove') }}"
        title="{{ __('wire-table::messages.query_builder_remove') }}"
        data-testid="query-builder-remove"
        class="inline-flex h-9 w-9 shrink-0 items-center justify-center self-end rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-700 sm:self-auto dark:hover:bg-gray-700 dark:hover:text-gray-200"
    >{!! icon('outline:x-mark', 'h-4 w-4') !!}</button>
</div>
@endif
