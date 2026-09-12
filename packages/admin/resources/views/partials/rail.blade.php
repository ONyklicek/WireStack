{{-- Whether the menu is a rail, decided before the page paints.

     The same shape as `theme.blade.php` beside it, for exactly the same reason,
     and the defect it fixes was the larger of the two. The choice lives in
     localStorage; reading it from the Alpine store meant the first frame of
     every page was the *other* answer — and because the column carries
     `transition-[width]`, a collapsed menu did not simply flicker. It opened to
     288 pixels, showed every label, and slid shut over a third of a second, on
     every load and every `wire:navigate`. Measured, before this file existed:
     288px held for ~50ms, then 284 → 269 → 235 → 186 → … → 64.

     There is no server answer to fall back on: the shell has no session state
     and no cookie, and inventing one would put the menu's width into the
     request cycle to save a script this small. So the answer is stamped on
     `<html>` before the body is parsed, and the rules below read it.

     **Why CSS rather than Tailwind utilities.** Eight elements need it — the
     column's width, the row's centring, five things the rail hides and one it
     reveals — and all of them need it *before* Alpine. Eight copies of an
     arbitrary variant spelling `[html[data-rail=true]_&]` would be less legible
     than five selectors, and `x-show` cannot run early enough to help at all.
     What stays on Alpine is what is genuinely runtime: a group's own folding,
     and whether pointing at a row opens anything.

     `data-rail` is the whole vocabulary. An element says what it wants with
     `data-rail-hide` or `data-rail-only`; the width and the centring belong to
     the sidebar and the row. Alpine keeps the attribute in step afterwards
     through `window.wireAdminRail`, the way the theme switch goes through
     `window.wireAdminTheme` — one owner for what the choice means, rather than
     a second copy of the rule in a store. --}}
<script data-wire-admin-rail>
    (() => {
        // Deduplicated by Livewire's head merge the same way the theme script
        // is; the guard is for a layout that includes the partial twice.
        if (window.wireAdminRail) {
            return;
        }

        const key = 'wire-admin.rail';

        const rail = {
            get() {
                try {
                    return window.localStorage.getItem(key) === '1';
                } catch (e) {
                    // Private mode, or storage blocked: a menu that cannot
                    // remember being narrow is better wide than missing.
                    return false;
                }
            },

            /** Put the stored choice on the document. The only writer of `data-rail`. */
            apply() {
                const collapsed = rail.get();

                document.documentElement.setAttribute('data-rail', collapsed ? 'true' : 'false');

                return collapsed;
            },

            /** Choose, remember, and show it — in that order. */
            set(collapsed) {
                try {
                    window.localStorage.setItem(key, collapsed ? '1' : '0');
                } catch (e) {
                    // Unstorable is still switchable.
                }

                return rail.apply();
            },
        };

        window.wireAdminRail = rail;

        rail.apply();

        // `wire:navigate` replaces the live `<html>` attributes with the fetched
        // document's, and the server does not know what this browser chose — so
        // without this the rail springs open on the second page, which is the
        // very flash the file exists to remove. Same two hooks as the theme, and
        // the same reason for both: `onSwap` runs before the browser paints,
        // `livewire:navigated` covers the cached back/forward path.
        document.addEventListener('livewire:navigating', (event) => {
            if (typeof event.detail?.onSwap === 'function') {
                event.detail.onSwap(() => rail.apply());
            }
        });

        document.addEventListener('livewire:navigated', () => rail.apply());
    })();
</script>

<style data-wire-admin-rail>
    /* Hidden until the media query below reveals it: a dot that belongs on a
       64-pixel icon must not appear beside a label in the wide menu, and it must
       not appear at all on a phone, whose drawer is never a rail. */
    [data-rail-only] { display: none; }

    /* The column's width is a layout constant, not a density dial — and this is
       the file that owns the column's width, so the pin belongs here rather than
       in the shared density partial, which knows nothing about a sidebar.

       `w-72` and `lg:w-64` read `--spacing` like every other utility, so compact
       took the menu to 201px as a drawer and 179px as a column while the rail
       beside it stayed the 4rem written below as a literal — a "narrow" menu
       115 pixels wider than the narrow menu. What it cost was legible: group
       headings clipped to "BILLING & INVOICI…". Compact's gain in a menu is
       vertical (more entries on the screen), and the rules above keep it. */
    [data-density="compact"] .wire-admin-sidebar { width: 18rem; }

    @media (min-width: 1024px) {
        /* Ordered before the rail's own width below, not after: the two
           selectors weigh the same, so whichever is written last would win — and
           a collapsed compact menu 16rem wide is the rail not collapsing. */
        [data-density="compact"] .wire-admin-sidebar { width: 16rem; }

        /* `wide` and `mobile` are not tested anywhere in here on purpose. Below
           this width the same element is a drawer, which is the whole of what
           those two flags were for — so the media query answers both, and the
           rail cannot follow the menu onto a phone by accident. */
        [data-rail="true"] .wire-admin-sidebar { width: 4rem; }

        [data-rail="true"] [data-rail-hide] { display: none; }
        [data-rail="true"] [data-rail-only] { display: block; }

        [data-rail="true"] [data-rail-row] {
            justify-content: center;
            padding-inline: 0;
        }
    }
</style>
