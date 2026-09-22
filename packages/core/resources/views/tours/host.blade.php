{{-- The guided walkthrough, mounted once per page by whatever draws the chrome.

     Registered into NyonCode\WireCore\Foundation\View\PageChrome by this
     package's service provider. An application that has registered no tour
     renders nothing here at all — see the `@if` below, which is where that is
     decided, and why it is decided there rather than at boot.

     ## No JavaScript ships for this

     Everything below is plain Alpine over data PHP resolved. The two things a
     tour popup is really made of are already on every page inside
     `wire-core-dropdown.js`, which is a declared bundle:

       $float(reference, floating, config)  — Floating UI with flip/shift/size,
                                              a z-index resolver that clears
                                              modals, and re-assertion after a
                                              Livewire morph strips inline style

       modals.partials.focus-trap           — expression-only, no bundle at all

     So this feature adds no entry to `build:core-assets`, no committed dist
     file and no bytes to any <head>.

     ## What it will not do

     **Nothing runs below the sheet breakpoint.** Not a bottom sheet, not a
     cut-out — the tour does not start, and one already running ends if the
     viewport crosses down. A sheet cannot point at anything, and on a phone the
     sidebar is a drawer and the toolbar has collapsed, so the elements a step
     names are not on the page to point at. A tour that silently became a stack
     of captions is worse than no tour.

     **A step whose element is missing or hidden is skipped, never fatal.** An
     application may have hidden a control, this screen may legitimately not
     have it, or it may be rendered and waiting behind an `x-show`. The
     worst outcome of an upgrade is therefore a tour that got shorter. That is
     also why a step names an element *hook* and not a selector: a typo would
     otherwise be indistinguishable from an absent element, which is why
     TourStep validates the name at definition time instead.

     ## Traps this file is written around

     - `$refs` are NOT populated when a parent's `x-init` runs, and the throw
       takes the whole region with it. Every first read of `$refs.panel` is
       inside `$nextTick`.
     - Single quotes throughout the Alpine expressions: a double quote inside an
       attribute truncates it, and this file would stop working silently.
     - Blade directives sit on their own lines inside an opening tag. A
       directive butted against a word character does not compile. --}}
{{-- Nothing to run is the overwhelmingly common answer — no tour registered, or
     none that claims this screen, or one this person already acknowledged — and
     it costs an empty `foreach` over the registry and this `@if`. The chrome
     itself is registered unconditionally, because provider order is composer's
     discovery order and an application registering its tours in its own
     provider may boot after this package. --}}
@if ($tour !== null)
@php($payload = $tourHost->payload($tour))
{{-- The round trip, outside the `wire:ignore` below. A component cannot both be
     ignored by Livewire and call it, and the chrome needs the ignore to keep the
     inline styles Floating UI writes onto the panel. --}}
