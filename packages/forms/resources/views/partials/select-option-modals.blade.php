{{-- Create / edit option modals for a Select-family combobox, rendered only
     while mounted (one open at a time, keyed by state path — see
     InteractsWithSelectCreation). Shared by the base Select view and
     relationship variants (BelongsToSelect) so the modal flow has one owner.

     Expected variables:
       $field     Select   the field owning the option forms
       $livewire  mixed    the bound Livewire host (non-null when included) --}}
@php
    $isCreateModalMounted = $field->hasCreateOptionForm()
        && data_get($livewire, 'mountedCreateOptionSelect') === $field->getStatePath();
    $isEditModalMounted = $field->hasEditOptionForm() && ! $field->isMultiple()
        && data_get($livewire, 'mountedEditOptionSelect') === $field->getStatePath();

    // When the Select lives inside a stacked action modal, the option overlay must
    // sit one layer above the top action frame — otherwise it renders behind the
    // modal it was opened from. Outside any action modal (depth 0) the default
    // base layer is fine. The depth read is side-effect-free (no form rebuild).
    $optionModalZ = (is_object($livewire) && method_exists($livewire, 'actionStackDepth') && $livewire->actionStackDepth() > 0)
        ? \NyonCode\WireCore\Modals\ModalStack::zIndexForDepth($livewire->actionStackDepth())
        : null;
@endphp

@if($isCreateModalMounted)
    {{-- Rule 5: Htmlable object, not the <x-wire::modal> component. --}}
    {{ new \NyonCode\WireCore\Modals\Html\Modal(
        heading: $field->getCreateOptionModalHeading(),
        width: 'md',
        zIndex: $optionModalZ,
        {{-- Keys the teleport: nothing stops both option modals from being
             mounted at once (the two properties are independent), and two
             unkeyed Modal shells in one component share a wire:key. --}}
        id: 'select-create-option-'.md5($field->getStatePath()),
        closeAction: 'unmountCreateOption',
        wireModel: 'mountedCreateOptionSelect',
        bodyView: 'wire-forms::partials.select-option-modal-body',
        bodyData: ['field' => $field, 'form' => $field->getCreateOptionForm($livewire), 'mode' => 'create'],
        footerView: 'wire-forms::partials.select-option-modal-footer',
        footerData: ['mode' => 'create', 'cancelAction' => 'unmountCreateOption', 'saveAction' => 'createSelectOption', 'saveLabel' => __('wire-forms::fields.create')],
    ) }}
@endif

@if($isEditModalMounted)
    {{-- Rule 5: Htmlable object, not the <x-wire::modal> component. --}}
    {{ new \NyonCode\WireCore\Modals\Html\Modal(
        heading: $field->getEditOptionModalHeading(),
        width: 'md',
        zIndex: $optionModalZ,
        id: 'select-edit-option-'.md5($field->getStatePath()),
        closeAction: 'unmountEditOption',
        wireModel: 'mountedEditOptionSelect',
        bodyView: 'wire-forms::partials.select-option-modal-body',
        bodyData: ['field' => $field, 'form' => $field->getEditOptionForm($livewire), 'mode' => 'edit'],
        footerView: 'wire-forms::partials.select-option-modal-footer',
        footerData: ['mode' => 'edit', 'cancelAction' => 'unmountEditOption', 'saveAction' => 'updateSelectOption', 'saveLabel' => __('wire-forms::fields.save')],
    ) }}
@endif
