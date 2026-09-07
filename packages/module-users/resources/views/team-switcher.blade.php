{{-- The topbar entry point, registered with PageChrome::TOPBAR.

     A view rather than the component itself, because the registry holds names
     and the shell renders them: this is the one line that mounts the Livewire
     component, and it renders nothing at all where this installation has no
     teams — a top bar without a switcher, rather than a switcher with one
     option in it. --}}
@if (\NyonCode\WireModuleUsers\Support\Teams::enabled() && count(\NyonCode\WireModuleUsers\Support\Teams::optionsFor()) > 1)
    @livewire(\NyonCode\WireModuleUsers\Livewire\TeamSwitcher::class)
@endif
