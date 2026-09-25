{{-- A page of the application's own: the shared heading over whatever view the
     page names. The content inherits this scope, so the component's public
     properties and `$this` reach it as they reach any Livewire view. --}}
<div class="wire-resource-page space-y-4 sm:space-y-6">
    @include('wire-panels::pages.partials.header')

    @include('wire-panels::pages.partials.page-widgets', ['pageWidgets' => $headerWidgets])

    @include($contentView)

    @include('wire-panels::pages.partials.page-widgets', ['pageWidgets' => $footerWidgets])

    @include('wire-panels::pages.partials.action-modals')
</div>
