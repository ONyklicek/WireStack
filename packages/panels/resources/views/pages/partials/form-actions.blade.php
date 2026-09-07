{{-- The one control a create or edit page ends with.

     `<x-wire::button>` rather than hand-written utilities: the submit used to
     carry its own `bg-primary-600 px-4 py-2` and so was the one button in the
     framework that did not move when the canonical colour and size resolvers
     did — no focus ring, no disabled state, no hover.

     Full width on a phone, where a 90px target floating in an empty row is a
     button you aim at; its natural width from `sm` up. --}}
<div class="flex flex-col gap-2 sm:flex-row sm:items-center">
    <x-wire::button type="submit" size="md" class="w-full justify-center sm:w-auto">
        {{ __('wire-panels::messages.save') }}
    </x-wire::button>
</div>
