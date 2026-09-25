{{-- Resource view page: optional heading over the resource's read-only infolist. --}}
<div class="wire-resource-page space-y-4 sm:space-y-6">
    @include('wire-panels::pages.partials.header')

    @include('wire-panels::pages.partials.page-widgets', ['pageWidgets' => $headerWidgets])

    {{ $infolist }}

    @foreach($relationManagers as $manager)
        @livewire($manager, ['ownerRecord' => $ownerRecord], key('rm-'.$loop->index))
    @endforeach

    @include('wire-panels::pages.partials.page-widgets', ['pageWidgets' => $footerWidgets])

    @include('wire-panels::pages.partials.action-modals')
</div>
