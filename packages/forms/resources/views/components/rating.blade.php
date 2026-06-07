@php
    use NyonCode\WireForms\Components\Rating;
    assert($field instanceof Rating);
    $wireModifier = $field->getWireModelModifier();
    // Bright star scale (-500/-400); hues kept in sync with the canonical
    // Foundation palette (success = emerald, not green).
    $colorClasses = match($field->getColor()) {
        'primary' => 'text-primary-500',
        'success'  => 'text-emerald-500',
        'danger'   => 'text-red-500',
        default    => 'text-amber-400',
    };
@endphp

@include('wire-forms::partials.field-wrapper-start')

<div
    x-data="{
        rating: @entangle($field->getWireModelAttribute()){{ $wireModifier ? '.' . $wireModifier : '' }},
        hovered: 0,
        allowHalf: @js($field->isAllowHalf()),
        clearable: @js($field->isClearable()),
        disabled: @js($field->isDisabled()),

        setRating(val) {
            if (this.disabled) return;
            if (this.clearable && this.rating === val) {
                this.rating = 0;
            } else {
                this.rating = val;
            }
        },

        setHalf(index, event) {
            if (!this.allowHalf || this.disabled) return;
            const rect = event.currentTarget.getBoundingClientRect();
            const half = event.clientX - rect.left < rect.width / 2;
            this.hovered = half ? index - 0.5 : index;
        },

        clickStar(index, event) {
            if (this.allowHalf) {
                const rect = event.currentTarget.getBoundingClientRect();
                const half = event.clientX - rect.left < rect.width / 2;
                this.setRating(half ? index - 0.5 : index);
            } else {
                this.setRating(index);
            }
        },

        isFilled(index) {
            const active = this.hovered || this.rating;
            return index <= active;
        },

        isHalfFilled(index) {
            const active = this.hovered || this.rating;
            return active >= index - 0.5 && active < index;
        }
    }"
    class="inline-flex items-center gap-0.5"
>
    @for($i = 1; $i <= $field->getMax(); $i++)
        <button
            type="button"
            @click="clickStar({{ $i }}, $event)"
            @mousemove="setHalf({{ $i }}, $event)"
            @mouseleave="hovered = 0"
            :disabled="disabled"
            @class([
                'relative focus:outline-none transition-transform',
                'hover:scale-110' => !$field->isDisabled(),
                'cursor-not-allowed opacity-50' => $field->isDisabled(),
            ])
        >
            {{-- Full star --}}
            <svg
                x-show="isFilled({{ $i }})"
                class="w-7 h-7 {{ $colorClasses }}" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
            </svg>
            {{-- Half star --}}
            <svg
                x-show="isHalfFilled({{ $i }})"
                class="w-7 h-7 {{ $colorClasses }}" viewBox="0 0 24 24" fill="currentColor">
                <defs><clipPath id="half-{{ $i }}"><rect x="0" y="0" width="12" height="24"/></clipPath></defs>
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="currentColor" clip-path="url(#half-{{ $i }})"/>
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="none" stroke="currentColor" stroke-width="1.5"/>
            </svg>
            {{-- Empty star --}}
            <svg
                x-show="!isFilled({{ $i }}) && !isHalfFilled({{ $i }})"
                class="w-7 h-7 text-gray-300 dark:text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
            </svg>
        </button>
    @endfor

    <span x-show="rating > 0" x-text="rating" class="ml-2 text-sm text-gray-500 dark:text-gray-400 tabular-nums"></span>
</div>

@include('wire-forms::partials.field-wrapper-end')
