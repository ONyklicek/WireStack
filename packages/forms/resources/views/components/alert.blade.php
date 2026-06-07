@php
    use NyonCode\WireForms\Components\Display\Alert;
    assert($field instanceof Alert);

    $colorClasses = match($field->getColor()) {
        'success' => 'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-300',
        'warning' => 'bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300',
        'danger' => 'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300',
        default => 'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-900/20 dark:border-blue-800 dark:text-blue-300',
    };
@endphp

<div
        @if($field->isDismissible()) x-data="{ show: true }" x-show="show" x-transition @endif
class="rounded-md border p-4 {{ $colorClasses }}"
>
    <div class="flex">
        @if($field->getIcon())
            <div class="shrink-0 mr-3">
                <x-wire::icon :name="$field->getIcon()" class="w-5 h-5"/>
            </div>
        @endif
        <div class="flex-1">
            @if($field->getTitle())
                <h3 class="text-sm font-medium">{{ $field->getTitle() }}</h3>
            @endif
            @if($field->getContent())
                <div @class(['text-sm', 'mt-1' => $field->getTitle()])>
                    {{ $field->getContent() }}
                </div>
            @endif
        </div>
        @if($field->isDismissible())
            <button type="button" @click="show = false"
                    class="ml-3 shrink-0 -mt-1 -mr-1 p-1 rounded-md hover:opacity-75 focus:outline-none">
                <span class="sr-only">Dismiss</span>
                <x-wire::icon name="outline:x-mark" class="w-4 h-4"/>
            </button>
        @endif
    </div>
</div>
