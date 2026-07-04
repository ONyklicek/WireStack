{{-- Renders the mounted action's modal / slide-over / wizard / confirmation for
     a WithActions host. Self-contained (no table view dependencies) and driven
     entirely by the host's action engine. --}}
@php
    use NyonCode\WireCore\Foundation\Concerns\HasColor;

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
    @endphp

    @if(! empty($modalData) && isset($modalData['heading']))
        {{-- Confirmation --}}
        @if(($modalData['isConfirmation'] ?? false) && ! $isSlideOver && ! $actionFormInstance)
            <x-wire-modals::confirmation
                wire:model="{{ $showModel }}"
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
                close-action="{{ $closeAction }}"
            />
        @else
            {{-- Modal / Slide-over shell --}}
            @if($isSlideOver)
                <x-wire-modals::slide-over
                    wire:model="{{ $showModel }}"
                    :heading="$modalData['heading']"
                    :description="$modalData['description'] ?? null"
                    :width="$modalData['width'] ?? 'md'"
                    :close-on-click-away="$modalData['closeOnClickAway'] ?? true"
                    :close-on-escape="$modalData['closeOnEscape'] ?? true"
                    :bottom-sheet-on-mobile="$isSlideOverOnMobile"
                    close-action="{{ $closeAction }}"
                >
                    @include('wire-core::actions.partials.modal-host-body')
                    <x-slot:footer>
                        @include('wire-core::actions.partials.modal-host-footer')
                    </x-slot:footer>
                </x-wire-modals::slide-over>
            @else
                <x-wire-modals::modal
                    wire:model="{{ $showModel }}"
                    :heading="$modalData['heading']"
                    :description="$modalData['description'] ?? null"
                    :width="$modalData['width'] ?? 'md'"
                    :close-on-click-away="$modalData['closeOnClickAway'] ?? true"
                    :close-on-escape="$modalData['closeOnEscape'] ?? true"
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
