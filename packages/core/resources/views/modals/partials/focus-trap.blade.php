{{-- Focus management for a modal shell. Included INSIDE the root element's
     opening tag, so all three shells (modal, confirmation, slide-over) get one
     implementation rather than three.

     Without this the focus simply stays wherever it was when the modal opened.
     On a grid table that means it stays on the row behind the dialog: Tab then
     walks the page *behind* the modal instead of its buttons, the dialog cannot
     be operated from the keyboard at all, and when it closes the focus is left
     on whatever the tabbing landed on — so the grid's arrow keys, which only
     answer when a row itself has the focus, are dead until the user clicks a
     row again.

     Three parts: take the focus on open (remembering where it came from), keep
     Tab inside while open, and hand it back on close.

     Deliberately expression-only, with no dependency on a JS bundle: a modal
     must not need an asset that a page rendering only modals would not load.
     Single quotes throughout — a double quote inside an Alpine attribute
     truncates it, which is how this file would silently stop working. --}}
x-effect="
    if (show && ! $el._wireFocusFrom) {
        $el._wireFocusFrom = document.activeElement;
        $nextTick(() => {
            const target = $el.querySelector('[autofocus]') || $el._wireFocusable()[0] || $el;
            target.focus({ preventScroll: true });
        });
    } else if (! show && $el._wireFocusFrom) {
        const back = $el._wireFocusFrom;
        $el._wireFocusFrom = null;
        {{-- Only if it is still in the document and still focusable: a Livewire
             re-render may have replaced the row the modal was opened from. --}}
        if (back && back.isConnected && back !== document.body) {
            back.focus({ preventScroll: true });
        }
    }
"
x-on:keydown.tab="
    const items = $el._wireFocusable();
    if (! items.length) return;

    const first = items[0];
    const last = items[items.length - 1];
    const active = document.activeElement;

    {{-- Focus outside the dialog (the row behind it, say) is pulled back in. --}}
    if (! $el.contains(active)) {
        $event.preventDefault();
        ($event.shiftKey ? last : first).focus();
    } else if ($event.shiftKey && active === first) {
        $event.preventDefault();
        last.focus();
    } else if (! $event.shiftKey && active === last) {
        $event.preventDefault();
        first.focus();
    }
"
x-init="
    {{-- tabIndex >= 0 rather than a :not([tabindex='-1']) selector, which would
         need quotes this attribute cannot carry. --}}
    $el._wireFocusable = () => [...$el.querySelectorAll('a[href], button, input, select, textarea, [tabindex]')]
        .filter((el) => ! el.disabled && el.tabIndex >= 0 && el.getClientRects().length > 0)
"
tabindex="-1"
