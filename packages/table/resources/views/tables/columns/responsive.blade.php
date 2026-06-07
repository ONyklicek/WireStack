{{-- Responsive cell wrapper: separate mobile/desktop markup at a breakpoint. --}}
@php
    /** @var string $breakpoint */
    /** @var string $mobileContent */
    /** @var string $desktopContent */
@endphp
<span class="{{ $breakpoint }}:hidden">{!! $mobileContent !!}</span><span class="hidden {{ $breakpoint }}:inline">{!! $desktopContent !!}</span>
