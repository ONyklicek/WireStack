<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Wire Preview' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    {{-- A real Echo, only when the workbench was started with a Reverb behind it
         (WIRE_BROADCAST=1). Before Livewire's own scripts, because Livewire reads
         window.Echo.socketId() on every request it makes. --}}
    @if(\Workbench\App\Providers\WorkbenchBroadcastServiceProvider::enabled())
        @php($wireEchoConfig = [
            'key' => \Workbench\App\Providers\WorkbenchBroadcastServiceProvider::APP_KEY,
            'host' => '127.0.0.1',
            'port' => \Workbench\App\Providers\WorkbenchBroadcastServiceProvider::PORT,
        ])
        <script>window.__wireEchoConfig = {!! json_encode($wireEchoConfig) !!};</script>
        <script src="{{ route('workbench.echo-bootstrap') }}"></script>
    @endif
</head>
<body class="min-h-screen bg-slate-100 text-slate-950 antialiased">
    <div class="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(59,130,246,0.08),_transparent_42%),linear-gradient(180deg,_#f8fbff_0%,_#eef4fb_100%)]">
        <div class="mx-auto max-w-7xl px-3 py-4 sm:px-6 sm:py-10">
            @unless($captureOnly ?? false)
                <div class="mb-8 flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-[0.32em] text-sky-700/80">Wire Preview Runtime</p>
                        <h1 class="mt-2 text-3xl font-semibold text-slate-950">{{ $title ?? 'Preview' }}</h1>
                        @isset($subtitle)
                            <p class="mt-2 max-w-3xl text-sm text-slate-600">{{ $subtitle }}</p>
                        @endisset
                    </div>

                    <a
                        href="/previews"
                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
                    >
                        All previews
                    </a>
                </div>
            @endunless

            @yield('content')
        </div>
    </div>

    <x-wire-notifications::toast-container />
    @livewireScripts
</body>
</html>
