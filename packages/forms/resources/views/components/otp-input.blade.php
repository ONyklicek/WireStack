@php
    use NyonCode\WireForms\Components\OtpInput;
    assert($field instanceof OtpInput);
@endphp

@include('wire-forms::partials.field-assets')

@include('wire-forms::partials.field-wrapper-start')

<div
    {{-- Body registered as `wireOtpInput`; only per-instance config here. --}}
    x-data="wireOtpInput({
        length: @js($field->getLength()),
        statePath: @js($field->getWireModelAttribute()),
        numericOnly: @js($field->isNumericOnly()),
    })"
    class="flex items-center gap-2"
>
    @for($i = 0; $i < $field->getLength(); $i++)
        @if($field->getSeparator() && $i > 0 && $i % $field->getSeparator() === 0)
            <span class="text-gray-400 dark:text-gray-500 font-medium select-none">—</span>
        @endif

        <input
            type="{{ $field->isMasked() ? 'password' : 'text' }}"
            x-ref="digit-{{ $i }}" data-testid="form-otp-{{ $field->getStatePath() }}-{{ $i }}"
            :value="digits[{{ $i }}]"
            @input="onInput({{ $i }}, $event)"
            @keydown="onKeydown({{ $i }}, $event)"
            @paste="onPaste($event)"
            @focus="$event.target.select()"
            maxlength="2"
            @if($field->isNumericOnly()) inputmode="numeric" pattern="[0-9]*" @endif
            @if($field->isDisabled()) disabled @endif
            @if($field->isReadOnly()) readonly @endif
            @class([
                'w-11 h-12 text-center text-lg font-semibold rounded-md border bg-white dark:bg-gray-800',
                'border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white',
                'focus:border-primary-500 focus:ring-1 focus:ring-primary-500 focus:outline-none',
                'hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-150',
                'border-red-500 focus:border-red-500 focus:ring-red-500' => $errors->has($field->getStatePath()),
                'disabled:opacity-50 disabled:cursor-not-allowed' => $field->isDisabled(),
            ])
        />
    @endfor
</div>

@include('wire-forms::partials.field-wrapper-end')
