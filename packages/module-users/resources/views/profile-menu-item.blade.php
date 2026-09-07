{{-- The link to the signed-in user's own page, in the user menu.

     The module owns the profile page, so the module contributes the link to it.
     Before the USER_MENU region existed this lived in a layout slot every
     application wrote by hand — reaching into this package's translations from a
     file this package cannot see.

     The URL is asked for rather than built: this package owns the page, not the
     routing convention, so an application that routed nothing gets no link
     rather than a link to nowhere. Zones give the same question several answers,
     which is the other reason it is asked at render. --}}
@php($wmuProfileUrl = app(NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls::class)->urlFor('users', 'profile'))

@if ($wmuProfileUrl)
    <x-wire::menu-item
        :href="$wmuProfileUrl"
        icon="outline:user-circle"
        wire:navigate
        data-testid="admin-profile-link"
    >
        {{ __('wire-module-users::messages.profile') }}
    </x-wire::menu-item>
@endif
