{{-- One row of the user menu: `<x-wire-admin::menu-item>`.

     The markup moved down to `<x-wire::menu-item>` in wire-core the moment a
     second package outside the shell needed a row — the users module's profile
     link and the auth module's sign-out, neither of which depends on this
     package. This name stays because an application's own layout slot uses it,
     and it delegates rather than repeating: an adapter, not a second copy. --}}
@props([
    'href' => null,
    'icon' => null,
    'type' => 'button',
])

<x-wire::menu-item :href="$href" :icon="$icon" :type="$type" {{ $attributes }}>
    {{ $slot }}
</x-wire::menu-item>
