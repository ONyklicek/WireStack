{{-- The layout the signed-out screens render in.

     What `php artisan wire-module-auth:install` writes into an application, kept
     here by hand because the workbench has no installer run. It is the reason
     the file exists at all: the shell's frame takes the stylesheet from a `head`
     slot — a package cannot know an application's Vite entry names — so without
     this the login page would carry the framework's markup and none of these
     styles, including the `[x-cloak]` rule the two-factor challenge needs. --}}
@props(['title' => null])

<x-wire-admin::auth-layout :title="$title ?? config('app.name')">
    <x-slot:head>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </x-slot:head>

    <x-slot:brand>{{ config('app.name') }}</x-slot:brand>

    {{ $slot }}
</x-wire-admin::auth-layout>
