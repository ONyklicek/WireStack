{{-- Resource create page: optional heading over the resource's form. --}}
<div class="wire-resource-page space-y-4 sm:space-y-6">
    @include('wire-panels::pages.partials.header')

    {{-- The warning before unsaved input is left behind, when the page wants
         it: `wireUnsavedChanges` compares the form's state with what was last
         saved, and holds off while a save is in flight. --}}
    <form
        wire:submit="save"
        class="space-y-4 sm:space-y-6"
        @if($unsavedChanges) x-data="wireUnsavedChanges(@js($unsavedChanges))" @endif
    >
        {{ $this->form }}

        @include('wire-panels::pages.partials.form-actions')
    </form>

    @include('wire-panels::pages.partials.action-modals')
</div>
