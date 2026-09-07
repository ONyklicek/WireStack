{{-- The theme, decided before the page paints and re-decided after every swap.

     One file rather than one copy per layout, because the rule it encodes is
     written three times otherwise — in the admin head, in the auth head, and in
     the Alpine store's setter — and the copies had already drifted: the auth
     screen read the same key with a two-state rule, so `system` meant light
     there and followed the OS everywhere else.

     Two things have to be true at once, and they pull in opposite directions:

     **Before the first paint.** Reading the choice from Alpine, or from PHP,
     means rendering the page before knowing what colours it wants. So this is
     plain JS in the head, no dependencies, run while the body is still being
     parsed.

     **After a `wire:navigate`.** Livewire's navigate copies the `<html>`
     attributes of the *fetched* document over the live one
     (`replaceHtmlAttributes`), and the server cannot know what this browser
     chose — so every SPA visit arrived with `dark` stripped and the admin went
     white on the second page. `livewire:navigating` hands out an `onSwap`
     callback that runs in the same task as the swap, before the browser is
     given a chance to paint; `livewire:navigated` is the belt to that
     brace, for the cached back/forward path and for any future swap that does
     not offer one.

     The vocabulary is NyonCode\WireCore\Foundation\Enums\Theme's — three states,
     with `system` the one you have to be able to go back to. This file owns the
     one thing PHP cannot: turning that choice into a class on a live document.
     --}}
<script data-wire-admin-theme>
    (() => {
        // The head partial is deduplicated by Livewire's head merge (an inline
        // script is an "asset", matched by its own markup), so this runs once
        // per document. The guard is for the layout that includes it twice.
        if (window.wireAdminTheme) {
            return;
        }

        const key = 'wire-admin.theme';
        const system = window.matchMedia('(prefers-color-scheme: dark)');

        const theme = {
            /**
             * The *choice*, which is one of three — not whether the page is
             * currently dark, which is an outcome. Storing the outcome is what
             * makes `system` impossible to return to.
             *
             * Anything unknown — an old two-state value, a hand-edited setting
             * — is the system, which is what Theme::resolve() answers on the PHP
             * side. One rule, written twice because one half of it has to run
             * before the page is painted.
             */
            get() {
                try {
                    return window.localStorage.getItem(key) ?? 'system';
                } catch (e) {
                    // Private mode, or storage blocked: the system preference
                    // still applies, which is the default anyway.
                    return 'system';
                }
            },

            /** Put the stored choice on the document. The only writer of `dark`. */
            apply() {
                const chosen = theme.get();

                document.documentElement.classList.toggle(
                    'dark',
                    chosen === 'dark' || (chosen !== 'light' && system.matches),
                );

                return chosen;
            },

            /** Choose, remember, and show it — in that order. */
            set(chosen) {
                try {
                    window.localStorage.setItem(key, chosen);
                } catch (e) {
                    // Unstorable is still switchable: the page changes now and
                    // forgets on the next load, which beats not changing.
                }

                return theme.apply();
            },
        };

        window.wireAdminTheme = theme;

        theme.apply();

        // Following the system means following it *while the page is open*.
        // Without this, a laptop that dims itself at sunset leaves an admin
        // sitting in the wrong theme until it is reloaded — which is the whole
        // difference between "system" and "whatever it was at load".
        system.addEventListener('change', () => {
            if (theme.get() === 'system') {
                theme.apply();
            }
        });

        // Same task as the swap: `onSwap` callbacks run between
        // `document.body.replaceWith()` and the end of the navigate turn, so the
        // stripped class is back before anything is painted. A visit that hands
        // out no callback still gets the class back one event later.
        document.addEventListener('livewire:navigating', (event) => {
            if (typeof event.detail?.onSwap === 'function') {
                event.detail.onSwap(() => theme.apply());
            }
        });

        document.addEventListener('livewire:navigated', () => theme.apply());
    })();
</script>
