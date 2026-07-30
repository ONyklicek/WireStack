{{-- The browser's own date/time control, shared by every picker that offers a
     native fallback (DateTimePicker, TimePicker). The input type and the min/max
     shape come from the field, so this partial stays mode-agnostic.

     Extracted rather than copied: the two pickers differ only in their custom
     panel, and a duplicated native branch is the half that would silently drift.

     Expects: $field, $fieldId, $wireAttr, $minBound, $maxBound. --}}
<input
        type="{{ $field->getNativeInputType() }}"
        id="{{ $fieldId }}"
{{ $wireAttr }}="{{ $field->getWireModelAttribute() }}"
@if($field->getPlaceholder())
    placeholder="{{ $field->getPlaceholder() }}"
@endif
@if($minBound)
    min="{{ $minBound }}"
@endif
@if($maxBound)
    max="{{ $maxBound }}"
@endif
@if($field->isDisabled())
    disabled
@endif
@if($field->isReadOnly())
    readonly
@endif
@if($field->hasAutofocus())
    autofocus
@endif
@if($field->isRequired())
    required
@endif
@class([
    'block w-full rounded-md border-gray-300 shadow-sm',
    'focus:border-primary-500 focus:ring-primary-500',
    'hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-150',
    'dark:bg-gray-800 dark:border-gray-600 dark:text-white text-sm',
    'border-red-500 focus:border-red-500 focus:ring-red-500' => $errors->has($field->getStatePath()),
])
/>
