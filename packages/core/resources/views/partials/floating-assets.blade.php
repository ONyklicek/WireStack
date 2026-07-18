@php
    use NyonCode\WireCore\Foundation\View\FloatingAssets;
@endphp

{{-- Pre-bundled "Teleport + Floating UI" dropdown primitive. The URL (route +
     cache-busting mtime) is resolved once per request by the FloatingAssets owner.

     Loaded through Livewire's @assets directive so the script registers once and
     also runs when the surface renders inside a Livewire-loaded modal (AJAX), where
     DOM-morphed <script> tags would never execute. --}}
@assets
<script src="{{ app(FloatingAssets::class)->url() }}"></script>
@endassets
