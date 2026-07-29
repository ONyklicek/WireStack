@php
    // The URL (route + cache-busting mtime) is owned and memoised by the canonical
    // AssetManager, which this package's provider registers the bundle with.
    // Recomputing it here would be a second resolver for the same concern.
    $assetUrl = app(\NyonCode\WireCore\Foundation\Assets\AssetManager::class)->url('wire-table', 'records');
@endphp

{{-- Pre-bundled record-action controller (wireRecordActions). Loaded through
     Livewire's @assets directive so the script registers once and also runs when
     the table renders inside a Livewire-loaded modal, where a DOM-morphed
     <script> tag would never execute. --}}
@assets
<script src="{{ $assetUrl }}"></script>
@endassets
