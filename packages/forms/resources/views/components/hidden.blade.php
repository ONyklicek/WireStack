@php /** @var \NyonCode\WireForms\Components\Hidden $field */ @endphp
<input
    type="hidden"
    id="{{ $field->getId() }}"
    wire:model{{ $field->getWireModelModifier() ? '.' . $field->getWireModelModifier() : '' }}="{{ $field->getWireModelAttribute() }}"
/>
