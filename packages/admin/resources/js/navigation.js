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

let registered = false;

const register = () => {
    if (registered || ! window.Alpine) return;

    registered = true;
    window.Alpine.data('wireNavFilter', wireNavFilter);
};

if (window.Alpine) register();
else document.addEventListener('alpine:init', register);
