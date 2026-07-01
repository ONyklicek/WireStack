@php
    use NyonCode\WireCore\Foundation\Schema\Wizard;

    assert($layout instanceof Wizard);

    $steps = $layout->getSteps();
    $count = count($steps);
@endphp

<div
    x-data="{ step: {{ $layout->getActiveStep() }}, total: {{ $count }}, skippable: {{ $layout->isSkippable() ? 'true' : 'false' }} }"
    class="space-y-6"
>
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
            @click="step = Math.max(0, step - 1)"
            class="inline-flex items-center gap-1 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150"
        >
            <x-wire::icon name="outline:chevron-left" class="w-4 h-4" />
            {{ __('wire-core::actions.wizard_previous') }}
        </button>

        <span x-show="step === 0"></span>

        <button
            type="button"
            x-show="step < total - 1"
            @click="step = Math.min(total - 1, step + 1)"
            class="inline-flex items-center gap-1 rounded-md border border-transparent bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors duration-150"
        >
            {{ __('wire-core::actions.wizard_next') }}
            <x-wire::icon name="outline:chevron-right" class="w-4 h-4" />
        </button>
    </div>
</div>
