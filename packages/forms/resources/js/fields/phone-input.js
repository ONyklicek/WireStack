/**
 * PhoneInput's controller: two controls over one value.
 *
 * The field's state is the written international number (`+420 123 456 789`).
 * This splits it into the prefix the select shows and the digits the input
 * holds, and writes it back on every change — so the form, its validation and
 * the model only ever see one string.
 *
 * The national part is NOT reformatted while it is being typed. Regrouping on
 * every keystroke moves the caret to the end of the input, which makes an edit
 * in the middle of a number impossible; the grouping is applied to the value
 * that goes into state, and to what arrives from the server.
 */
const wirePhoneInput = (config = {}) => ({
    state: config.state,
    countries: config.countries ?? [],
    country: config.defaultCountry ?? '',
    national: '',

    init() {
        this.read(this.state)

        // The server can hand over a different number (a reactive default, a
        // record loaded into the form). Re-read it, unless it is what this
        // controller just wrote.
        this.$watch('state', (value) => {
            if (value !== this.compose()) this.read(value)
        })

        this.$watch('country', () => this.write())
        this.$watch('national', () => this.write())
    },

    /** Split a written number into the select's prefix and the input's digits. */
    read(value) {
        const number = (value ?? '').toString().replace(/[^0-9+]/g, '')

        if (! number) {
            this.national = ''

            return
        }

        // The longest matching prefix wins, so +420 beats +4 and +1 never
        // swallows +1868.
        const match = this.countries
            .filter((option) => number.startsWith(option.dialingCode))
            .sort((a, b) => b.dialingCode.length - a.dialingCode.length)[0]

        if (! match) {
            this.national = number

            return
        }

        this.country = match.country
        this.national = this.group(number.slice(match.dialingCode.length))
    },

    /** Put the two controls back together into the one value state holds. */
    write() {
        this.state = this.compose()
    },

    compose() {
        const digits = (this.national ?? '').replace(/\D/g, '')

        // An empty number is empty, prefix and all: a field left alone must not
        // arrive at validation holding a bare `+420`.
        if (! digits) return ''

        return `${this.dialingCode()} ${this.group(digits)}`
    },

    dialingCode() {
        const match = this.countries.find((option) => option.country === this.country)

        return match ? match.dialingCode : ''
    },

    /**
     * Digits in groups of three, the trailing single digit joining the group
     * before it. Mirrors PhoneInput::group() in PHP — the two write the same
     * number, whichever side rendered it.
     */
    group(digits) {
        const groups = digits.match(/.{1,3}/g) ?? []

        if (groups.length > 1 && groups[groups.length - 1].length === 1) {
            groups[groups.length - 2] += groups.pop()
        }

        return groups.join(' ')
    },
})

export default wirePhoneInput
