{{-- Layout for full-page Livewire components registered by Route::wireResources().

     A full-page component needs one and the framework deliberately does not
     supply it: what a page is wrapped in — the shell, the nav, the assets — is
     the application's, exactly as the routes themselves are. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Wire' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-100 text-slate-950 antialiased">
    <div class="mx-auto max-w-7xl px-4 py-8">
        {{ $slot }}
    </div>

    <x-wire-notifications::toast-container />
    @livewireScripts
</body>
</html>
