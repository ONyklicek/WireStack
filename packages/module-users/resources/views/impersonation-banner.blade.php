{{-- The topbar entry point, registered with PageChrome::TOPBAR where
     impersonation is on. Renders nothing outside an impersonation. --}}
@if (\NyonCode\WireModuleUsers\Support\Impersonation::active())
    @livewire(\NyonCode\WireModuleUsers\Livewire\ImpersonationBanner::class)
@endif
