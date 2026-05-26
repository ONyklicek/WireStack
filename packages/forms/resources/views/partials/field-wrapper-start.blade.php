@php
    $statePath = $field->getStatePath();
    $hasError = $errors->has($statePath);
    $columnSpan = $field->getColumnSpan();
@endphp

<div
    wire:loading.class="opacity-50 pointer-events-none"
    @class([
        'wire-field relative transition-opacity duration-150',
        'sm:col-span-1' => $columnSpan === 1,
        'sm:col-span-2 md:col-span-2' => $columnSpan === 2 || $columnSpan === 'full',
    ])
>
    {{-- Loading spinner --}}
    <div wire:loading class="absolute top-0 right-0 mt-1 mr-1 z-10">
        <svg class="h-4 w-4 text-primary-500 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </div>

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
