{{-- The spacing scale, and the two places it must not reach.

     Emitted once in the head, keyed on `[data-density]` so a fixed choice and a
     per-person switch are the same rules: the layout sets the attribute from
     config today, and a toggle will flip it in the browser later.

     **Why this ships from the framework rather than living in the application's
     stylesheet.** The first line is the one anybody would write. The rest is
     what measuring the real pages found, and nobody derives it from
     documentation:

       - `--spacing` drives `h-16` and `w-4` as surely as it drives `px-3`, so an
         unpinned compact takes the top bar from 64px to 44px and an icon from
         16px to 10px — 8px in the media manager. Pinning the icon also recovers
         the icon-only buttons whose height follows it (11px → 19px there).
       - `@tailwindcss/forms` sets `padding: .5rem .75rem` on every text input,
         select and textarea as a literal in its base layer. `--spacing` cannot
         reach it, which leaves the surface where density matters most almost
         unchanged: five form fields span 258px by default and 240px on the token
         alone. Reaching the control padding takes them to 228px.

     Font size is deliberately untouched: it reads `--text-*`, a separate family,
     so compact tightens the chrome and leaves the words alone.
--}}
<style data-wire-density>
    [data-density="compact"] {
        --spacing: 0.175rem;
    }

    /* The two the token over-reaches. Values are the shipped ones, so a pin
       restores the default rather than inventing a size. */
    [data-density="compact"] svg {
        min-width: 1rem;
        min-height: 1rem;
    }

    [data-density="compact"] header {
        min-height: 4rem;
    }

    /* The one the token cannot reach at all. Matched the way the forms plugin
       matches, so this lands on the same controls and nothing else. */
    [data-density="compact"] :is(
        input:where([type="text"], [type="email"], [type="password"], [type="number"],
                    [type="search"], [type="url"], [type="tel"], [type="date"],
                    [type="datetime-local"], [type="month"], [type="time"], [type="week"],
                    :not([type])),
        select,
        textarea
    ) {
        padding-top: 0.3125rem;
        padding-bottom: 0.3125rem;
    }
</style>

{{-- The per-person half of the same setting.

     The `<style>` above is keyed on `[data-density]`, and the layout renders
     that attribute from config. This lets a person choose for themselves, and
     the two compose rather than fight: **config is the default, a stored choice
     overrides it.** Somebody who has never touched the switch keeps whatever the
     application shipped, including a later change to it.

     Three things this has to get right, all of them learned by the theme script
     next door:

     **Before the first paint.** The server has already put the default on the
     document, so there is no flash for someone who never chose — but a person
     who chose compact would see the default first if this waited for Alpine.
     Plain JS in the head, no dependencies.

     **After a `wire:navigate`.** Livewire copies the `<html>` attributes of the
     *fetched* document over the live one, and the server cannot know what this
     browser chose — so every SPA visit would arrive with the config default and
     silently undo the choice. This is the same bug that stripped `dark`, on a
     different attribute.

     **Storage can refuse.** Private mode and blocked site data throw on read and
     on write. Unstorable is still switchable: the page changes now and forgets
     on the next load, which beats not changing.
--}}
<script data-wire-density-script>
    (() => {
        if (window.wireDensity) {
            return;
        }

        const key = 'wire.density';
        const root = document.documentElement;

        // What the server put there — the application's default, and what a
        // person who has never chosen should keep seeing.
        const shipped = root.dataset.density || 'normal';

        const density = {
            /** The stored choice, or the application's default. */
            get() {
                try {
                    return window.localStorage.getItem(key) ?? shipped;
                } catch (e) {
                    return shipped;
                }
            },

            /** Put it on the document. The only writer of `data-density`. */
            apply() {
                const chosen = density.get();
                root.dataset.density = chosen;

                return chosen;
            },

            /** Choose, remember, and show it — in that order. */
            set(chosen) {
                try {
                    window.localStorage.setItem(key, chosen);
                } catch (e) {
                    // See the note above: unstorable is still switchable.
                }

                return density.apply();
            },

            /** Back to the application's default, and stop remembering. */
            clear() {
                try {
                    window.localStorage.removeItem(key);
                } catch (e) {
                    // Nothing was stored, so nothing has to be removed.
                }

                return density.apply();
            },
        };

        window.wireDensity = density;

        density.apply();

        // Same task as the swap, so the attribute is back before anything is
        // painted; the second listener is the belt for a visit that hands out no
        // callback (the cached back/forward path).
        document.addEventListener('livewire:navigating', (event) => {
            if (typeof event.detail?.onSwap === 'function') {
                event.detail.onSwap(() => density.apply());
            }
        });

        document.addEventListener('livewire:navigated', () => density.apply());
    })();
</script>
