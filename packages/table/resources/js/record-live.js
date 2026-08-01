/*
 * The push half of `Table::live(broadcast: true)`.
 *
 * A live table already polls, and the poll already carries another user's write
 * onto the screen. This only changes WHEN: it listens on the table's broadcast
 * channel and re-reads as soon as somebody commits, instead of on the next tick.
 *
 * Which is why every failure here is silent and harmless. No Echo on the page,
 * no broadcast connection in the app, a socket that drops halfway through the
 * afternoon — the table falls back to its interval rather than to nothing, and
 * the user sees a slower table, not a stale one. Nothing on the wire is applied
 * either: the event is a nudge, and the refresh goes through the component's own
 * query, so authorization, filters and the current page are re-evaluated
 * server-side exactly as they are for a poll.
 *
 * Kept import-free, like record-selection.js: when the compiled bundle is
 * missing the asset partial inlines this file verbatim, and a dangling import
 * would take Alpine down with it.
 */

// A function, NOT an arrow: Alpine binds a data factory's `this` to the magic
// context that carries $wire.
function wireTableLive(config = {}) {
    return {
        /* Declared, not assigned into on the fly. Alpine resolves `this` inside a
           component method to the merged scope stack, and its setter puts a name
           no scope owns onto the OUTERMOST one — so an undeclared field would be
           shared with every other component wrapping this table rather than
           belonging to this one. */
        channel: config.channel || null,
        eventName: config.event || '.wire-table.changed',
        /* A burst of writes (a fill over fifty rows, a bulk action) is one
           broadcast per record. Coalesce them into a single re-read. */
        settle: config.settle ?? 250,
        _timer: null,
        _subscription: null,

        init() {
            if (! this.channel || ! window.Echo) return

            try {
                this._subscription = window.Echo.private(this.channel)
                this._subscription.listen(this.eventName, () => this.schedule())
            } catch (e) {
                // Echo present but unusable (no connection configured, channel
                // authorization refused). The interval is still running.
                this._subscription = null
            }
        },

        destroy() {
            clearTimeout(this._timer)

            if (! this._subscription || ! window.Echo) return

            try {
                window.Echo.leave(this.channel)
            } catch (e) {
                // Nothing to do — the page is going away either way.
            }
        },

        schedule() {
            clearTimeout(this._timer)
            this._timer = setTimeout(() => this.refresh(), this.settle)
        },

        /**
         * Re-read, unless the user is in the middle of something.
         *
         * A cell mid-write is the case that matters: the server would answer
         * with the value as it stands BEFORE that write commits, and while the
         * cell itself refuses to reconcile over a save in flight, there is no
         * reason to spend the round trip only to be ignored. Waiting one settle
         * window costs nothing — the broadcast that prompted this is about
         * somebody else's write, which is not going anywhere.
         */
        refresh() {
            if (this.busy()) {
                this.schedule()

                return
            }

            this.$wire.refreshTable()
        },

        /** Any cell of this table with a write in flight. */
        busy() {
            return [...this.$el.querySelectorAll('[data-record-key][data-column-name]')]
                .some((cell) => {
                    try {
                        return window.Alpine.$data(cell)?.saving === true
                    } catch (e) {
                        return false
                    }
                })
        },
    }
}

const registerWireTableLive = () => {
    window.Alpine.data('wireTableLive', wireTableLive)
}

if (window.Alpine) {
    registerWireTableLive()
} else {
    document.addEventListener('alpine:init', registerWireTableLive)
}
