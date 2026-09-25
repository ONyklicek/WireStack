{{-- Where a page's actions open — a confirmation, a form, a wizard, a halt.

     Once per page, after everything else, so a modal is never drawn inside the
     page's own form. The object rather than <x-wire-actions::modal-host>: rule 5
     keeps the tag for consumers and has the framework draw without it. The list
     page includes none of this, because its table already draws the host its
     header actions open in. --}}
@php
    use NyonCode\WireCore\Actions\View\ModalHostComponent;
    use NyonCode\WireCore\Foundation\View\ComponentRenderer;
@endphp
{!! ComponentRenderer::render(new ModalHostComponent(component: $this)) !!}
