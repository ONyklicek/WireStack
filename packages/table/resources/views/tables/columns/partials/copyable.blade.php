{{-- Copy affordance around an already-rendered cell. --}}
{{-- Variables: $content, $copyValue, $copyMessage --}}
@php
    /** @var string $content already-rendered cell markup, spliced in verbatim */
    /** @var mixed $copyValue the value the button puts on the clipboard */
    /** @var string|null $copyMessage what to announce afterwards */

    // Built as a string rather than as markup, for the same reason the text and
    // color partials next door are: it is the only way to emit a cell with no
    // whitespace between its tags. Every such run is one text node the Livewire
    // morph walks on every commit, and this partial can render 500 times on a page.
    //
    // The behaviour lives in the `record-copy` bundle, bound once for the document
    // and reached through `data-copy`. It used to be an inline Alpine component per
    // cell — 2042 bytes and 11 whitespace nodes each; the button below is ~180.
    $label = $copyMessage ?? __('wire-table::messages.copy');

    $out = '<span class="inline-flex items-center gap-1.5 group">'
        .$content
        .'<button type="button"'
        .' data-copy="'.e((string) $copyValue).'"'
        .' data-copy-message="'.e((string) $copyMessage).'"'
        .' data-testid="cell-copy"'
        .' aria-label="'.e($label).'"'
        .' title="'.e(__('wire-table::messages.copy')).'"'
        .' class="opacity-0 group-hover:opacity-100 transition-opacity p-0.5 rounded hover:bg-gray-100 dark:hover:bg-gray-700">'
        .icon('clipboard-document', 'w-4 h-4', 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300')
        .'</button></span>';
@endphp
{!! $out !!}
