{{-- The signed-in user's own page.

     One card per question, and each card below the first is a component of its
     own — a separate form, a separate button, a separate thing that can fail.
     The alternative, one form with three sections, has to explain what happened
     when the middle one does not validate, and "your name saved but your
     password did not" is not a message a profile page should ever produce. --}}
<div class="wire-profile-page mx-auto max-w-3xl space-y-4">
    <x-wire::breadcrumbs :items="$breadcrumbs ?? []" />

    @if ($title)
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $title }}</h1>
    @endif

    <x-wire::section
        :heading="__('wire-module-users::messages.profile_information')"
        :description="__('wire-module-users::messages.profile_information_hint')"
        data-testid="profile-information"
    >
        <form wire:submit="save" class="space-y-4">
            {{ $this->form }}

            <div class="flex items-center gap-2">
                <x-wire::button type="submit" data-testid="profile-save">
                    {{ __('wire-panels::messages.save') }}
                </x-wire::button>
            </div>
        </form>
    </x-wire::section>

    {{-- Keyed by class, not by index: turning a card off in config must not
         hand the next card's state to a component that is not it. --}}
    @foreach ($cards as $card)
        @livewire($card, key('profile-card-'.$card))
    @endforeach
</div>
