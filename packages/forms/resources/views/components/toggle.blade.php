@php /** @var \NyonCode\WireForms\Components\Toggle $field */
    $wireModifier = $field->getWireModelModifier();
    $wireAttr = 'wire:model' . ($wireModifier ? ".{$wireModifier}" : '');
@endphp

@include('wire-forms::partials.field-wrapper-start')

    <div
        x-data="{ enabled: @entangle($field->getWireModelAttribute()) }"
        class="flex items-center gap-3"
    >
        <button
            type="button"
            role="switch"
            :aria-checked="enabled ? 'true' : 'false'"
            @click="enabled = !enabled"
            @if($field->isDisabled()) disabled @endif
            :class="enabled ? 'bg-primary-600' : 'bg-gray-200 dark:bg-gray-700'"
            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
        >
            <span
                :class="enabled ? 'translate-x-5' : 'translate-x-0'"
                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
            ></span>
        </button>

        @if($field->getOnLabel() || $field->getOffLabel())
            <span class="text-sm text-gray-700 dark:text-gray-300" x-text="enabled ? @js($field->getOnLabel()) : @js($field->getOffLabel())"></span>
        @endif
    </div>

@include('wire-forms::partials.field-wrapper-end')
