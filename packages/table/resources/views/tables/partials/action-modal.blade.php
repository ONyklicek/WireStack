{{-- Action Modal (Confirmation or Form) - uses wire-core modal components --}}
@if($component->tableState->get('modal.action.show'))
    @php
        $modalData = $component->getActionModalData();
        $actionFormInstance = $component->getActionModalFormInstance();
        $actionInfolistInstance = $component->getActionModalInfolistInstance();
        $hasInfolist = $modalData['hasInfolist'] ?? false;
        $isSlideOver = $modalData['slideOver'] ?? false;
        $isSlideOverOnMobile = $modalData['slideOverOnMobile'] ?? false;
        $isFullScreenMobile = $modalData['fullScreenOnMobile'] ?? false;
        $wizardSteps = $modalData['steps'] ?? null;
        $isWizard = is_array($wizardSteps) && count($wizardSteps) > 0;
        $wizardCurrentStep = $isWizard ? (int) $component->tableState->get('modal.action.currentStep', 0) : 0;
        $wizardTotalSteps = $isWizard ? count($wizardSteps) : 0;
    @endphp

    @if(!empty($modalData) && isset($modalData['heading']))
        @if($isSlideOver)
            {{-- Slide Over Panel --}}
            <x-wire-modals::slide-over
                wire:model="tableState.modal.action.show"
                :heading="$modalData['heading']"
                :description="$modalData['description'] ?? null"
                :width="$modalData['width'] ?? 'md'"
                :close-on-click-away="$modalData['closeOnClickAway'] ?? true"
                :close-on-escape="$modalData['closeOnEscape'] ?? true"
                :bottom-sheet-on-mobile="$isSlideOverOnMobile"
                :breakpoint="$modalData['mobileBreakpoint'] ?? null"
                :sticky-header="$modalData['stickyHeader'] ?? false"
                :sticky-footer="$modalData['stickyFooter'] ?? false"
                :max-height="$modalData['maxHeight'] ?? null"
                close-action="closeActionModal"
            >
                @if($isWizard)
                    @include('wire-table::tables.partials.wizard-steps', [
                        'steps' => $wizardSteps,
                        'currentStep' => $wizardCurrentStep,
                    ])
                @endif

                @if($actionFormInstance)
                    {{ $actionFormInstance }}
                @elseif($actionInfolistInstance)
                    {{ $actionInfolistInstance }}
                @endif

                <x-slot:footer>
                    @if($isWizard)
                        @include('wire-table::tables.partials.wizard-footer', [
                            'currentStep' => $wizardCurrentStep,
                            'totalSteps' => $wizardTotalSteps,
                            'modalData' => $modalData,
                            'secondaryClasses' => 'rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600',
                            'primaryClasses' => 'rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm inline-flex items-center gap-2 '.($modalData['submitButtonClasses'] ?? \NyonCode\WireCore\Foundation\Concerns\HasColor::getModalSubmitButtonClasses($modalData['actionColor'] ?? 'primary')),
                        ])
                    @else
                    <div class="flex justify-end gap-3">
                        @include('wire-table::tables.partials.modal-footer-actions', [
                            'footerActions' => $modalData['footerActions'] ?? [],
                            'position' => 'before',
                        ])
                        <button
                            type="button"
                            wire:click="closeActionModal"
                            class="rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600"
                        >
                            {{ $modalData['cancelLabel'] }}
                        </button>
                        @unless($hasInfolist)
                        <button
                            type="button"
                            wire:click="submitActionModal"
                            wire:loading.attr="disabled"
                            wire:target="submitActionModal"
                            @class([
                                'rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm inline-flex items-center gap-2',
                                $modalData['submitButtonClasses'] ?? \NyonCode\WireCore\Foundation\Concerns\HasColor::getModalSubmitButtonClasses($modalData['actionColor'] ?? 'primary'),
                            ])
                        >
                            @include('wire-core::partials.spinner', ['wireTarget' => 'submitActionModal', 'class' => 'h-4 w-4'])
                            <span wire:loading.remove wire:target="submitActionModal">{{ $modalData['submitLabel'] }}</span>
                            <span wire:loading wire:target="submitActionModal">{{ $modalData['savingLabel'] ?? __('Saving...') }}</span>
                        </button>
                        @endunless
                        @include('wire-table::tables.partials.modal-footer-actions', [
                            'footerActions' => $modalData['footerActions'] ?? [],
                            'position' => 'after',
                        ])
                    </div>
                    @endif
                </x-slot:footer>
            </x-wire-modals::slide-over>
        @elseif($modalData['isConfirmation'] ?? false)
            {{-- Confirmation Modal --}}
            @php
                $iconColor = $modalData['iconColor'] ?? 'warning';
            @endphp
            <x-wire-modals::confirmation
                wire:model="tableState.modal.action.show"
                wire:click="submitActionModal"
                :heading="$modalData['heading']"
                :description="$modalData['description'] ?? null"
                :width="$modalData['width'] ?? 'md'"
                icon="exclamation-triangle"
                :icon-color="$iconColor"
                :submit-label="$modalData['submitLabel']"
                :cancel-label="$modalData['cancelLabel']"
                :color="$modalData['actionColor'] ?? 'primary'"
                :close-on-click-away="$modalData['closeOnClickAway'] ?? true"
                :close-on-escape="$modalData['closeOnEscape'] ?? true"
                close-action="closeActionModal"
            />
        @else
            {{-- Form Modal --}}
            <x-wire-modals::modal
                wire:model="tableState.modal.action.show"
                :heading="$modalData['heading']"
                :description="$modalData['description'] ?? null"
                :width="$modalData['width'] ?? 'md'"
                :close-on-click-away="$modalData['closeOnClickAway'] ?? true"
                :close-on-escape="$modalData['closeOnEscape'] ?? true"
                :full-screen-on-mobile="$isFullScreenMobile"
                :slide-over-on-mobile="$isSlideOverOnMobile"
                :breakpoint="$modalData['mobileBreakpoint'] ?? null"
                :sticky-footer="true"
                close-action="closeActionModal"
            >
                @if($isWizard)
                    @include('wire-table::tables.partials.wizard-steps', [
                        'steps' => $wizardSteps,
                        'currentStep' => $wizardCurrentStep,
                    ])
                @endif

                @if($actionFormInstance)
                    {{ $actionFormInstance }}
                @elseif($actionInfolistInstance)
                    {{ $actionInfolistInstance }}
                @endif

                <x-slot:footer>
                    @if($isWizard)
                        @include('wire-table::tables.partials.wizard-footer', [
                            'currentStep' => $wizardCurrentStep,
                            'totalSteps' => $wizardTotalSteps,
                            'modalData' => $modalData,
                            'secondaryClasses' => 'inline-flex w-full justify-center rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-3 sm:py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 active:bg-gray-100 dark:active:bg-gray-600 sm:w-auto touch-manipulation',
                            'primaryClasses' => 'inline-flex w-full justify-center items-center gap-2 rounded-xl px-4 py-3 sm:py-2.5 text-sm font-semibold text-white shadow-sm sm:w-auto touch-manipulation '.($modalData['submitButtonClasses'] ?? \NyonCode\WireCore\Foundation\Concerns\HasColor::getModalSubmitButtonClasses($modalData['actionColor'] ?? 'primary')),
                        ])
                    @else
                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                        @include('wire-table::tables.partials.modal-footer-actions', [
                            'footerActions' => $modalData['footerActions'] ?? [],
                            'position' => 'before',
                        ])
                        <button
                            type="button"
                            wire:click="closeActionModal"
                            class="inline-flex w-full justify-center rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-3 sm:py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 active:bg-gray-100 dark:active:bg-gray-600 sm:w-auto touch-manipulation"
                        >
                            {{ $modalData['cancelLabel'] }}
                        </button>
                        @unless($hasInfolist)
                        <button
                            type="button"
                            wire:click="submitActionModal"
                            wire:loading.attr="disabled"
                            wire:target="submitActionModal"
                            @class([
                                'inline-flex w-full justify-center items-center gap-2 rounded-xl px-4 py-3 sm:py-2.5 text-sm font-semibold text-white shadow-sm sm:w-auto touch-manipulation',
                                $modalData['submitButtonClasses'] ?? \NyonCode\WireCore\Foundation\Concerns\HasColor::getModalSubmitButtonClasses($modalData['actionColor'] ?? 'primary'),
                            ])
                        >
                            @include('wire-core::partials.spinner', ['wireTarget' => 'submitActionModal', 'class' => 'h-4 w-4'])
                            <span wire:loading.remove wire:target="submitActionModal">{{ $modalData['submitLabel'] }}</span>
                            <span wire:loading wire:target="submitActionModal">{{ $modalData['savingLabel'] ?? __('Saving...') }}</span>
                        </button>
                        @endunless
                        @include('wire-table::tables.partials.modal-footer-actions', [
                            'footerActions' => $modalData['footerActions'] ?? [],
                            'position' => 'after',
                        ])
                    </div>
                    @endif
                </x-slot:footer>
            </x-wire-modals::modal>
        @endif
    @endif
@endif
