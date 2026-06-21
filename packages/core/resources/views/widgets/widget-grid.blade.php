<div class="wire-widget-grid">
    <div class="grid gap-6" style="grid-template-columns: repeat({{ $columns ?? 2 }}, minmax(0, 1fr));">
        @foreach($widgets as $widget)
            @if($widget->isVisible())
                <div class="{{ $widget->getColumnSpan() === 'full' ? 'col-span-full' : ($widget->getColumnSpan() ? 'col-span-' . $widget->getColumnSpan() : '') }}"
                     @if($widget->isPolling()) {!! $widget->getPollingDirective() !!} @endif>
                    {{ $widget }}
                </div>
            @endif
        @endforeach
    </div>
</div>
