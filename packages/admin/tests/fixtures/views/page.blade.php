<x-wire-admin::layout :title="$title ?? null" :linked-only="$linkedOnly ?? false">
    <x-slot:head><link rel="stylesheet" href="/app.css"></x-slot:head>
    <x-slot:brand>Acme</x-slot:brand>
    <x-slot:topbar><span data-testid="al-topbar">Topbar</span></x-slot:topbar>
    <x-slot:user><span data-testid="al-user">Jane</span></x-slot:user>

    Records
</x-wire-admin::layout>
