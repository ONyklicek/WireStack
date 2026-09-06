/**
 * OtpInput's controller: N boxes that behave like one field.
 *
 * The body was an inline `x-data` object literal in the view, which meant every
 * OTP field on a page shipped all ~55 lines of it again — the same per-instance
 * cost the other field controllers were moved out of the markup to avoid
 * (architecture/plans/forms-and-surfaces-performance.md § 3). What stays in the
 * markup is the per-instance config.
 *
 * The value is written through `$wire.set()` rather than entangled: the boxes
 * are the source of truth between keystrokes, and the joined string is what the
 * field's state holds.
 */
const wireOtpInput = (config = {}) => ({
    length: config.length ?? 6,
    statePath: config.statePath ?? '',
    numericOnly: config.numericOnly ?? false,

    digits: Array(config.length ?? 6).fill(''),

    init() {
        const existing = this.$wire.get(this.statePath)

        if (existing) {
            String(existing)
                .split('')
                .slice(0, this.length)
                .forEach((character, index) => {
                    this.digits[index] = character
                })
        }

        this.$watch('digits', () => {
            this.$wire.set(this.statePath, this.digits.join(''))
        })
    },

    /**
     * What a box is allowed to hold. `numericOnly` says the code is digits, so a
     * letter is dropped rather than stored and validated away later — including
     * one that arrives inside a pasted string.
     */
    filter(text) {
        const cleaned = text.replace(/\s/g, '')

        return this.numericOnly ? cleaned.replace(/\D/g, '') : cleaned
    },

    /** Fill from `index` onwards, and leave the caret on the last box written. */
    fill(index, characters) {
        const written = characters.slice(0, this.length - index)

        written.split('').forEach((character, offset) => {
            if (index + offset < this.length) this.digits[index + offset] = character
        })

        this.focusBox(Math.min(index + written.length, this.length - 1))
    },

    focusBox(index) {
        this.$nextTick(() => this.$refs['digit-' + index]?.focus())
    },

    onInput(index, event) {
        const raw = event.target.value
        const value = this.filter(raw)

        // Everything was filtered away, so the keystroke was not for this field:
        // put the digit that was there back rather than letting a rejected
        // character delete a valid one. (A box is selected on focus, so typing
        // over it replaces what it holds.)
        if (raw !== '' && value === '') {
            event.target.value = this.digits[index]

            return
        }

        // More than one character means a paste landed in a single box.
        if (value.length > 1) {
            this.fill(index, value)

            return
        }

        this.digits[index] = value.slice(-1)

        // A cleared box must not advance — the caret belongs where the user
        // just deleted, not one box to the right of it.
        if (value && index < this.length - 1) this.focusBox(index + 1)
    },

    onKeydown(index, event) {
        if (event.key === 'Backspace') {
            // Backspace in an empty box steps back and clears the one before,
            // so a code is deleted with one key rather than two per box.
            if (! this.digits[index] && index > 0) {
                this.digits[index - 1] = ''
                this.focusBox(index - 1)
            } else {
                this.digits[index] = ''
            }
        } else if (event.key === 'ArrowLeft' && index > 0) {
            this.$refs['digit-' + (index - 1)]?.focus()
        } else if (event.key === 'ArrowRight' && index < this.length - 1) {
            this.$refs['digit-' + (index + 1)]?.focus()
        }
    },

    onPaste(event) {
        event.preventDefault()

        this.fill(0, this.filter(event.clipboardData.getData('text')))
    },
})

export default wireOtpInput
