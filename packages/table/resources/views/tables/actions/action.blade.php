@php
    use Illuminate\Database\Eloquent\Model;
    use NyonCode\WireCore\Actions\Action;

    assert($action instanceof Action);
    assert($record instanceof Model);

    $data = $action->getRenderData($record);
@endphp

@if($data['url'])
    <a
            href="{{ $data['url'] }}"
            @if($data['target']) target="{{ $data['target'] }}" @endif
            class="{{ $data['classes'] }}"
            @if($data['tooltip']) title="{{ $data['tooltip'] }}" @endif
            @if($data['shortcutLabel']) data-shortcut="{{ $data['shortcutLabel'] }}"
@endif
@foreach($data['extraAttributes'] as $attr => $val)
    {{ $attr }}="{{ $val }}"
@endforeach
>

    @include('wire-table::tables.actions.partials.button-content', ['data' => $data])
</a>

@else
    <button
            type="button"
            @if($data['hasModal'])
                wire:click{{ $data['wireModifiers'] }}="openActionModal('{{ $data['recordKey'] }}', '{{ $data['actionName'] }}')"
            @else
                wire:click{{ $data['wireModifiers'] }}="executeTableAction('{{ $data['recordKey'] }}', '{{ $data['actionName'] }}')"
            @endif
            class="{{ $data['classes'] }}"
            @if($data['tooltip']) title="{{ $data['tooltip'] }}" @endif
            @if($data['disabled']) disabled @endif
            @if($data['shortcutAlpine'])
                x-on:keydown.{{ $data['shortcutAlpine'] }}.window.prevent="$el.click()"
            @endif
            @if($data['shortcutLabel']) data-shortcut="{{ $data['shortcutLabel'] }}"
@endif
@foreach($data['extraAttributes'] as $attr => $val)
    {{ $attr }}="{{ $val }}"
@endforeach
>
{{-- Loading spinner --}}
@if($data['showLoading'])
    <svg
            wire:loading
            @if($data['hasModal'])
                wire:target="openActionModal('{{ $data['recordKey'] }}', '{{ $data['actionName'] }}')"
            @else
                wire:target="executeTableAction('{{ $data['recordKey'] }}', '{{ $data['actionName'] }}')"
            @endif
            class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"
    >
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
    </svg>
@endif

{{-- Normal content (hidden during loading if loading indicator is enabled) --}}
@if($data['showLoading'])
    <span
            wire:loading.remove
            @if($data['hasModal'])
                wire:target="openActionModal('{{ $data['recordKey'] }}', '{{ $data['actionName'] }}')"
            @else
                wire:target="executeTableAction('{{ $data['recordKey'] }}', '{{ $data['actionName'] }}')"
            @endif
            class="inline-flex items-center gap-1.5"
    >
                @include('wire-table::tables.actions.partials.button-content', ['data' => $data])
            </span>
    @if($data['loadingText'])
        <span
                wire:loading
                @if($data['hasModal'])
                    wire:target="openActionModal('{{ $data['recordKey'] }}', '{{ $data['actionName'] }}')"
                @else
                    wire:target="executeTableAction('{{ $data['recordKey'] }}', '{{ $data['actionName'] }}')"
                @endif
        >{{ $data['loadingText'] }}</span>
    @endif
        @else
            @include('wire-table::tables.actions.partials.button-content', ['data' => $data])
        @endif
    </button>
@endif
