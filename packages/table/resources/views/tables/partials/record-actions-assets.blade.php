@php
    // Cache-bust by the bundle's mtime so a rebuild is picked up without a manual
    // version bump; falls back to no query string if the file is not present yet.
    $assetFile = \NyonCode\WireTable\WireTableServiceProvider::ASSETS_PATH.'/wire-table-records.js';
    $assetVersion = is_file($assetFile) ? (string) filemtime($assetFile) : null;
    $assetUrl = route('wire-table.asset', ['asset' => 'records']).($assetVersion ? '?id='.$assetVersion : '');
@endphp

{{-- Pre-bundled record-action controller (wireRecordActions). Loaded through
     Livewire's @assets directive so the script registers once and also runs when
     the table renders inside a Livewire-loaded modal, where a DOM-morphed
     <script> tag would never execute. --}}
@assets
<script src="{{ $assetUrl }}"></script>
@endassets
