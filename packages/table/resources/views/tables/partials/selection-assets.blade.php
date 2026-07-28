@php
    // Cache-bust by the bundle's mtime so a rebuild is picked up without a manual
    // version bump.
    $selectionAssetFile = \NyonCode\WireTable\WireTableServiceProvider::ASSETS_PATH.'/wire-table-selection.js';
    $selectionAssetVersion = is_file($selectionAssetFile) ? (string) filemtime($selectionAssetFile) : null;
@endphp

{{-- Pre-bundled selection component (wireRecordSelection). Loaded through
     Livewire's @assets directive so the script registers once and also runs when
     the table renders inside a Livewire-loaded modal, where a DOM-morphed
     <script> tag would never execute. --}}
@assets
@if($selectionAssetVersion !== null)
<script src="{{ route('wire-table.asset', ['asset' => 'selection']).'?id='.$selectionAssetVersion }}"></script>
@else
{{-- The x-data on the table wrapper references the factory either way, and the
     wrapper owns search, filters, the bulk bar, pagination, the mobile cards
     and the modal hosts — a dangling reference would kill Alpine for all of it,
     silently. The source is import-free on purpose so it can stand in verbatim
     when the compiled bundle is missing. --}}
<script>{!! file_get_contents(dirname($selectionAssetFile, 2).'/resources/js/record-selection.js') !!}</script>
@endif
@endassets
