{{-- One checkbox in a CheckboxList. Shared by the flat and grouped layouts so
     the two can never drift apart. --}}
{{-- Variables: $field, $wireAttr, $value, $label --}}
<div
    class="flex items-center gap-2"
    @if($field->isSearchable()) x-show="!search || @js(strtolower($label)).includes(search.toLowerCase())" @endif
>
    <input
        type="checkbox"
        id="{{ $field->getId() }}-{{ $value }}"
        data-testid="form-checklist-{{ $field->getStatePath() }}-{{ $value }}"
        {{ $wireAttr }}="{{ $field->getWireModelAttribute() }}"
        value="{{ $value }}"
        @if($field->isDisabled()) disabled @endif
        class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 transition-colors duration-150 dark:bg-gray-800 dark:border-gray-600"
    />
    <label for="{{ $field->getId() }}-{{ $value }}" class="text-sm text-gray-700 dark:text-gray-300">
        {{ $label }}
    </label>
</div>
