{{-- The face in the corner: <x-wire-admin::avatar :user="auth()->user()" class="h-8 w-8" />

     Asked through the contract, never through a package: the shell must draw an
     avatar without knowing whether this application stores a path in a column,
     resolves Gravatar, or has no pictures at all. A user model that implements
     `NyonCode\WireCore\Foundation\Contracts\HasAvatar` gets its picture; every
     other user gets the initial, which is what the top bar always drew.

     Null from `getAvatarUrl()` is a real answer, not a failure — a person who
     has uploaded nothing is the common case, not the broken one. --}}
@props(['user' => null, 'name' => null])

@php
    $avatarUrl = $user instanceof \NyonCode\WireCore\Foundation\Contracts\HasAvatar
        ? $user->getAvatarUrl()
        : null;

    // Whatever they are called, then their address, then a question mark: a row
    // with neither is still a row somebody has to look at.
    $avatarLabel = (string) ($name
        ?? (is_object($user) ? ($user->name ?? $user->email ?? '') : '')
    );

    $avatarInitial = $avatarLabel === '' ? '?' : mb_strtoupper(mb_substr(trim($avatarLabel), 0, 1));
@endphp

@if ($avatarUrl !== null && $avatarUrl !== '')
    <img
        src="{{ $avatarUrl }}"
        alt="{{ $avatarLabel }}"
        data-testid="admin-avatar"
        {{ $attributes->class(['shrink-0 rounded-full object-cover']) }}
    >
@else
    <span
        data-testid="admin-avatar"
        aria-hidden="true"
        {{ $attributes->class(['bg-primary-600 inline-flex shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white']) }}
    >{{ $avatarInitial }}</span>
@endif
