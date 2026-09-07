/*
 * The push half of the `broadcast` notification driver.
 *
 * The bell already tells the truth every time the page talks to the server. This
 * only changes WHEN: it listens on the recipient's own channel and re-reads as
 * soon as a notification is raised, instead of at whatever the next round trip
 * happens to be — which, for a tab nobody is clicking in, is never.
 *
 * Which is why every failure here is silent and harmless. No Echo on the page, no
 * broadcast connection in the app, a socket that drops over lunch — the bell
 * falls back to being right-on-render rather than to being wrong, and the user
 * sees a late badge, not a stale one. Nothing on the wire is applied either: the
 * event carries no payload, and the refresh goes through the component's own
 * NotificationCenter read, so the recipient scoping is re-evaluated server-side
 * exactly as it is for a full render.
 *
 * Kept import-free, like record-selection.js: when the compiled bundle is missing
 * the asset partial inlines this file verbatim, and a dangling import would take
 * Alpine down with it.
 */

// A function, NOT an arrow: Alpine binds a data factory's `this` to the magic
// context that carries $wire.
function wireNotificationLive(config = {}) {
    return {
        /* Declared, not assigned into on the fly. Alpine's setter puts a name no
           scope owns onto the OUTERMOST scope, so an undeclared field would be
           shared with whatever else wraps this bell rather than belonging to it. */
        channel: config.channel || null,
        eventName: config.event || '.wire-notification.received',
        /* A bulk job notifying one user forty times is forty broadcasts.
           Coalesce them into a single re-read. */
        settle: config.settle ?? 250,
        _timer: null,
        _subscription: null,
        /* Echo retries a refused subscription, and a console filling with the
           same paragraph is a console people stop reading. */
        _reportedRefusal: false,

        init() {
            if (! this.channel || ! window.Echo) return

            try {
                this._subscription = window.Echo.private(this.channel)
                this._subscription.listen(this.eventName, () => this.schedule())

                // Say so when the subscription is refused.
                //
                // This is the one failure worth being loud about, because it is
                // the only one that looks like success: the bell still updates on
                // every render, so nothing appears broken, and the push half is
                // simply dead. An app that never authorized the channel — or
                // authorized a different name — would otherwise have no way to
                // find that out short of timing the updates.
                //
                // A warning, not an error: the fallback is working as designed.
                // And a report only — authorizing is the application's decision,
                // so nothing here retries or works around a policy.
                if (typeof this._subscription.error === 'function') {
                    this._subscription.error((e) => this.reportRefused(e))
                }
            } catch (e) {
                // Echo present but unusable (no connection configured at all).
                this._subscription = null
            }
        },

        /** @param {*} e whatever Echo hands back for a refused subscription */
        reportRefused(e) {
            if (this._reportedRefusal) return

            this._reportedRefusal = true
            console.warn(
                '[wire] notification channel "'
                + this.channel
                + '" refused the subscription; the bell will still update on every '
                + 'render, but not live. Authorize it in routes/channels.php, or '
                + 'leave wire-core.notifications.broadcast.authorize on.',
                e,
            )
        },

        schedule() {
            clearTimeout(this._timer)
            this._timer = setTimeout(() => this.refresh(), this.settle)
        },

        refresh() {
            /* The component may be gone by the time a coalesced burst lands —
               a wire:navigate away, a teardown mid-flight. */
            if (! this.$wire) return

            this.$wire.$refresh()
        },

        destroy() {
            clearTimeout(this._timer)

            if (this._subscription && window.Echo) {
                try {
                    window.Echo.leave(this.channel)
                } catch (e) {
                    // Leaving a channel we may never have joined is not a failure
                    // worth surfacing; the socket is going away regardless.
                }
            }

            this._subscription = null
        },
    }
}

let registered = false

const registerWireNotificationLive = () => {
    if (registered || ! window.Alpine) return
    registered = true
    window.Alpine.data('wireNotificationLive', wireNotificationLive)
}

if (window.Alpine) registerWireNotificationLive()
else document.addEventListener('alpine:init', registerWireNotificationLive)
