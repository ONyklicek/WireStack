{{-- Resource edit page: optional heading over the resource's form. --}}
<div class="wire-resource-page space-y-4 sm:space-y-6">
    @include('wire-panels::pages.partials.header')

    <form wire:submit="save" class="space-y-4 sm:space-y-6">
        {{ $this->form }}

        @include('wire-panels::pages.partials.form-actions')
    </form>

    @foreach($relationManagers as $manager)
        @livewire($manager, ['ownerRecord' => $ownerRecord], key('rm-'.$loop->index))
    @endforeach
</div>
