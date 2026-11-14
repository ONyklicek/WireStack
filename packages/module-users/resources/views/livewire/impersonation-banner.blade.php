{{-- Who you are signed in as while impersonating, and the way back. --}}
<div
    role="status"
    data-testid="impersonation-banner" @wireEl('impersonation-banner')
    class="flex min-w-0 items-center gap-2 rounded-lg bg-amber-100 px-3 py-1.5 text-sm text-amber-900 ring-1 ring-amber-300 dark:bg-amber-400/15 dark:text-amber-200 dark:ring-amber-400/30"
>
    {!! icon('outline:eye', 'h-4 w-4 shrink-0') !!}
    <span class="truncate">{{ __('wire-module-users::messages.impersonating', ['name' => $name]) }}</span>
    <button
        type="button"
        wire:click="stop"
        wire:loading.attr="disabled"
        data-testid="impersonation-stop" @wireEl('impersonation-stop')
        class="shrink-0 rounded-md bg-amber-400 px-2 py-0.5 text-xs font-semibold text-gray-950 hover:bg-amber-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500"
    >{{ __('wire-module-users::messages.impersonation_stop') }}</button>
</div>
