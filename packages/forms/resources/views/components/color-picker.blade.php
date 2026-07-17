@php

    use NyonCode\WireForms\Components\ColorPicker;

    assert($field instanceof ColorPicker);

    $wireModifier = $field->getWireModelModifier();
    $wireAttr = 'wire:model' . ($wireModifier ? ".$wireModifier" : '');
@endphp

@include('wire-forms::partials.field-wrapper-start')

{{-- format() decides what the *state* holds; <input type="color"> only ever
     speaks hex, so the swatch is driven by a hex mirror and every write is
     converted back to the configured format. --}}
<div
    x-data="{
        format: @js($field->getFormat()),
        color: @entangle($field->getWireModelAttribute()){{ $wireModifier ? '.' . $wireModifier : '' }},

        /** Any supported notation → {r,g,b,a}; unparseable → black, so the swatch never breaks. */
        parse(value) {
            const v = String(value ?? '').trim();
            let m;

            if ((m = v.match(/^#?([0-9a-f]{3})$/i))) {
                const [r, g, b] = m[1].split('').map((c) => parseInt(c + c, 16));
                return { r, g, b, a: 1 };
            }
            if ((m = v.match(/^#?([0-9a-f]{6})$/i))) {
                const n = parseInt(m[1], 16);
                return { r: (n >> 16) & 255, g: (n >> 8) & 255, b: n & 255, a: 1 };
            }
            if ((m = v.match(/^rgba?\(([^)]+)\)$/i))) {
                const p = m[1].split(',').map((x) => parseFloat(x));
                return { r: p[0] | 0, g: p[1] | 0, b: p[2] | 0, a: p[3] ?? 1 };
            }
            if ((m = v.match(/^hsla?\(([^)]+)\)$/i))) {
                const p = m[1].split(',').map((x) => parseFloat(x));
                return { ...this.hslToRgb(p[0], p[1], p[2]), a: p[3] ?? 1 };
            }

            return { r: 0, g: 0, b: 0, a: 1 };
        },

        hslToRgb(h, s, l) {
            s /= 100; l /= 100;
            const k = (n) => (n + h / 30) % 12;
            const a = s * Math.min(l, 1 - l);
            const f = (n) => Math.round(255 * (l - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1)))));
            return { r: f(0), g: f(8), b: f(4) };
        },

        rgbToHsl({ r, g, b }) {
            r /= 255; g /= 255; b /= 255;
            const max = Math.max(r, g, b), min = Math.min(r, g, b);
            const l = (max + min) / 2;
            let h = 0, s = 0;
            if (max !== min) {
                const d = max - min;
                s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
                h = max === r ? ((g - b) / d + (g < b ? 6 : 0)) : max === g ? (b - r) / d + 2 : (r - g) / d + 4;
                h *= 60;
            }
            return { h: Math.round(h), s: Math.round(s * 100), l: Math.round(l * 100) };
        },

        /** {r,g,b,a} → the configured notation. */
        stringify(c) {
            const hex = '#' + [c.r, c.g, c.b].map((n) => n.toString(16).padStart(2, '0')).join('');
            if (this.format === 'rgb') return `rgb(${c.r}, ${c.g}, ${c.b})`;
            if (this.format === 'rgba') return `rgba(${c.r}, ${c.g}, ${c.b}, ${c.a})`;
            if (this.format === 'hsl') {
                const h = this.rgbToHsl(c);
                return `hsl(${h.h}, ${h.s}%, ${h.l}%)`;
            }
            return hex;
        },

        /** The native swatch is hex-only, whatever the state holds. */
        get hex() {
            const c = this.parse(this.color);
            return '#' + [c.r, c.g, c.b].map((n) => n.toString(16).padStart(2, '0')).join('');
        },

        set hex(value) {
            this.color = this.stringify(this.parse(value));
        },

        pick(value) {
            this.color = this.stringify(this.parse(value));
        },
    }"
    class="flex items-center gap-2"
>
    <input
            type="color"
            id="{{ $field->getId() }}"
            data-testid="form-color-{{ $field->getStatePath() }}"
            x-model="hex"
            @if($field->isDisabled()) disabled @endif
            class="h-10 w-14 rounded border-gray-300 p-1 cursor-pointer dark:border-gray-600 transition-colors duration-150"
    />
    <input
            type="text"
            x-model="color"
            data-testid="form-color-{{ $field->getStatePath() }}-hex"
            @if($field->getPlaceholder()) placeholder="{{ $field->getPlaceholder() }}" @endif
            @if($field->isDisabled()) disabled @endif
            @if($field->isReadOnly()) readonly @endif
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-150 dark:bg-gray-800 dark:border-gray-600 dark:text-white text-sm"
    />

    @if($field->getSwatches())
        <div class="mt-2 flex flex-wrap gap-1">
            @foreach($field->getSwatches() as $swatch)
                <button
                    type="button"
                    x-on:click="pick('{{ $swatch }}')" data-testid="form-color-{{ $field->getStatePath() }}-swatch-{{ $swatch }}" aria-label="{{ $swatch }}"
                    @if($field->isDisabled()) disabled @endif
                    class="h-6 w-6 rounded border border-gray-300 dark:border-gray-600 transition-transform hover:scale-110 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    style="background-color: {{ $swatch }}"
                    title="{{ $swatch }}"
                ></button>
            @endforeach
        </div>
    @endif
</div>

@include('wire-forms::partials.field-wrapper-end')
