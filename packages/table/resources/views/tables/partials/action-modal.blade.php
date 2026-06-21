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
                close-action="closeActionModal"
            >
                @if($actionFormInstance)
                    {{ $actionFormInstance }}
                @elseif($actionInfolistInstance)
                    {{ $actionInfolistInstance }}
                @endif

                <x-slot:footer>
                    <div class="flex justify-end gap-3">
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
                                'bg-primary-600 hover:bg-primary-700' => ($modalData['actionColor'] ?? 'primary') === 'primary',
                                'bg-red-600 hover:bg-red-700' => ($modalData['actionColor'] ?? 'primary') === 'danger',
                                'bg-emerald-600 hover:bg-emerald-700' => ($modalData['actionColor'] ?? 'primary') === 'success',
                                'bg-amber-500 hover:bg-amber-600' => ($modalData['actionColor'] ?? 'primary') === 'warning',
                            ])
                        >
                            <svg wire:loading wire:target="submitActionModal" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span wire:loading.remove wire:target="submitActionModal">{{ $modalData['submitLabel'] }}</span>
                            <span wire:loading wire:target="submitActionModal">{{ __('Saving...') }}</span>
                        </button>
                        @endunless
                    </div>
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
            @php
                $slideUpOnMobile = $isSlideOverOnMobile || $isFullScreenMobile;
            @endphp
            <x-wire-modals::modal
                wire:model="tableState.modal.action.show"
                :heading="$modalData['heading']"
                :description="$modalData['description'] ?? null"
                :width="$modalData['width'] ?? 'md'"
                :close-on-click-away="$modalData['closeOnClickAway'] ?? true"
                :close-on-escape="$modalData['closeOnEscape'] ?? true"
                :full-screen-on-mobile="$isFullScreenMobile"
                :sticky-footer="true"
                close-action="closeActionModal"
            >
                @if($actionFormInstance)
                    {{ $actionFormInstance }}
                @elseif($actionInfolistInstance)
                    {{ $actionInfolistInstance }}
                @endif

                <x-slot:footer>
                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
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
                                'bg-primary-600 hover:bg-primary-700 active:bg-primary-800 focus:ring-primary-500' => ($modalData['actionColor'] ?? 'primary') === 'primary',
                                'bg-red-600 hover:bg-red-700 active:bg-red-800 focus:ring-red-500' => ($modalData['actionColor'] ?? 'primary') === 'danger',
                                'bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 focus:ring-emerald-500' => ($modalData['actionColor'] ?? 'primary') === 'success',
                                'bg-amber-500 hover:bg-amber-600 active:bg-amber-700 focus:ring-amber-500' => ($modalData['actionColor'] ?? 'primary') === 'warning',
                            ])
                        >
                            <svg wire:loading wire:target="submitActionModal" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span wire:loading.remove wire:target="submitActionModal">{{ $modalData['submitLabel'] }}</span>
                            <span wire:loading wire:target="submitActionModal">{{ __('Saving...') }}</span>
                        </button>
                        @endunless
                    </div>
                </x-slot:footer>
            </x-wire-modals::modal>
        @endif
    @endif
@endif