@livewire('wire-tour-acknowledgement', ['tourId' => $tour->getId()], key('wire-tour-'.$tour->getId()))
<div
    x-data="{
        tourId: @js($tour->getId()),
        steps: @js($payload['steps']),
        breakpoint: @js($payload['breakpoint']),
        progressTemplate: @js(__('wire-core::messages.tour_progress', ['current' => '{c}', 'total' => '{t}'])),

        {{-- `plan` is the indices of the steps whose element was actually on the
             page when the tour started, and `cursor` walks it. The progress
             counter reads off the plan rather than off `steps`, which is the
             whole reason the plan exists: a tour with a step this screen does
             not have would otherwise count it and then skip it, and somebody
             would watch "1 of 3" become "3 of 3". A tour that got shorter should
             say it is shorter. --}}
        plan: [],
        cursor: 0,
        active: false,
        detach: null,
        rect: { top: 0, left: 0, width: 0, height: 0 },
        raised: null,

        get step() { return this.steps[this.plan[this.cursor]] || {}; },
        get isLast() { return this.cursor >= this.plan.length - 1; },
        get progress() {
            return this.progressTemplate
                .replace('{c}', this.cursor + 1)
                .replace('{t}', this.plan.length);
        },

        {{-- The one rule that keeps a tour off a phone. Re-read rather than
             cached: a tablet rotates, and a desktop window gets dragged narrow. --}}
        tooNarrow() {
            return window.matchMedia('(max-width: ' + this.breakpoint + 'px)').matches;
        },

        {{-- On the page is not enough: it has to be *showing*. A great deal of
             this framework's markup is rendered and hidden with `x-show` — the
             bulk-action bar until rows are selected, a dropdown until it opens —
             and `querySelector` finds those just as happily. A step pointed at
             one would pin the panel to a box of zero size in the corner.
             `getClientRects()` is empty for anything `display: none` or
             detached, and it is the test the focus trap already uses for the
             same question. --}}
        shown(selector) {
            const el = document.querySelector(selector);
            return el && el.getClientRects().length > 0 ? el : null;
        },

        at(cursor) {
            const step = this.steps[this.plan[cursor]];
            return step ? this.shown(step.selector) : null;
        },

        {{-- The first planned step still on the page, walking in `direction`.
             Null when there is none left, which ends the tour. The plan is built
             once, but the page keeps moving underneath it — a row can be
             filtered away between one step and the next — so this re-checks
             rather than trusting the plan it walks. --}}
        seek(from, direction) {
            for (let i = from; i >= 0 && i < this.plan.length; i += direction) {
                if (this.at(i)) { return i; }
            }
            return null;
        },

        start() {
            if (this.tooNarrow()) { return; }

            this.plan = this.steps
                .map((step, i) => (this.shown(step.selector) ? i : null))
                .filter((i) => i !== null);

            if (this.plan.length === 0) { return; }

            this.cursor = 0;
            this.active = true;
            this.$nextTick(() => this.place());
        },

        place() {
            this.release();
            const target = this.at(this.cursor);
            {{-- Gone since the plan was built: walk on rather than showing a
                 panel pinned to nothing. --}}
            if (! target) { this.move(1); return; }

            {{-- Raised above the backdrop rather than the backdrop being cut
                 out: one z-index instead of an SVG mask that would have to
                 track the element's box anyway. --}}
            this.raised = { el: target, position: target.style.position, zIndex: target.style.zIndex };
            if (getComputedStyle(target).position === 'static') { target.style.position = 'relative'; }
            target.style.zIndex = '61';

            this.track();
            this.detach = this.$float(target, this.$refs.panel, { placement: this.step.placement || 'bottom', offset: 12 });
        },

        {{-- The ring is our own element over the target's box, so it carries a
             hook name and an application can restyle it. The target's own
             markup is never written to beyond the z-index above, which is
             restored. --}}
        track() {
            const target = this.at(this.cursor);
            if (! target) { return; }
            const box = target.getBoundingClientRect();
            this.rect = { top: box.top, left: box.left, width: box.width, height: box.height };
        },

        release() {
            if (this.detach) { this.detach(); this.detach = null; }
            if (this.raised) {
                this.raised.el.style.position = this.raised.position;
                this.raised.el.style.zIndex = this.raised.zIndex;
                this.raised = null;
            }
        },

        move(direction) {
            const next = this.seek(this.cursor + direction, direction);
            if (next === null) { this.done(false); return; }
            this.cursor = next;
            this.$nextTick(() => this.place());
        },

        next() { this.isLast ? this.done(false) : this.move(1); },
        back() { this.move(-1); },

        {{-- Finishing and skipping are the same call. Somebody who skipped has
             decided about this tour as firmly as somebody who finished it, and a
             tour that comes back after a skip is the worst version of this. The
             event is what step 3 listens for to record it. --}}
        done(skipped) {
            this.release();
            this.active = false;
            window.dispatchEvent(new CustomEvent('wire-tour:done', {
                detail: { id: this.tourId, skipped: skipped },
            }));
        },
    }"
    x-init="$nextTick(() => start())"
    x-on:keydown.escape.window="active && done(true)"
    x-on:scroll.window.passive="active && track()"
    x-on:resize.window="active && (tooNarrow() ? done(true) : track())"
    wire:ignore
>
    <div
        @wireEl('tour-backdrop')
        x-show="active"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-[60] bg-gray-900/50 dark:bg-gray-950/70"
        aria-hidden="true"
    ></div>

    <div
        @wireEl('tour-highlight')
        x-show="active"
        x-cloak
        x-bind:style="`top:${rect.top - 4}px;left:${rect.left - 4}px;width:${rect.width + 8}px;height:${rect.height + 8}px`"
        class="pointer-events-none fixed z-[62] rounded-lg ring-2 ring-primary-500 ring-offset-2 ring-offset-transparent transition-all duration-150"
        aria-hidden="true"
    ></div>

    <div
        @wireEl('tour-panel')
        x-ref="panel"
        x-show="active"
        x-cloak
        @include('wire-core::modals.partials.focus-trap', ['openExpression' => 'active'])
        class="absolute left-0 top-0 z-[63] w-72 max-w-[calc(100vw-2rem)] rounded-xl border border-gray-200 bg-white p-4 shadow-xl dark:border-gray-700 dark:bg-gray-800"
        role="dialog"
        aria-label="{{ __('wire-core::messages.tour_region') }}"
    >
        <p
            @wireEl('tour-heading')
            x-show="step.heading"
            x-text="step.heading"
            class="mb-1 text-sm font-semibold text-gray-900 dark:text-gray-100"
        ></p>

        <p
            @wireEl('tour-text')
            x-show="step.text"
            x-text="step.text"
            class="text-sm text-gray-600 dark:text-gray-300"
        ></p>

        <div class="mt-4 flex items-center justify-between gap-3">
            <span
                @wireEl('tour-progress')
                x-text="progress"
                class="text-xs tabular-nums text-gray-400 dark:text-gray-500"
            ></span>

            <div class="flex items-center gap-2">
                <button
                    @wireEl('tour-skip')
                    type="button"
                    x-on:click="done(true)"
                    class="rounded-md px-2 py-1 text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                >{{ __('wire-core::messages.tour_skip') }}</button>

                <button
                    @wireEl('tour-back')
                    type="button"
                    x-show="cursor > 0"
                    x-on:click="back()"
                    class="rounded-md border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                >{{ __('wire-core::messages.tour_back') }}</button>

                <button
                    @wireEl('tour-next')
                    type="button"
                    x-on:click="next()"
                    x-text="isLast ? @js(__('wire-core::messages.tour_finish')) : @js(__('wire-core::messages.tour_next'))"
                    class="rounded-md bg-primary-600 px-3 py-1 text-xs font-medium text-white hover:bg-primary-700"
                ></button>
            </div>
        </div>
    </div>
</div>
@endif
