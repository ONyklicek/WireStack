{{-- Standing in for wire-admin's signed-out frame. What the seam needs from a
     frame is a title and a slot; the shell's real one adds the head, the theme
     decision and the card, and is tested in its own package. --}}
<!DOCTYPE html>
<html lang="en">
<head><title>{{ $title ?? 'Wire' }}</title></head>
<body data-testid="auth-frame">{{ $slot }}</body>
</html>
