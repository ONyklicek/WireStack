{{-- The layout for full-page Livewire components registered by
     Route::wireResources().

     Since the shell shipped (ADR 0028) this file is what an application actually
     writes: it names the layout it wants and fills the slots. Everything below
     the slots — the frame, the sidebar over Workspace, the palette, the toasts,
     the asset directives — is `wire-admin`'s.

     Nothing in the package sets `livewire.component_layout`; that line lives in
     WorkbenchServiceProvider, because installing the shell must not be the same
     act as adopting it. --}}
{{-- `?chrome=sidebar` puts the account menu and the collapse button at the foot
     of the menu (verify-admin-placement.mjs); the configuration is the default. --}}
@php($chromeInSidebar = request()->query('chrome') === 'sidebar' ? 'sidebar' : null)
<x-wire-admin::layout :title="$title ?? 'Wire'" :user-placement="$chromeInSidebar" :rail-toggle-placement="$chromeInSidebar">
    <x-slot:head>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </x-slot:head>

    {{-- No brand slot: the logo is `wire-admin.brand` configuration and the
         sidebar draws it. Passing one here too is how the workbench ended up
         with the name twice, once beside its own logo. --}}

    {{-- No user-menu slot, and that is the change worth noticing: the profile
         link and the way out used to be written here by hand, against packages
         this file happened to know the translation keys of. They are now rows
         the modules that own them contribute, through PageChrome::USER_MENU. The
         slot is still there for what an application genuinely owns. --}}

    {{ $slot }}
</x-wire-admin::layout>
