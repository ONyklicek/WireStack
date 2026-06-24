@php
    use NyonCode\WireCore\WireCoreServiceProvider;

    // Pre-bundled "Teleport + Floating UI" dropdown primitive, cache-busted by mtime.
    $floatingAssetVersion = @filemtime(WireCoreServiceProvider::ASSETS_PATH.'/wire-core-dropdown.js') ?: null;
    $floatingAssetUrl = route('wire-core.asset', ['asset' => 'dropdown']).($floatingAssetVersion ? '?id='.$floatingAssetVersion : '');
@endphp

{{-- Loaded through Livewire's @assets directive so the script registers once and
     also runs when the surface renders inside a Livewire-loaded modal (AJAX),
     where DOM-morphed <script> tags would never execute. --}}
@assets
<script src="{{ $floatingAssetUrl }}"></script>
@endassets
