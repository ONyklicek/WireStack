@php
    // The URL (route + cache-busting mtime) is owned and memoised by the canonical
    // AssetManager, which the provider registers this bundle with; recomputing it
    // here would be a second resolver for the same concern.
    $assetUrl = app(\NyonCode\WireCore\Foundation\Assets\AssetManager::class)->url('wire-core', 'chart');
@endphp

{{-- Pre-bundled chart controller (wireChart). Loaded through Livewire's @assets
     directive — never @push, which needs a @stack('scripts') no package layout
     renders — so the script is emitted once per page and also runs when the
     widget renders inside a Livewire-loaded modal, where a DOM-morphed <script>
     tag would never execute.

     Fetched per surface rather than from the always-present core bundle because
     charts are an optional, heavy asset class (js-asset-registration.md §3.C);
     the controller inside registers unconditionally, so arriving late is safe.

     Chart.js itself is the consuming app's dependency and is not shipped here. --}}
@assets
<script src="{{ $assetUrl }}"></script>
@endassets
