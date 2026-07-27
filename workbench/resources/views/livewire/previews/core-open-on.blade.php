<div class="mx-auto max-w-3xl space-y-6 p-8">
    <div>
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Event-opened modal shells (openOn)</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Each shell below has no wire:model binding — a window event opens it purely client-side.
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <button
            type="button"
            data-testid="open-on-modal-trigger"
            onclick="window.dispatchEvent(new CustomEvent('demo-open-help'))"
            class="rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-500"
        >Open modal</button>

        <button
            type="button"
            data-testid="open-on-confirm-trigger"
            onclick="window.dispatchEvent(new CustomEvent('demo-open-confirm'))"
            class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:ring-gray-600"
        >Open confirmation</button>

        <button
            type="button"
            data-testid="open-on-panel-trigger"
            onclick="window.dispatchEvent(new CustomEvent('demo-open-panel'))"
            class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:ring-gray-600"
        >Open slide-over</button>

        <button
            type="button"
            data-testid="open-on-server-tick"
            wire:click="tick"
            class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:ring-gray-600"
        >Server roundtrip (<span data-testid="open-on-tick-count">{{ $serverTicks }}</span>)</button>
    </div>

    {!! new \NyonCode\WireCore\Modals\Html\Modal(
        heading: 'Keyboard shortcuts',
        description: 'Opened by a window event, closed client-side.',
        icon: 'outline:question-mark-circle',
        openOn: 'demo-open-help',
        id: 'open-on-modal',
        body: '<div id="open-on-modal-body">Server ticks so far: <span data-testid="open-on-modal-ticks">'.$serverTicks.'</span></div>',
    ) !!}

    {!! new \NyonCode\WireCore\Modals\Html\Confirmation(
        heading: 'Event-opened dialog',
        description: 'Informative confirmation without a wire:model binding.',
        isInformative: true,
        openOn: 'demo-open-confirm',
        id: 'open-on-confirmation',
    ) !!}

    {!! new \NyonCode\WireCore\Modals\Html\SlideOver(
        heading: 'Event-opened panel',
        openOn: 'demo-open-panel',
        id: 'open-on-slideover',
        body: '<div id="open-on-panel-body">Slide-over body</div>',
    ) !!}
</div>
