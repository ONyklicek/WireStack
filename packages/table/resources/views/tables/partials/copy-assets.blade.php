@php
    // The URL (route + cache-busting mtime) is owned and memoised by the canonical
    // AssetManager, which this package's provider registers the bundle with. The
    // path is still needed here to choose the branch below, and to read the source
    // for the fallback when the compiled bundle is absent.
    $copyAssetFile = \NyonCode\WireTable\WireTableServiceProvider::ASSETS_PATH.'/wire-table-copy.js';
    $copyAssetUrl = is_file($copyAssetFile)
        ? app(\NyonCode\WireCore\Foundation\Assets\AssetManager::class)->url('wire-table', 'copy')
        : null;
@endphp

{{-- Pre-bundled clipboard controller (record-copy.js), included once by a table
     that has at least one copyable column. Through Livewire's @assets for the same
     reason as the other bundles: the script has to run once, and has to run when
     the table renders inside a Livewire-loaded modal, where a DOM-morphed <script>
     would never execute. --}}
@assets
@if($copyAssetUrl !== null)
<script src="{{ $copyAssetUrl }}"></script>
@else
{{-- Wrapped in an IIFE, which is what esbuild does to the same source for the
     bundle above (`--format=iife`): inlined bare, its top-level `const`/`let`
     would land in the document's global lexical scope, and a second copy would be
     a SyntaxError taking the whole script with it. The listener guard lives on
     `window` precisely so two copies still bind only once. --}}
<script>(function () {
{!! file_get_contents(dirname($copyAssetFile, 2).'/resources/js/record-copy.js') !!}
})();</script>
@endif
@endassets

{{-- The one feedback pill for the page. Rendered here rather than created in JS
     because a consumer's Tailwind scans `resources/views` and `src`, never `dist`
     — a class that existed only in the bundle would never reach their CSS.

     `hidden` and the viewport coordinates are driven by the controller; the
     transition is the browser's, so no Alpine is involved in showing it. --}}
<span
    data-copy-feedback
    hidden
    aria-live="polite"
    class="pointer-events-none fixed z-50 -translate-y-1/2 inline-flex items-center gap-1 rounded bg-white/90 dark:bg-gray-800/90 px-1.5 py-0.5 shadow-sm text-xs font-medium text-emerald-600 dark:text-emerald-400"
>{!! icon('check', 'w-4 h-4', 'text-emerald-500') !!}<span data-copy-feedback-text></span></span>
