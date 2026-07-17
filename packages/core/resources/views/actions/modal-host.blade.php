{{-- Renders the mounted action's modal / slide-over / wizard / confirmation for
     a WithActions host. Self-contained (no table view dependencies) and driven
     entirely by the host's action engine. --}}

{{-- Preload the floating-dropdown bundle on the host's initial render (this host
     view is always rendered, even with no modal open). A form modal opened by a
     later action can contain a searchable Select / dropdown whose `$float` magic
     must already be registered — its own @assets sits inside the teleported modal
     that only appears on the action roundtrip, which Livewire does not inject in
     time, so the panel would otherwise pin to the top-left corner. --}}
@include('wire-core::partials.floating-assets')

@php
    use NyonCode\WireCore\Foundation\Concerns\HasColor;
    use NyonCode\WireCore\Modals\ModalStack;

    /** @var object $component The Livewire host (WithActions). */
    $secondaryButtonClasses = 'inline-flex w-full sm:w-auto justify-center rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600';
@endphp

@if($component->isActionModalVisible())
    @php
        $modalData = $component->getActionModalData();
        $actionFormInstance = $component->getActionModalFormInstance();
        $actionInfolistInstance = $component->getActionModalInfolistInstance();
        $hasInfolist = $modalData['hasInfolist'] ?? false;
        $isSlideOver = $modalData['slideOver'] ?? false;
        $isFullScreenMobile = $modalData['fullScreenOnMobile'] ?? false;
        $isSlideOverOnMobile = $modalData['slideOverOnMobile'] ?? false;
        $wizardSteps = $modalData['steps'] ?? null;
        $isWizard = is_array($wizardSteps) && count($wizardSteps) > 0;
        $currentStep = $isWizard ? $component->getMountedActionStepIndex() : 0;
        $totalSteps = $isWizard ? count($wizardSteps) : 0;
        $isLastStep = ! $isWizard || $currentStep >= ($totalSteps - 1);
        $primaryButtonClasses = 'inline-flex w-full sm:w-auto justify-center items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm '
            .($modalData['submitButtonClasses'] ?? HasColor::getModalSubmitButtonClasses($modalData['actionColor'] ?? 'primary'));

        // Modal stacking: every open modal is a live frame. Draw each parent
        // frame (all but the top) as a dimmed, click-inert — but still live —
        // form behind the active one, then layer the active modal on top. The
        // parent forms stay bound to their own depth-scoped state path, so a
        // nested modal's $setParent write is reflected behind it immediately.
        $mountedModals = $component->getMountedActionModals();
        $stackDepth = max(0, count($mountedModals) - 1);
        $activeZIndex = $stackDepth > 0 ? ModalStack::zIndexForDepth($stackDepth) : null;
        // The active modal binds to a stable flag (not a per-depth path) so the
        // reused modal element is not re-initialised by Alpine on push/pop.
        $activeShowModel = 'actionModalOpen';
    @endphp

    @for($depth = 0; $depth < $stackDepth; $depth++)
        @include('wire-core::modals.suspended', [
            'modalData' => $mountedModals[$depth],
            'formInstance' => $component->getActionModalFormInstanceForDepth($depth),
            'zIndex' => ModalStack::zIndexForDepth($depth),
            'depthBelowTop' => $stackDepth - $depth,
        ])
    @endfor

    @if(! empty($modalData) && isset($modalData['heading']))
        {{-- Confirmation --}}
        @if(($modalData['isConfirmation'] ?? false) && ! $isSlideOver && ! $actionFormInstance)
            <x-wire-modals::confirmation
                wire:model="{{ $activeShowModel }}"
                wire:click="{{ $submitAction }}"
                :heading="$modalData['heading']"
                :description="$modalData['description'] ?? null"
                :width="$modalData['width'] ?? 'md'"
                icon="exclamation-triangle"
                :icon-color="$modalData['iconColor'] ?? 'warning'"
                :submit-label="$modalData['submitLabel']"
                :cancel-label="$modalData['cancelLabel']"
                :color="$modalData['actionColor'] ?? 'primary'"
                :close-on-click-away="$modalData['closeOnClickAway'] ?? true"
                :close-on-escape="$modalData['closeOnEscape'] ?? true"
                :z-index="$activeZIndex"
                close-action="{{ $closeAction }}"
            />
        @else
            {{-- Modal / Slide-over shell --}}
            @if($isSlideOver)
                <x-wire-modals::slide-over
                    wire:model="{{ $activeShowModel }}"
                    :heading="$modalData['heading']"
                    :description="$modalData['description'] ?? null"
                    :width="$modalData['width'] ?? 'md'"
                    :close-on-click-away="$modalData['closeOnClickAway'] ?? true"
                    :close-on-escape="$modalData['closeOnEscape'] ?? true"
                    :z-index="$activeZIndex"
                    :bottom-sheet-on-mobile="$isSlideOverOnMobile"
                    :sticky-header="$modalData['stickyHeader'] ?? false"
                    :sticky-footer="$modalData['stickyFooter'] ?? false"
                    :max-height="$modalData['maxHeight'] ?? null"
                    close-action="{{ $closeAction }}"
                >
                    @include('wire-core::actions.partials.modal-host-body')
                    <x-slot:footer>
                        @include('wire-core::actions.partials.modal-host-footer')
                    </x-slot:footer>
                </x-wire-modals::slide-over>
            @else
                <x-wire-modals::modal
                    wire:model="{{ $activeShowModel }}"
                    :heading="$modalData['heading']"
                    :description="$modalData['description'] ?? null"
                    :width="$modalData['width'] ?? 'md'"
                    :close-on-click-away="$modalData['closeOnClickAway'] ?? true"
                    :close-on-escape="$modalData['closeOnEscape'] ?? true"
                    :z-index="$activeZIndex"
                    :full-screen-on-mobile="$isFullScreenMobile"
                    :slide-over-on-mobile="$isSlideOverOnMobile"
                    :sticky-footer="true"
                    close-action="{{ $closeAction }}"
                >
                    @include('wire-core::actions.partials.modal-host-body')
                    <x-slot:footer>
                        @include('wire-core::actions.partials.modal-host-footer')
                    </x-slot:footer>
                </x-wire-modals::modal>
            @endif
        @endif
    @endif
@endif
