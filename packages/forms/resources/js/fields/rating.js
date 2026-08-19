/**
 * Star-rating controller. `state` comes in from the x-data expression because
 * `@entangle` compiles to an Alpine magic, in scope only there.
 */
const wireRating = (config = {}) => ({
    rating: config.state,
    hovered: 0,
    allowHalf: config.allowHalf ?? false,
    clearable: config.clearable ?? false,
    disabled: config.disabled ?? false,

    setRating(val) {
        if (this.disabled) return
        if (this.clearable && this.rating === val) {
            this.rating = 0
        } else {
            this.rating = val
        }
    },

    setHalf(index, event) {
        if (! this.allowHalf || this.disabled) return
        const rect = event.currentTarget.getBoundingClientRect()
        const half = event.clientX - rect.left < rect.width / 2
        this.hovered = half ? index - 0.5 : index
    },

    clickStar(index, event) {
        if (this.allowHalf) {
            const rect = event.currentTarget.getBoundingClientRect()
            const half = event.clientX - rect.left < rect.width / 2
            this.setRating(half ? index - 0.5 : index)
        } else {
            this.setRating(index)
        }
    },

    isFilled(index) {
        const active = this.hovered || this.rating
        return index <= active
    },

    isHalfFilled(index) {
        const active = this.hovered || this.rating
        return active >= index - 0.5 && active < index
    },
})

export default wireRating
