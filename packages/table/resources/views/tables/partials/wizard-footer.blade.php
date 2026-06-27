{{-- Wizard footer: Back / Next / Submit driven by the current step.
     Expects: $currentStep (int), $totalSteps (int), $modalData (array),
              $primaryClasses (string), $secondaryClasses (string). --}}
@php
    $isFirstStep = $currentStep <= 0;
    $isLastStep = $currentStep >= $totalSteps - 1;
@endphp
<div class="flex w-full flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
    <button type="button" wire:click="closeActionModal" class="{{ $secondaryClasses }}">
        {{ $modalData['cancelLabel'] }}
    </button>

    <div class="flex flex-col-reverse gap-3 sm:flex-row">
        @unless($isFirstStep)
            <button
                type="button"
                wire:click="prevActionModalStep"
                wire:loading.attr="disabled"
                wire:target="prevActionModalStep"
                class="{{ $secondaryClasses }}"
            >
                {{ __('Back') }}
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
                <span wire:loading wire:target="submitActionModal">{{ __('Saving...') }}</span>
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
                <span>{{ __('Next') }}</span>
            </button>
        @endif
    </div>
</div>
