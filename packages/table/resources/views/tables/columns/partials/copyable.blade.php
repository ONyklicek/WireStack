{{-- Copy affordance around an already-rendered cell. --}}
{{-- Variables: $content, $copyValue, $copyMessage --}}
@php
    /** @var string $content already-rendered cell markup, spliced in verbatim */
    /** @var mixed $copyValue the value the button puts on the clipboard */
    /** @var string|null $copyMessage what to announce afterwards */

    // The button itself is core's — the same affordance an infolist entry uses, so
    // it has one owner ({@see NyonCode\WireCore\Foundation\View\CopyButton}) rather
    // than a copy per package. It is compiled once per shape there and spliced, so
    // this stays what it was: no view render per cell.
    //
    // The table still passes its own strings. `wire-table::messages.copy` is what a
    // consumer has already translated and what this cell has always announced;
    // reaching for core's key instead would change the page to save a parameter.
    $out = '<span class="inline-flex items-center gap-1.5 group">'
        .$content
        .app(\NyonCode\WireCore\Foundation\View\CopyButton::class)->render(
            value: (string) $copyValue,
            message: (string) $copyMessage,
            label: $copyMessage ?? __('wire-table::messages.copy'),
            title: __('wire-table::messages.copy'),
            testId: 'cell-copy',
        )
        .'</span>';
@endphp
{!! $out !!}
