@php
    use NyonCode\WireForms\Components\OtpInput;
    assert($field instanceof OtpInput);
    $wireModifier = $field->getWireModelModifier();
@endphp

@include('wire-forms::partials.field-wrapper-start')

<div
    x-data="{
        length: {{ $field->getLength() }},
        digits: Array({{ $field->getLength() }}).fill(''),
        separator: @js($field->getSeparator()),

        init() {
            const existing = $wire.get('{{ $field->getWireModelAttribute() }}');
            if (existing) {
                const chars = String(existing).split('').slice(0, this.length);
                chars.forEach((c, i) => { this.digits[i] = c; });
            }
            this.$watch('digits', () => {
                $wire.set('{{ $field->getWireModelAttribute() }}', this.digits.join(''));
            });
        },

        onInput(index, event) {
            const val = event.target.value.replace(/\s/g, '');
            if (val.length > 1) {
                // Handle paste into single box
                const chars = val.split('').slice(0, this.length - index);
                chars.forEach((c, i) => {
                    if (index + i < this.length) this.digits[index + i] = c;
                });
                const next = Math.min(index + chars.length, this.length - 1);
                this.$nextTick(() => this.$refs['digit-' + next]?.focus());
            } else {
                this.digits[index] = val.slice(-1);
                if (val && index < this.length - 1) {
                    this.$nextTick(() => this.$refs['digit-' + (index + 1)]?.focus());
                }
            }
        },

        onKeydown(index, event) {
            if (event.key === 'Backspace') {
                if (!this.digits[index] && index > 0) {
                    this.digits[index - 1] = '';
                    this.$nextTick(() => this.$refs['digit-' + (index - 1)]?.focus());
                } else {
                    this.digits[index] = '';
                }
            } else if (event.key === 'ArrowLeft' && index > 0) {
                this.$refs['digit-' + (index - 1)]?.focus();
            } else if (event.key === 'ArrowRight' && index < this.length - 1) {
                this.$refs['digit-' + (index + 1)]?.focus();
            }
        },

        onPaste(event) {
            event.preventDefault();
            const pasted = event.clipboardData.getData('text').replace(/\s/g, '').slice(0, this.length);
            pasted.split('').forEach((c, i) => { this.digits[i] = c; });
            const next = Math.min(pasted.length, this.length - 1);
            this.$nextTick(() => this.$refs['digit-' + next]?.focus());
        }
    }"
    class="flex items-center gap-2"
>
    @for($i = 0; $i < $field->getLength(); $i++)
        @if($field->getSeparator() && $i > 0 && $i % $field->getSeparator() === 0)
            <span class="text-gray-400 dark:text-gray-500 font-medium select-none">—</span>
        @endif

        <input
            type="{{ $field->isMasked() ? 'password' : 'text' }}"
            x-ref="digit-{{ $i }}"
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
