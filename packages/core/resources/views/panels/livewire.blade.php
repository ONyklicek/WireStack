{{-- Single root for Livewire. The floating-assets partial registers the shared
     wire-core bundle (which defines the wireEditableCell Alpine engine the
     editable entries commit through) via Livewire's @assets directive. --}}
<div>
    @include('wire-core::partials.floating-assets')

    {{ $panel }}
</div>
