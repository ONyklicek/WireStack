/**
 * wire-admin's own interaction layer: the menu filter, and — beside it as they
 * land — keyboard movement in the menu and the horizontal menu's overflow.
 *
 * A bundle of its own rather than more inline Alpine in the layout: the layout
 * already carries one inline script (the store), and a controller with loops,
 * timers and a type-ahead is the line past which ADR 0024 wants a file and
 * `@wireStackScripts`. Registered unconditionally and idempotently, never only
 * on `alpine:init` — see AI_CODING_STANDARD.md § JavaScript & Alpine.
 */

/** Whether a key press belongs to a field rather than to the page. */
const typingInField = (event) => {
    const target = event.target;

    return target instanceof HTMLElement
        && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
};

/**
 * The menu filter: hides the rows whose label does not match, keeping the
 * structure — a group stays a group and a parent stays over its children.
 *
 * Not a second search. ⌘K searches records, commands and the menu; this narrows
 * the list you are looking at and leaves it looking like itself. Labels are
 * read from `data-nav-label`, which the row carries already lower-cased, so
 * nothing here re-derives what the server rendered.
 */
const wireNavFilter = () => ({
    query: '',

    /** Rows shown while filtering, or null when nothing is typed. */
    matches: null,

    init() {
        this.$watch('query', () => this.apply());

        // A filter that survives a page change is a menu with half its rows
        // missing and nobody knowing why.
        this.clearOnNavigate = () => { this.query = ''; };
        document.addEventListener('livewire:navigated', this.clearOnNavigate);
    },

    destroy() {
        document.removeEventListener('livewire:navigated', this.clearOnNavigate);
        this.setFiltering(false);
    },

    apply() {
        const term = this.query.trim().toLocaleLowerCase();
        let shown = 0;

        this.setFiltering(term !== '');

        this.$root.querySelectorAll('[data-nav-row]:not([data-nav-child])').forEach((row) => {
            const own = term === '' || (row.dataset.navLabel ?? '').includes(term);
            let childMatched = false;

            row.querySelectorAll('[data-nav-child]').forEach((child) => {
                const match = own || (child.dataset.navLabel ?? '').includes(term);

                child.hidden = ! match;
                childMatched ||= match;
            });

            row.hidden = ! (own || childMatched);

            if (! row.hidden) shown++;
        });

        this.$root.querySelectorAll('[data-nav-group]').forEach((group) => {
            group.hidden = term !== '' && ! group.querySelector('[data-nav-row]:not([hidden])');
        });

        this.matches = term === '' ? null : shown;
    },

    /** Folded groups and submenus open while filtering, so a match is visible. */
    setFiltering(on) {
        const store = window.Alpine?.store('wireAdmin');

        if (store) store.filtering = on;
    },

    /** `/` from anywhere on the page, unless the caret is already in a field. */
    focusFromShortcut(event) {
        if (event.key !== '/' || event.metaKey || event.ctrlKey || event.altKey || typingInField(event)) return;

        event.preventDefault();
        this.$refs.filter?.focus();
    },

    /** Escape clears, and gives the keyboard back to the list. */
    clear() {
        this.query = '';

        // Now rather than on the watcher's tick: the rows are still hidden until
        // it runs, and a hidden row cannot take focus.
        this.apply();
        this.$root.querySelector('[data-nav-row] a[href], [data-nav-row] button')?.focus();
    },
});

/**
 * The horizontal menu's overflow: what does not fit the bar is hidden in it and
 * shown under "More" instead.
 *
 * Hidden, not moved. Moving a row's element into the panel would carry its
 * Alpine state and its teleported dropdown with it; the panel instead holds its
 * own rendering of every entry, shown for the ones listed in `overflow`, and
 * named apart (`admin-topnav-more-item`) so nothing counts a row twice.
 *
 * Once one entry overflows, every entry after it does too — the bar keeps the
 * menu's order rather than filling a gap with whatever happens to be narrow.
 */
const wireTopNav = () => ({
    overflow: [],

    init() {
        this.observer = new ResizeObserver(() => this.measure());
        this.observer.observe(this.$refs.bar);
        this.$nextTick(() => this.measure());
    },

    destroy() {
        this.observer?.disconnect();
    },

    /** ← / → along the bar's entries, skipping the ones under More. */
    step(event, by) {
        const controls = [...this.$refs.bar.querySelectorAll(':scope > [data-topnav-entry]:not([hidden])')]
            .map((entry) => entry.querySelector('a[href], button'))
            .filter(Boolean);
        const index = controls.indexOf(event.target.closest('a, button'));

        if (index === -1) return;

        event.preventDefault();
        controls[(index + by + controls.length) % controls.length]?.focus();
    },

    /** ↓ on a group opens its panel and puts focus on the first link in it. */
    openDown(event, open, toggle, panel) {
        event.preventDefault();

        if (! open) toggle();

        this.$nextTick(() => setTimeout(() => panel?.querySelector('a[href]')?.focus(), 20));
    },

    /** ↑ / ↓ between the links of an open panel. */
    panelStep(event, by) {
        const links = [...event.currentTarget.querySelectorAll('a[href]')];
        const index = links.indexOf(document.activeElement);

        event.preventDefault();
        links[Math.min(Math.max(index + by, 0), links.length - 1)]?.focus();
    },

    measure() {
        const bar = this.$refs.bar;
        const entries = [...bar.querySelectorAll(':scope > [data-topnav-entry]')];

        entries.forEach((entry) => { entry.hidden = false; });

        const edge = bar.getBoundingClientRect().right + 0.5;
        const cut = entries.findIndex((entry) => entry.getBoundingClientRect().right > edge);
        const hidden = cut === -1 ? [] : entries.slice(cut);

        hidden.forEach((entry) => { entry.hidden = true; });

        const ids = hidden.map((entry) => entry.dataset.topnavEntry);

        // Only when it changed: a new array every measurement re-renders the
        // panel's rows on every pixel of a window resize.
        if (ids.join('|') !== this.overflow.join('|')) this.overflow = ids;
    },
});

