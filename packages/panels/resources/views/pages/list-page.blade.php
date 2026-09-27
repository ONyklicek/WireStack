{{-- Resource list page: optional heading over the resource's table. --}}
<div class="wire-resource-page space-y-4 sm:space-y-6" @if(($clusterNavigation ?? null)?->frame()) data-cluster-frame="{{ $clusterNavigation->frame() }}" @endif>
    @include('wire-panels::pages.partials.header')

    @include('wire-panels::pages.partials.page-widgets', ['pageWidgets' => $headerWidgets])

    @include('wire-panels::pages.partials.list-tabs')

    {{ $this->table }}

    @include('wire-panels::pages.partials.page-widgets', ['pageWidgets' => $footerWidgets])
</div>
