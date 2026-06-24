@php
    use NyonCode\WireForms\Components\Display\Alert;
    assert($field instanceof Alert);
@endphp

<div
        @if($field->isDismissible()) x-data="{ show: true }" x-show="show" x-transition @endif
class="rounded-md border p-4 {{ $field->getColorClasses() }}"
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
