{{-- Standing in for the layout `wire-module-auth:install` writes into an
     application's own resources/views. The real one names the shell's frame and
     fills its head slot with @@vite; what matters here is only that it resolves,
     which is the question Frame asks. --}}
<div data-testid="app-auth-frame">{{ $slot }}</div>
