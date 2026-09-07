{{-- One labelled input.

     The classes are wire-forms' text input, not a second opinion about what an
     input looks like: these screens sit either side of the same door as the
     panel's own forms, and a login field that is styled differently from every
     other field in the application is the one place it shows.

     A partial rather than a component because it takes no behaviour — a
     component class here would exist to pass six strings through. --}}
@php
    $fieldId = $id ?? $name;
    $fieldErrors = $errors->get($name);
@endphp

<div>
    <label for="{{ $fieldId }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
        {{ $label }}
    </label>

    <input
        id="{{ $fieldId }}"
        name="{{ $name }}"
        type="{{ $type ?? 'text' }}"
        value="{{ $value ?? old($name) }}"
        @if ($autocomplete ?? false) autocomplete="{{ $autocomplete }}" @endif
        @if ($autofocus ?? false) autofocus @endif
        @if ($required ?? true) required @endif
        @if ($readonly ?? false) readonly @endif
        @if ($inputmode ?? false) inputmode="{{ $inputmode }}" @endif
        @class([
            'mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm',
            'focus:border-primary-500 focus:ring-primary-500',
            'placeholder:text-gray-400 dark:placeholder:text-gray-500',
            'hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-150',
            'dark:bg-gray-800 dark:border-gray-600 dark:text-white',
            'border-red-500 focus:border-red-500 focus:ring-red-500' => $fieldErrors !== [],
        ])
    />

    @foreach ($fieldErrors as $message)
        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @endforeach
</div>
