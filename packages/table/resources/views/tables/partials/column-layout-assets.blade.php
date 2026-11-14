@php
    $columnsAssetFile = \NyonCode\WireTable\WireTableServiceProvider::ASSETS_PATH.'/wire-table-columns.js';
@endphp

{{-- Column widths the user drags and columns pinned to the start
     (wire-table-columns.js). Included only by a table that has either. Through
     @assets so it runs once per document, also when the table first arrives in
     a Livewire response. --}}
@assets
<style>
    .wire-column-resizing, .wire-column-resizing * { cursor: col-resize !important; user-select: none !important; }
</style>
@if(is_file($columnsAssetFile))
@packageScripts('wire-table', 'wire-table-columns.js')
@else
<script>{!! file_get_contents(dirname($columnsAssetFile, 2).'/resources/js/record-columns.js') !!}</script>
@endif
@endassets
