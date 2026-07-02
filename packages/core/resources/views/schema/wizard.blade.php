@php
    use NyonCode\WireCore\Foundation\Schema\Wizard;

    assert($layout instanceof Wizard);

    $steps = $layout->getSteps();
    $count = count($steps);
    $livewire = $layout->getLivewire();
    // Per-step validation needs a host endpoint (wire-forms InteractsWithWizards);
    // without one — standalone render — "Next" advances client-side only.
    $canValidate = $livewire !== null && method_exists($livewire, 'validateWizardStep');
    // A wizard is addressed by its name so multiple wizards on one host resolve
    // independently; unnamed wizards fall back to "first wizard in the schema".
    $wizardKey = $layout->getName() !== '' ? $layout->getName() : null;

    // First visible step containing an errored field: after a failed full-form
    // submit the client jumps there, so messages aren't stranded in a hidden panel.
    $errorStep = null;
    if ($count > 0 && isset($errors) && $errors->any()) {
        foreach ($steps as $index => $step) {
            foreach ($step->getDescendantFieldStatePaths() as $path) {
                if ($errors->has($path)) {
                    $errorStep = $index;
                    break 2;
                }
            }
        }
    }
@endphp

{{-- The x-data expression must stay byte-identical across Livewire morphs: a
     changed x-data attribute makes Alpine re-initialize the whole component,
     resetting the active step. Anything render-dependent (step count, first
     errored step) flows in through the sync carrier below instead. --}}
<div
    x-data="{
        step: {{ $layout->getActiveStep() }},
        total: 0,
        skippable: @js($layout->isSkippable()),
        canValidate: @js($canValidate),
        wizard: @js($wizardKey),
        validating: false,
        sync(total, errorStep) {
            this.total = total;
            // Steps can appear/disappear mid-wizard (visibleWhen on a Step);
            // never point past the last rendered one.
            this.step = Math.min(this.step, Math.max(0, total - 1));
            if (errorStep !== null) this.step = errorStep;
        },
        async next() {
            if (this.step >= this.total - 1 || this.validating) return;
            if (! this.canValidate) { this.step++; return; }
            this.validating = true;
            try {
                if (await this.$wire.validateWizardStep(this.step, this.wizard)) this.step++;
            } finally {
                this.validating = false;
            }
        },
        prev() { this.step = Math.max(0, this.step - 1); }
    }"
    class="space-y-6"
>
    {{-- Sync carrier: x-init re-runs whenever a morph changes this expression
         (step count or first-error step moved), keeping the indicator in sync
         with the server-rendered steps — without touching the x-data scope. --}}
    <div x-init="sync({{ $count }}, {{ $errorStep ?? 'null' }})" class="hidden" aria-hidden="true"></div>
    {{-- Step indicator --}}
    <ol class="flex items-center">
        @foreach($steps as $index => $step)
            <li @class(['flex items-center', 'flex-1' => ! $loop->last])>
                <button
                    type="button"
                    @click="(skippable || {{ $index }} <= step) && (step = {{ $index }})"
                    :disabled="! skippable && {{ $index }} > step"
                    class="flex items-center gap-2 text-left focus:outline-none disabled:cursor-not-allowed"
                >
                    <span
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold transition-colors duration-150"
                        :class="step === {{ $index }}
                            ? 'bg-primary-600 text-white'
                            : (step > {{ $index }} ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400')"
                    >
                        {{ $index + 1 }}
                    </span>
                    <span class="hidden sm:block">
                        <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ $step->getLabel() }}</span>
                        @if($step->getDescription())
                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $step->getDescription() }}</span>
                        @endif
                    </span>
                </button>

                @if(! $loop->last)
                    <div class="mx-3 h-px flex-1 bg-gray-200 dark:bg-gray-700"></div>
                @endif
            </li>
        @endforeach
    </ol>

    {{-- Mobile: circles above carry no text, so name the active step here. --}}
    <div class="sm:hidden">
        @foreach($steps as $index => $step)
            <p x-show="step === {{ $index }}" x-cloak class="text-sm font-medium text-gray-900 dark:text-white">
                {{ $step->getLabel() }}
                @if($step->getDescription())
                    <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">{{ $step->getDescription() }}</span>
                @endif
            </p>
        @endforeach
    </div>

    {{-- Panels: all kept in the DOM so nested fields validate together. --}}
    @foreach($steps as $index => $step)
        <div x-show="step === {{ $index }}" x-cloak>
            {{ $step }}
        </div>
    @endforeach

    {{-- Navigation --}}
    <div class="flex items-center justify-between border-t border-gray-200 dark:border-gray-700 pt-4">
        <button
            type="button"
            x-show="step > 0"
            @click="prev()"
            class="inline-flex items-center gap-1 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150"
        >
            <x-wire::icon name="outline:chevron-left" class="w-4 h-4" />
            {{ __('wire-core::actions.wizard_previous') }}
        </button>

        <span x-show="step === 0"></span>

        <button
            type="button"
            x-show="step < total - 1"
            @click="next()"
            :disabled="validating"
            class="inline-flex items-center gap-1 rounded-md border border-transparent bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-wait transition-colors duration-150"
        >
            {{ __('wire-core::actions.wizard_next') }}
            <x-wire::icon name="outline:chevron-right" class="w-4 h-4" />
        </button>
    </div>
</div>
