{{-- Resource view page: optional heading over the resource's read-only infolist. --}}
<div class="wire-resource-page space-y-4">
    @if($title)
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $title }}</h1>
    @endif

    {{ $infolist }}

    @foreach($relationManagers as $manager)
        @livewire($manager, ['ownerRecord' => $ownerRecord], key('rm-'.$loop->index))
    @endforeach
</div>
