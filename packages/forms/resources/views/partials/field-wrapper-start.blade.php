@php
    $statePath = $field->getStatePath();
    $hasError = $errors->has($statePath);
    $columnSpan = $field->getColumnSpan();
@endphp

<div
    @class([
        'wire-field',
        'sm:col-span-1' => $columnSpan === 1,
        'sm:col-span-2' => $columnSpan === 2 || $columnSpan === 'full',
    ])
>
    @if($field->getLabel() && !($hideLabel ?? false))
        <label for="{{ $field->getId() }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $field->getLabel() }}
            @if($field->isRequired())
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    @if(method_exists($field, 'getHint') && $field->getHint())
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">
            {{ $field->getHint() }}
        </p>
    @endif