/** Whether an element is drawn — not hidden by the filter, a fold or the rail. */
const drawn = (el) => el.offsetParent !== null && ! el.closest('[hidden]');

/**
 * Moving through the menu from the keyboard (navigation-surfaces.md § 6b).
 *
 * Not `role="menu"` and not a roving tabindex: the menu is a list of links, a
 * screen reader should keep calling them links, and Tab must still walk every
 * row — taking the rows out of the tab order to fix arrow keys would break the
 * one thing that already worked. This only adds movement on top:
 *
 *   ↑ / ↓        the previous / next row that is drawn, across groups
 *   Home / End   the first / last one
 *   → / ←        open / close a row's submenu; ← on a child goes to its parent
 *   a letter     the next row whose label starts with what was typed (500 ms)
 *
 * Rows opt in with `data-nav-focus`, so the pinned copies, the group headings
 * and the rows are one sequence in the order they are drawn.
 */
const wireNavKeys = () => ({
    typed: '',
    typedAt: 0,

    /**
     * The rows the keyboard can land on: drawn, and able to take focus — an
     * unrouted row is an `<a>` with no `href`, and moving onto it would move
     * nowhere while swallowing the key.
     */
    rows() {
        return [...this.$root.querySelectorAll('[data-nav-focus]')]
            .filter((row) => drawn(row) && (row.tagName === 'BUTTON' || row.hasAttribute('href')));
    },

    move(event) {
        const current = event.target.closest?.('[data-nav-focus]');

        if (! current || event.metaKey || event.ctrlKey || event.altKey) return;

        const rows = this.rows();
        const index = rows.indexOf(current);
        let target = null;

        // Any other movement starts the next word afresh: Home and then `t` is
        // a search for "t", not for whatever was typed a moment before.
        if (event.key.length !== 1) this.typed = '';

        switch (event.key) {
            case 'ArrowDown': target = rows[index + 1]; break;
            case 'ArrowUp': target = rows[index - 1]; break;
            case 'Home': target = rows[0]; break;
            case 'End': target = rows[rows.length - 1]; break;
            case 'ArrowRight':
            case 'ArrowLeft':
                target = this.disclose(current, event.key === 'ArrowRight');
                break;
            default:
                target = this.typeAhead(event, rows, index);
        }

        if (target === undefined) return;

        event.preventDefault();
        target?.focus();
    },

    /**
     * → opens a closed submenu and ← closes an open one, by the row's own
     * click — the rail's popover and the wide menu's disclosure both already
     * answer it. ← on a child row goes up to the row it sits under.
     */
    disclose(row, open) {
        const expanded = row.getAttribute('aria-expanded');

        if (expanded !== null) {
            if ((expanded === 'true') !== open) row.click();

            return null;
        }

        if (! open) {
            const parent = row.closest('[data-nav-child]')?.parentElement?.closest('[data-nav-row]');

            return parent?.querySelector('[data-nav-focus]') ?? undefined;
        }

        return undefined;
    },

    typeAhead(event, rows, index) {
        if (event.key.length !== 1 || event.key === ' ') return undefined;

        const now = Date.now();

        this.typed = (now - this.typedAt < 500 ? this.typed : '') + event.key.toLocaleLowerCase();
        this.typedAt = now;

        const label = (row) => (row.getAttribute('aria-label') ?? row.textContent).trim().toLocaleLowerCase();
        const ordered = [...rows.slice(index + 1), ...rows.slice(0, index + 1)];

        // From the row after the current one, wrapping: a repeated letter walks
        // the rows that start with it, a word jumps to the row it starts.
        return ordered.find((row) => label(row).startsWith(this.typed));
    },

    /** ↓ from the filter enters the list, which is what makes the filter usable without a mouse. */
    enter(event) {
        const first = this.rows()[0];

        if (first) {
            event.preventDefault();
            first.focus();
        }
    },
});

let registered = false;

const register = () => {
    if (registered || ! window.Alpine) return;

    registered = true;
    window.Alpine.data('wireNavFilter', wireNavFilter);
    window.Alpine.data('wireTopNav', wireTopNav);
    window.Alpine.data('wireNavKeys', wireNavKeys);
};

if (window.Alpine) register();
else document.addEventListener('alpine:init', register);
