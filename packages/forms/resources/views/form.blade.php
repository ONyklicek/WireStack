<div class="wire-forms-form space-y-6">
    @foreach($components as $component)
        @if($component->isVisible())
            {{ $component }}
        @endif
    @endforeach
</div>
