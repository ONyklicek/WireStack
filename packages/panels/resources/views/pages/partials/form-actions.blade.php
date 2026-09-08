{{-- The one control a create or edit page ends with.

     The canonical Button rather than hand-written utilities: the submit used to
     carry its own `bg-primary-600 px-4 py-2` and so was the one button in the
     framework that did not move when the canonical colour and size resolvers
     did — no focus ring, no disabled state, no hover.

     Rendered as an object rather than as `<x-wire::button>`: the component tags
     are the consumer-facing API, and rule 5 says the framework itself must draw
     without them. Same class, same view, no dependency on the tag being
     registered. See Foundation\View\ComponentRenderer.

     Full width on a phone, where a 90px target floating in an empty row is a
     button you aim at; its natural width from `sm` up. --}}
@php
    use NyonCode\WireCore\Foundation\View\Button;
    use NyonCode\WireCore\Foundation\View\ComponentRenderer;
@endphp
<div class="flex flex-col gap-2 sm:flex-row sm:items-center">
    {!! ComponentRenderer::render(
        new Button(size: 'md', type: 'submit'),
        __('wire-panels::messages.save'),
        ['class' => 'w-full justify-center sm:w-auto'],
    ) !!}
</div>
