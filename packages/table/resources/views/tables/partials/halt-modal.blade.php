{{-- Halt Modal (Dynamic Confirmation from Action) - uses wire-core confirmation component --}}
@if($component->tableState->get('modal.halt.show'))
    @php $haltModal = $component->getHaltModalData(); @endphp

    <x-wire-modals::confirmation
        wire:model="tableState.modal.halt.show"
        wire:click="submitHaltModal"
        :heading="$haltModal['heading'] ?? null"
        :description="$haltModal['description'] ?? null"
        :width="$haltModal['width'] ?? 'md'"
        :icon="$haltModal['icon'] ?? null"
        :icon-color="$haltModal['iconColor'] ?? 'warning'"
        :submit-label="$haltModal['submitLabel'] ?? __('wire-table::messages.confirm_submit')"
        :cancel-label="($haltModal['informative'] ?? false) ? __('wire-table::messages.confirm_close') : ($haltModal['cancelLabel'] ?? __('wire-table::messages.confirm_cancel'))"
        :color="$haltModal['color'] ?? null"
        :is-informative="$haltModal['informative'] ?? false"
        close-action="closeHaltModal"
    >
        {{-- Form Fields --}}
        @if($haltModal['hasForm'] ?? false)
            @php $haltFormInstance = $component->getHaltModalFormInstance(); @endphp
            @if($haltFormInstance)
                {!! $haltFormInstance->toHtml() !!}
            @endif
        @endif
    </x-wire-modals::confirmation>
@endif
