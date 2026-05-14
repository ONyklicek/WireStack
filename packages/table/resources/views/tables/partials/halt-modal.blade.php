{{-- Halt Modal (Dynamic Confirmation from Action) --}}
{{-- Uses wire-core modal styling patterns. Full migration to <x-wire-modals::confirmation> --}}
{{-- planned for Phase 6 when table-specific wiring is refactored. --}}
@if($component->showHaltModal ?? false)
    @php $haltModal = $component->getHaltModalData(); @endphp
    <div
        x-data="{ show: @entangle('showHaltModal') }"
        x-show="show"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="halt-modal-title"
        role="dialog"
        aria-modal="true"
    >
        <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            {{-- Backdrop --}}
            <div
                x-show="show"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/80 backdrop-blur-sm transition-opacity"
                @click="$wire.closeHaltModal()"
            ></div>

            <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

            {{-- Modal Panel --}}
            <div
                x-show="show"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                @class([
                    'relative inline-block transform overflow-hidden rounded-2xl bg-white dark:bg-gray-800 px-4 pt-5 pb-4 text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:p-6 sm:align-middle',
                    'sm:max-w-sm' => ($haltModal['width'] ?? 'md') === 'sm',
                    'sm:max-w-md' => ($haltModal['width'] ?? 'md') === 'md',
                    'sm:max-w-lg' => ($haltModal['width'] ?? 'md') === 'lg',
                    'sm:max-w-xl' => ($haltModal['width'] ?? 'md') === 'xl',
                    'sm:max-w-2xl' => ($haltModal['width'] ?? 'md') === '2xl',
                ])
            >
                <div class="sm:flex sm:items-start">
                    {{-- Icon (uses wire-core icon component) --}}
                    @if($haltModal['icon'] ?? null)
                        @php
                            $iconBgColor = match($haltModal['iconColor'] ?? 'warning') {
                                'danger' => 'bg-red-100 dark:bg-red-900/30',
                                'success' => 'bg-emerald-100 dark:bg-emerald-900/30',
                                'info' => 'bg-blue-100 dark:bg-blue-900/30',
                                default => 'bg-amber-100 dark:bg-amber-900/30',
                            };
                            $iconColor = match($haltModal['iconColor'] ?? 'warning') {
                                'danger' => 'text-red-600 dark:text-red-400',
                                'success' => 'text-emerald-600 dark:text-emerald-400',
                                'info' => 'text-blue-600 dark:text-blue-400',
                                default => 'text-amber-600 dark:text-amber-400',
                            };
                        @endphp
                        <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full {{ $iconBgColor }} sm:mx-0 sm:h-10 sm:w-10">
                            <x-wire::icon :name="$haltModal['icon']" :class="'h-6 w-6 ' . $iconColor" />
                        </div>
                    @endif

                    <div class="mt-3 text-center sm:mt-0 {{ ($haltModal['icon'] ?? null) ? 'sm:ml-4' : '' }} sm:text-left flex-1">
                        @if($haltModal['heading'] ?? null)
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="halt-modal-title">
                                {{ $haltModal['heading'] }}
                            </h3>
                        @endif

                        @if($haltModal['description'] ?? null)
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $haltModal['description'] }}
                                </p>
                            </div>
                        @endif

                        {{-- Form Fields --}}
                        @if($haltModal['hasForm'] ?? false)
                            <div class="mt-4">
                                @php $haltFormInstance = $component->getHaltModalFormInstance(); @endphp
                                @if($haltFormInstance)
                                    {!! $haltFormInstance->toHtml() !!}
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Buttons --}}
                <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse gap-3">
                    {{-- Submit button only if not informative --}}
                    @unless($haltModal['informative'] ?? false)
                        <button
                            type="button"
                            wire:click="submitHaltModal"
                            @class([
                                'inline-flex w-full justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm sm:w-auto',
                                'bg-red-600 hover:bg-red-500 focus:ring-red-500' => ($haltModal['color'] ?? null) === 'danger',
                                'bg-emerald-600 hover:bg-emerald-500 focus:ring-emerald-500' => ($haltModal['color'] ?? null) === 'success',
                                'bg-amber-500 hover:bg-amber-400 focus:ring-amber-500' => ($haltModal['color'] ?? null) === 'warning',
                                'bg-primary-600 hover:bg-primary-500 focus:ring-primary-500' => !in_array($haltModal['color'] ?? null, ['danger', 'success', 'warning']),
                            ])
                        >
                            {{ $haltModal['submitLabel'] ?? __('wire-table::messages.confirm_submit') }}
                        </button>
                    @endunless

                    {{-- Cancel / Close button --}}
                    <button
                        type="button"
                        wire:click="closeHaltModal"
                        class="mt-3 inline-flex w-full justify-center rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 sm:mt-0 sm:w-auto"
                    >
                        {{ $haltModal['informative'] ?? false ? __('wire-table::messages.confirm_close') : ($haltModal['cancelLabel'] ?? __('wire-table::messages.confirm_cancel')) }}
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
