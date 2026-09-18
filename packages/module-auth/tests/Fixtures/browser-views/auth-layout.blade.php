{{-- The signed-out frame for the browser tests. The Pest frame next door is a
     title and a slot, which is all markup assertions need; a real browser also
     needs Livewire, Alpine and the controllers `@wireStackScripts` registers,
     or the code boxes are six inert inputs and the test proves nothing. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Wire' }}</title>
    @livewireStyles
</head>
<body data-testid="auth-frame">
    {{ $slot }}
    @wireStackScripts
    @livewireScripts
</body>
</html>
