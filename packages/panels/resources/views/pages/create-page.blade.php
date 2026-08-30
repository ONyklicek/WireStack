{{-- Resource create page: optional heading over the resource's form. --}}
<div class="wire-resource-page space-y-4">
    @if($title)
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $title }}</h1>
    @endif

    <form wire:submit="save" class="space-y-4">
        {{ $this->form }}

        <div class="flex items-center gap-2">
            <button type="submit" class="rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white">
                {{ __('wire-panels::messages.save') }}
            </button>
        </div>
    </form>
</div>
