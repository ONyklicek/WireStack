{{-- ColorColumn cell. Column owns state/sanitisation; this partial owns markup. --}}
@php
    /** @var string $swatch CSS color already validated by Foundation\Support\CssColor */
    /** @var string $displayValue literal value shown next to the swatch ('' when swatch-only) */
    /** @var bool $copyable */
    /** @var string $copyValue */
    /** @var string $copyMessage */

    $out = '<span class="w-4 h-4 rounded ring-1 ring-gray-200 dark:ring-gray-700 shrink-0" style="background-color: '.e($swatch).';"></span>';

    if ($displayValue !== '') {
        $out .= '<span class="font-mono text-gray-700 dark:text-gray-300">'.e($displayValue).'</span>';
    }

    $out = '<span class="inline-flex items-center gap-2">'.$out.'</span>';

    if ($copyable) {
        $out = view('wire-table::tables.columns.partials.copyable', [
            'content' => $out,
            'copyValue' => $copyValue,
            'copyMessage' => $copyMessage,
        ])->render();
    }
@endphp
{!! $out !!}
