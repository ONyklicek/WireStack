@php
    $columns = $columns ?? 2;
    // A polling widget answers with a `wire:partial` region, which is inert
    // without the applier — and a widget-only dashboard has no other surface
    // that would pull the bundle in.
    $anyPolling = collect($widgets)->contains(fn ($widget) => $widget->isPolling());
@endphp

@if($anyPolling)
    @include('wire-core::partials.partial-assets')
@endif

<div class="wire-widget-grid">
    {{-- Responsive: 1 col on mobile, growing toward the configured count. --}}
    <div @class([
        'grid gap-6 grid-cols-1',
        'md:grid-cols-2' => $columns === 2,
        'md:grid-cols-2 xl:grid-cols-3' => $columns === 3,
        'md:grid-cols-2 xl:grid-cols-4' => $columns >= 4,
    ])>
        @foreach($widgets as $widget)
            @if($widget->isVisible())
                <div class="{{ $widget->getColumnSpanClass() }}"
                     @if($widget->isPolling()) {!! $widget->getPollingDirective() !!} @endif>
                    {{-- Only a polling widget needs an anchor, so a dashboard
                         that polls nothing emits exactly the markup it always
                         did. See widget-cell for why the anchor is nested
                         rather than on the polling element itself. --}}
                    @if($widget->isPolling())
                        @include('wire-core::widgets.widget-cell', ['widget' => $widget])
                    @else
                        {{ $widget }}
                    @endif
                </div>
            @endif
        @endforeach
    </div>
</div>
