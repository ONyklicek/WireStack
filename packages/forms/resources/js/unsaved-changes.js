/**
 * Warn before leaving a form whose input has not been saved.
 *
 * Put on the element that owns the form — `x-data="wireUnsavedChanges({...})"` —
 * with the form's state path and the method that saves it. Two ways out are
 * covered: the browser's own (a reload, a closed tab, a typed URL), answered by
 * `beforeunload` with the browser's own prompt, and `wire:navigate`, answered by
 * cancelling Livewire's `livewire:navigate` after a `confirm()`.
 *
 * "Unsaved" is the form's state against what was last saved, not "a key was
 * pressed": the baseline is the state bag as the page arrived, and it moves only
 * when the save method succeeds. So typing a value and deleting it again warns
 * about nothing, and a field that writes its state without a DOM event — a
 * picker, a rich editor, an entangled select — is seen like any other, because
 * what is compared is `$wire`'s copy of the bag, which every field writes into.
 *
 * The save itself must not be stopped by the warning it is about to make
 * unnecessary. A create page redirects from inside the save's response, and
 * Livewire processes that redirect before the action resolves — so a save in
 * flight holds the warning off, and settles the question when it finishes:
 * success moves the baseline, a validation failure leaves the warning armed.
 *
 * **A draft, when the page asks for one** (`draftKey`). Unsaved input is also
 * written to `localStorage` under that key — every few seconds while the form is
 * dirty, and as the page goes away — and a page that arrives to find one that
 * differs from what it loaded offers it back (`draftAvailable`, `restoreDraft()`,
 * `discardDraft()`). A successful save throws it away. Storage that throws — a
 * private window, a full quota — is not an error: there is simply no draft.
 */
const wireUnsavedChanges = (config = {}) => ({
    path: config.path ?? 'data',
    method: config.method ?? 'save',
    message: config.message ?? 'You have unsaved changes. Leave anyway?',
    baseline: null,
    saving: false,
    draftKey: config.draftKey ?? null,
    draftAvailable: false,

    init() {
        this.baseline = this.snapshot()

        const stored = this.readDraft()
        this.draftAvailable = stored !== null && stored !== this.baseline

        if (this.draftKey) {
            this.draftTimer = setInterval(() => this.keepDraft(), 4000)
        }

        this.stopIntercepting = this.$wire.$intercept(this.method, ({ onSuccess, onFinish }) => {
            this.saving = true

            onSuccess(() => {
                this.baseline = this.snapshot()
                this.discardDraft()
            })

            onFinish(() => {
                this.saving = false
            })
        })

        this.onBeforeUnload = (event) => {
            this.keepDraft()

            if (! this.isDirty()) return

            event.preventDefault()
            // Older browsers show their prompt only when this is set.
            event.returnValue = ''
        }

        this.onNavigate = (event) => {
            if (! this.isDirty()) return

            if (window.confirm(this.message)) {
                // Leaving was chosen. The page is going; a second prompt from
                // `beforeunload` on the same way out would ask twice.
                this.baseline = this.snapshot()

                return
            }

            event.preventDefault()
        }

        window.addEventListener('beforeunload', this.onBeforeUnload)
        document.addEventListener('livewire:navigate', this.onNavigate)
    },

    destroy() {
        clearInterval(this.draftTimer)
        window.removeEventListener('beforeunload', this.onBeforeUnload)
        document.removeEventListener('livewire:navigate', this.onNavigate)
        this.stopIntercepting?.()
    },

    /** The state bag as a string, so two bags compare by value. */
    snapshot() {
        return JSON.stringify(this.$wire.$get(this.path) ?? null)
    },

    isDirty() {
        return ! this.saving && this.snapshot() !== this.baseline
    },

    /** Write the unsaved state under the draft key; a clean form keeps none. */
    keepDraft() {
        if (! this.draftKey || this.draftAvailable) return

        try {
            this.isDirty()
                ? window.localStorage.setItem(this.draftKey, this.snapshot())
                : window.localStorage.removeItem(this.draftKey)
        } catch (e) {}
    },

    readDraft() {
        if (! this.draftKey) return null

        try {
            return window.localStorage.getItem(this.draftKey)
        } catch (e) {
            return null
        }
    },

    /** Put the kept draft back into the form. The baseline stays: it is still unsaved. */
    restoreDraft() {
        const stored = this.readDraft()
        this.draftAvailable = false

        if (stored !== null) {
            this.$wire.$set(this.path, JSON.parse(stored))
        }
    },

    discardDraft() {
        this.draftAvailable = false

        try {
            if (this.draftKey) window.localStorage.removeItem(this.draftKey)
        } catch (e) {}
    },
})

export default wireUnsavedChanges
