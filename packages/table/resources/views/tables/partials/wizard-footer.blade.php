{{-- Wizard footer: Back / Next / Submit driven by the current step, plus any
     custom modalFooterActions() (rendered the same as the non-wizard footer).
     Expects: $currentStep (int), $totalSteps (int), $modalData (array),
              $primaryClasses (string), $secondaryClasses (string). --}}
@php
    $isFirstStep = $currentStep <= 0;
    $isLastStep = $currentStep >= $totalSteps - 1;
    $footerActions = $modalData['footerActions'] ?? [];
@endphp
<div class="flex w-full flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
        <button type="button" wire:click="closeActionModal" class="{{ $secondaryClasses }}">
            {{ $modalData['cancelLabel'] }}
        </button>
        @include('wire-table::tables.partials.modal-footer-actions', [
            'footerActions' => $footerActions,
            'position' => 'before',
        ])
    </div>

    <div class="flex flex-col-reverse gap-3 sm:flex-row">
        @include('wire-table::tables.partials.modal-footer-actions', [
            'footerActions' => $footerActions,
            'position' => 'after',
        ])

        @unless($isFirstStep)
            <button
                type="button"
                wire:click="prevActionModalStep"
                wire:loading.attr="disabled"
                wire:target="prevActionModalStep"
                class="{{ $secondaryClasses }}"
            >
                {{ $modalData['previousLabel'] ?? __('Back') }}
            </button>
        @endunless

        @if($isLastStep)
            <button
                type="button"
                wire:click="submitActionModal"
                wire:loading.attr="disabled"
                wire:target="submitActionModal"
                class="{{ $primaryClasses }}"
            >
                @include('wire-core::partials.spinner', ['wireTarget' => 'submitActionModal', 'class' => 'h-4 w-4'])
                <span wire:loading.remove wire:target="submitActionModal">{{ $modalData['submitLabel'] }}</span>
                <span wire:loading wire:target="submitActionModal">{{ $modalData['savingLabel'] ?? __('Saving...') }}</span>
            </button>
        @else
            <button
                type="button"
                wire:click="nextActionModalStep"
                wire:loading.attr="disabled"
                wire:target="nextActionModalStep"
                class="{{ $primaryClasses }}"
            >
                @include('wire-core::partials.spinner', ['wireTarget' => 'nextActionModalStep', 'class' => 'h-4 w-4'])
                <span>{{ $modalData['nextLabel'] ?? __('Next') }}</span>
            </button>
        @endif
    </div>
</div>
