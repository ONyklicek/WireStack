@php
    use Illuminate\Database\Eloquent\Model;
    use NyonCode\WireCore\Actions\Contracts\RendersAsButton;

    assert($action instanceof RendersAsButton);
    /** @var Model|null $record */

    // The action resolves its own render state (classes, icon, label, disabled,
    // extra attributes) plus the host-supplied click expression via the click
    // resolver ($click). Core never hardcodes a table/form Livewire method.
    $data = $action->toButtonRenderArray($record ?? null, $click ?? null);

    // An explicit wireClick prop (a custom host handler) overrides the
    // resolver-derived expression; the same expression is reused as the
    // wire:loading target so the spinner gates on the exact click.
    $wireClickAction = $wireClick ?? $data['wireClick'];
    $wireModifiers = $wireClickModifiers ?? $data['wireModifiers'] ?? '';
@endphp

@if($action->isHidden($record ?? null))
    {{-- Hidden actions render nothing. --}}
@elseif($data['url'])
    <a
            href="{{ $data['url'] }}"
            @if($data['target']) target="{{ $data['target'] }}" @endif
            class="{{ $data['classes'] }}"
            data-testid="action-{{ $action->getName() }}"
            @if($action->getLabel($record ?? null)) aria-label="{{ $action->getLabel($record ?? null) }}" @endif
            @if($data['tooltip']) title="{{ $data['tooltip'] }}" @endif
            @if($data['shortcutLabel']) data-shortcut="{{ $data['shortcutLabel'] }}"@endif
            @foreach($data['extraAttributes'] as $attr => $val)
                {{ $attr }}="{{ $val }}"
            @endforeach
    >
        @include('wire-core::actions.partials.button-content', ['data' => $data])
    </a>
@else
    <button
            type="button"
            wire:click{{ $wireModifiers }}="{{ $wireClickAction }}"
            class="{{ $data['classes'] }}"
            data-testid="action-{{ $action->getName() }}"
            @if($action->getLabel($record ?? null)) aria-label="{{ $action->getLabel($record ?? null) }}" @endif
            @if($data['tooltip']) title="{{ $data['tooltip'] }}" @endif
            @if($data['disabled']) disabled @endif
            @if($data['shortcutAlpine'])
                x-on:keydown.{{ $data['shortcutAlpine'] }}.window.prevent="$el.click()"
            @endif
            @if($data['shortcutLabel']) data-shortcut="{{ $data['shortcutLabel'] }}"@endif
            @foreach($data['extraAttributes'] as $attr => $val)
                {{ $attr }}="{{ $val }}"
            @endforeach
    >
        @if($data['showLoading'])
            {{-- Loading spinner. The wrapping span carries the per-record wire:target
                 so the spinner markup itself stays record-invariant and is resolved
                 once per request (see Foundation\View\Primitives). --}}
            <span wire:loading wire:target="{{ $wireClickAction }}">{!! $data['spinnerHtml'] !!}</span>
            <span wire:loading.remove wire:target="{{ $wireClickAction }}" class="inline-flex items-center gap-1.5">
                @include('wire-core::actions.partials.button-content', ['data' => $data])
            </span>
            @if($data['loadingText'])
                <span wire:loading wire:target="{{ $wireClickAction }}">{{ $data['loadingText'] }}</span>
            @endif
        @else
            @include('wire-core::actions.partials.button-content', ['data' => $data])
        @endif
    </button>
@endif
