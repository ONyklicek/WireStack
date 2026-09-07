{{-- Resource list page: optional heading over the resource's table. --}}
<div class="wire-resource-page space-y-4 sm:space-y-6">
    @include('wire-panels::pages.partials.header')

    {{ $this->table }}
</div>
