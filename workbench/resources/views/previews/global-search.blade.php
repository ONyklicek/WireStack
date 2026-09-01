@extends('layouts.preview', [
    'title' => 'Wire Core Global Search',
    'subtitle' => 'The command palette over every registered resource: type, arrow through the groups, Enter to open.',
    'captureOnly' => true,
])

@section('content')
    {{-- The trigger is the application's, not the framework's: a package that
         claimed ⌘K on every page would be taking a key the app may already use.
         This is what an app writes. --}}
    <div
            x-data
            @keydown.window.cmd.k.prevent="$dispatch('open-global-search')"
            @keydown.window.ctrl.k.prevent="$dispatch('open-global-search')"
            class="p-6"
    >
        <button
                type="button"
                x-on:click="$dispatch('open-global-search')"
                data-testid="global-search-trigger"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm text-gray-500 dark:text-gray-300"
        >
            Search invoices… <kbd class="rounded bg-gray-100 dark:bg-gray-600 px-1.5 py-0.5 text-xs">⌘K</kbd>
        </button>

        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
            Try <code>INV</code> or a customer name. Results are grouped by resource.
        </p>
    </div>

    @livewire('wire-global-search')
@endsection
