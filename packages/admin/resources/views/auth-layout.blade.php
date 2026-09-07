{{-- The frame for a page nobody is signed in to, from
     NyonCode\WireAdmin\View\AuthLayout.

     Everything the shell's own layout draws around a page — the menu, the
     palette, the bell — is missing on purpose: none of it means anything before
     a user exists. What stays is the head (so the theme, the assets and the
     interaction layer are the same ones the admin uses) and a card. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>

    @include('wire-admin::partials.theme')

    {{ $head ?? '' }}

    @livewireStyles

    @wireStackScripts
</head>
<body class="flex min-h-full items-center justify-center bg-gray-50 p-4 text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
    <div class="w-full max-w-sm" data-testid="admin-auth">
        <div class="mb-6 text-center text-lg font-semibold" data-testid="admin-auth-brand">
            {{ $brand ?? config('app.name') }}
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="mt-4 text-center text-sm text-gray-500 dark:text-gray-400">{{ $footer }}</div>
        @endisset
    </div>

    <x-wire-notifications::toast-container />

    @livewireScripts
</body>
</html>
