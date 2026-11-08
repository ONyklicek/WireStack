{{-- TextInputColumn readonly cell --}}
{{-- Variables: $column, $record, $state --}}
@php
    $value = (string) ($state ?? '');
    $placeholder = $column->getPlaceholder() ?? '-';
    $prefix = $column->getInputPrefix();
    $suffix = $column->getInputSuffix();

    // One span either way, decided here rather than with block `@if`s, which
    // Livewire brackets in `[if BLOCK]` comments inside its own render pass and
    // in nothing outside it — see TablePartials::region().
    [$spanClass, $text] = $value !== ''
        ? ['text-sm text-gray-900 dark:text-gray-100', ($prefix ?? '').$value.($suffix ?? '')]
        : ['text-sm text-gray-500 dark:text-gray-400 italic', $placeholder];
@endphp

<span class="{{ $spanClass }}">{{ $text }}</span>
