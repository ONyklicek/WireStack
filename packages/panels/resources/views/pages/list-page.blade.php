{{-- Resource list page: optional heading over the resource's table. --}}
<div class="wire-resource-page space-y-4">
    @if($title)
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $title }}</h1>
    @endif

    {{ $this->table }}
</div>
