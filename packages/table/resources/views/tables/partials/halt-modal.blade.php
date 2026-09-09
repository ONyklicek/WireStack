{{-- Halt Modal (Dynamic Confirmation from Action) - uses wire-core confirmation component --}}
@if($component->tableState->get('modal.halt.show'))
    @php $haltModal = $component->getHaltModalData(); @endphp

    @php
        // Form body (if any) is a Htmlable Form instance passed straight to the object.
        $haltFormInstance = ($haltModal['hasForm'] ?? false) ? $component->getHaltModalFormInstance() : null;
    @endphp
    {{-- Rule 5: rendered as a Htmlable object, not the <x-*> component. --}}
    {{ new \NyonCode\WireCore\Modals\Html\Confirmation(
        heading: $haltModal['heading'] ?? null,
        description: $haltModal['description'] ?? null,
        width: $haltModal['width'] ?? 'md',
        icon: $haltModal['icon'] ?? null,
        iconColor: $haltModal['iconColor'] ?? 'warning',
        submitLabel: $haltModal['submitLabel'] ?? __('wire-table::messages.confirm_submit'),
        cancelLabel: ($haltModal['informative'] ?? false) ? __('wire-table::messages.confirm_close') : ($haltModal['cancelLabel'] ?? __('wire-table::messages.confirm_cancel')),
        color: $haltModal['color'] ?? null,
        isInformative: $haltModal['informative'] ?? false,
        closeAction: 'closeHaltModal',
        wireModel: 'tableState.modal.halt.show',
        wireClick: 'submitHaltModal',
        body: $haltFormInstance,
        footerActions: $haltModal['footerActions'] ?? [],
    ) }}
@endif
