import { Passkeys, UserCancelledError } from '@laravel/passkeys'

/*
 * wirePasskey — an Alpine adapter over Laravel's own passkey client.
 *
 * Everything that can be got wrong here already has an owner. The challenge, the
 * relying party, the signature check, the credential rows and the session are
 * `laravel/passkeys` behind Fortify's `Features::passkeys()`; the browser
 * ceremony — `navigator.credentials`, the base64url on both sides of it, the
 * CSRF header, the conditional-UI autofill and the typed errors — is the
 * `@laravel/passkeys` npm client the package documents. This file is the twenty
 * lines that make those two reachable from a Blade view: a `busy` flag, a
 * message, and where to go afterwards.
 *
 * **An adapter, never a second wheel.** A hand-written ceremony would be a
 * parallel implementation of a security-adjacent protocol, diverging from the
 * documented client on the first browser quirk either of them learns about.
 *
 * In wire-core rather than beside either screen, for the reason `menu-item`
 * moved down (ADR 0032 §5): the auth module's sign-in button and the users
 * module's profile card need the same behaviour, and neither package may depend
 * on the other. Core is the lowest layer that can own it.
 *
 * Two things are deliberately not errors:
 *
 *   - **the visitor closing the platform dialog.** `UserCancelledError` is a
 *     decision, not a failure; a red line under the button for it reads as a bug
 *     in the site.
 *   - **a browser with no WebAuthn.** `supported` is what the views bind their
 *     visibility to, so the button is absent rather than present and broken —
 *     and on the login screen the password form is right there.
 *
 * The routes are passed in from PHP rather than defaulted here, because Fortify
 * lets an application rename every path it registers (`fortify.paths`), and a
 * client guessing `/passkeys/login` would post into a 404 on such an
 * installation.
 */
function wirePasskey(config = {}) {
    return {
        busy: false,
        error: null,
        supported: Passkeys.isSupported(),

        /**
         * Offer saved passkeys inside the browser's own credential dropdown.
         *
         * Only where the view asked for it and the browser can do it. The
         * dropdown anchors to an input whose `autocomplete` carries the
         * `webauthn` token — the login screen's e-mail field — and if none is on
         * the page the request shows nothing at all, silently, which is why this
         * is opt-in per screen rather than on everywhere.
         */
        init() {
            if (! config.autofill || ! this.supported || ! Passkeys.isAutofillSupported()) {
                return
            }

            Passkeys.autofill({ remember: !! config.remember, routes: config.routes })
                .then((response) => this.arrive(response))
                .catch(() => {})
        },

        /**
         * Sign in with a passkey.
         *
         * No address is asked for and none is sent: a discoverable credential
         * already knows which account it belongs to, which is why this button
         * sits *beside* the password form rather than inside it.
         */
        async signIn() {
            await this.ceremony(async () => {
                this.arrive(await Passkeys.verify({
                    remember: !! config.remember,
                    routes: config.routes,
                }))
            })
        },

        /**
         * Add a passkey to the signed-in account.
         *
         * The name is the person's own label for the device — "MacBook", "phone"
         * — and is the only thing typed in the whole ceremony.
         */
        async register(name) {
            await this.ceremony(async () => {
                await Passkeys.register({
                    name: name || config.defaultName || 'Passkey',
                    routes: config.routes,
                })

                // The list of keys is server-rendered, so the page it lives on
                // says what to do next: a Livewire refresh where there is one,
                // a reload where there is not.
                if (config.on) {
                    this.$dispatch(config.on, { name })
                } else {
                    window.location.reload()
                }
            })
        },

        /** Where a verified passkey lands — Fortify's answer, carried in its reply. */
        arrive(response) {
            window.location.href = response?.redirect ?? config.redirect ?? '/'
        },

        /**
         * The shape both ceremonies share: busy, cancelled, failed, done.
         *
         * `busy` is cleared in `finally` rather than on each path, because the
         * path that would be forgotten is the throw — and a button left spinning
         * after a dismissed dialog is a screen nobody can retry from.
         */
        async ceremony(run) {
            if (this.busy || ! this.supported) return

            this.busy = true
            this.error = null

            try {
                await run()
            } catch (error) {
                this.error = error instanceof UserCancelledError
                    ? null
                    : (error?.message || config.failedMessage || null)
            } finally {
                this.busy = false
            }
        },
    }
}

function registerWirePasskey() {
    window.Alpine.data('wirePasskey', wirePasskey)
}

if (window.Alpine) {
    // Alpine already started (the script arrived after a Livewire navigation).
    registerWirePasskey()
} else {
    document.addEventListener('alpine:init', registerWirePasskey)
}
