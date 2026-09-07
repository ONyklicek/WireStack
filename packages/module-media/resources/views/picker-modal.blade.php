{{-- The library as a chooser, mounted once per page by the shell.

     Registered into NyonCode\WireCore\Foundation\View\PageChrome by this
     module's service provider, which is what lets a package the shell has never
     heard of put a modal on every screen. Nothing here is Livewire until it is
     opened: the manager inside is behind `x-if`, so a page that never opens the
     picker pays for a few elements rather than for a component that queries the
     library on every render.

     ## The contract

     Anything on the page opens it by dispatching a cancelable DOM event:

         const event = new CustomEvent('wire-media-picker:open', {
             cancelable: true,
             detail: { token: 'anything', multiple: false, accepts: 'image/' },
         });
         window.dispatchEvent(event);

         if (! event.defaultPrevented) { /* nobody is listening — fall back */ }

     **Cancelable, and that is the whole design.** The caller cannot know whether
     the media module is installed — the rich text editor lives in `wire-forms`,
     which sits below this package and must never require it. So the listener
     *claims* the event by calling `preventDefault()`, and a caller that sees it
     un-defaulted knows to do whatever it did before. One `if`, no registry, no
     configuration, and no dependency in the wrong direction.

     The answer comes back the same way, on `wire-media-picker:picked`, carrying
     the token it was opened with so two pickers on one page cannot cross. --}}
<div
    x-data="{
        open: false,
        token: null,
        multiple: false,
        accepts: '',
        mounted: false,

        opened(event) {
            // Claimed before anything else, so a caller checking
            // `defaultPrevented` synchronously gets the right answer.
            event.preventDefault();

            this.token = event.detail?.token ?? null;
            this.multiple = !! event.detail?.multiple;
            this.accepts = event.detail?.accepts ?? '';
            this.mounted = true;
            this.open = true;

            this.configure();
        },

        /**
         * Tell the component inside what is being asked of it.
         *
         * Addressed directly rather than dispatched, and retried until it is
         * there. The first version fired a Livewire event on `$nextTick` and was
         * wrong for a reason worth writing down: the component is built by the
         * `x-if` above, and Livewire finishes initialising it a frame or two
         * later — so the event went out before anything was listening and the
         * picker opened with the previous caller's terms. It looked like it
         * worked, because the previous caller was usually asking for the same
         * thing.
         */
        configure(attempt = 0) {
            const host = this.$el.querySelector('[wire\\:id]');
            const wire = host && window.Livewire?.find(host.getAttribute('wire:id'));

            if (wire) {
                wire.call('configurePicker', this.multiple, this.accepts);

                return;
            }

            // ~1s at 60fps, then give up: a modal that polls forever is a modal
            // that keeps a broken page busy.
            if (attempt < 60) requestAnimationFrame(() => this.configure(attempt + 1));
        },

        picked(event) {
            window.dispatchEvent(new CustomEvent('wire-media-picker:picked', {
                detail: { token: this.token, files: event.detail?.files ?? event.detail?.[0]?.files ?? [] },
            }));

            this.open = false;
        },
    }"
    x-on:wire-media-picker:open.window="opened($event)"
    x-on:wire-media-picked.window="picked($event)"
    x-on:keydown.escape.window="open = false"
    data-testid="media-picker"
>
    <div
        x-show="open"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/60 p-4 sm:p-8"
        x-on:click.self="open = false"
    >
        {{-- Narrower than the library screen, and centred rather than pinned to
             the top edge: this is a question with an answer in it, not a place
             to spend the afternoon. 5xl was the library's width inherited by
             something that shows a folder tree and a handful of tiles. --}}
        <div class="max-h-full w-full max-w-3xl overflow-y-auto rounded-2xl bg-gray-50 p-4 shadow-2xl dark:bg-gray-950">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold">{{ __('wire-module-media::messages.pick_title') }}</h2>
                <button
                    type="button"
                    x-on:click="open = false"
                    data-testid="media-picker-close"
                    class="rounded-lg p-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                >{!! icon('outline:x-mark', 'h-5 w-5') !!}</button>
            </div>

            {{-- Built on first open and kept afterwards. Rebuilding it on every
                 open would throw away the folder somebody had navigated to, and
                 picking three images from one folder is the common case. --}}
            <template x-if="mounted">
                <div>
                    {{-- Keyed, so the modal keeps one component across a wire:navigate rather
                         than mounting a second one beside the first. --}}
                    @livewire('wire-media-picker', [], 'wire-media-picker')
                </div>
            </template>
        </div>
    </div>
</div>
