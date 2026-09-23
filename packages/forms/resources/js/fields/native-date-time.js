/**
 * A datetime with a clock step, made of two browser-native controls.
 *
 * `<input type="datetime-local">` cannot hold a step on a phone — to the browser
 * `step` is a validation rule, not a wheel, and iOS offers every minute — so a
 * `DateTimePicker` with `minutesStep()` renders a native date input beside a
 * native `<select>` of the clock slots instead, and this joins the two halves
 * into the one `Y-m-d\TH:i[:s]` state the field has always had.
 *
 * `state` is built in the x-data expression (`$wire.entangle` is an Alpine
 * magic, in scope only there). A half-filled pair writes nothing: the state is
 * a whole datetime or null, never a date without a time.
 */
const wireNativeDateTime = (config = {}) => ({
    state: config.state,
    date: '',
    time: '',

    init() {
        this.split(this.state)
        // A value set from the server (a reset, $set) re-splits into the halves.
        this.$watch('state', (value) => {
            if (value !== this.joined()) this.split(value)
        })
    },

    split(value) {
        const [date, time] = String(value ?? '').split(/[T ]/)
        this.date = date ?? ''
        // The select's options carry the state's own clock shape (H:i or H:i:s).
        this.time = time ? time.slice(0, config.hasSeconds ? 8 : 5) : ''
    },

    joined() {
        return this.date && this.time ? `${this.date}T${this.time}` : null
    },

    // Called from both halves' change events.
    commit() {
        if (this.date && this.time) {
            this.state = this.joined()
        } else if (! this.date && ! this.time) {
            this.state = null
        }
    },
})

export default wireNativeDateTime
