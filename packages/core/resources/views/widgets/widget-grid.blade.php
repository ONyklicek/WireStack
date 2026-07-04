<div class="wire-widget-grid">
    @php $columns = $columns ?? 2; @endphp
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
                    {{ $widget }}
                </div>
            @endif
        @endforeach
    </div>
</div>
