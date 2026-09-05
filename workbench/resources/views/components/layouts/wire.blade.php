{{-- The layout for full-page Livewire components registered by
     Route::wireResources().

     Since the shell shipped (ADR 0028) this file is what an application actually
     writes: it names the layout it wants and fills the slots. Everything below
     the slots — the frame, the sidebar over Workspace, the palette, the toasts,
     the asset directives — is `wire-admin`'s.

     Nothing in the package sets `livewire.component_layout`; that line lives in
     WorkbenchServiceProvider, because installing the shell must not be the same
     act as adopting it. --}}
<x-wire-admin::layout :title="$title ?? 'Wire'">
    <x-slot:head>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </x-slot:head>

    <x-slot:brand>Wire Workbench</x-slot:brand>

    {{ $slot }}
</x-wire-admin::layout>
