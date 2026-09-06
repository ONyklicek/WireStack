import wireCollapsibleItems from './collapsible-items'

/**
 * Builder's controller: the collapse state a Repeater also has, plus the one
 * thing only a Builder needs — the block picker.
 *
 * Composed rather than copied: folding an item is the same behaviour in both
 * fields, and `adding` is the whole difference between them.
 */
const wireBuilderBlocks = (config = {}) => ({
    ...wireCollapsibleItems(config),

    /** Whether the "add a block" menu is open. */
    adding: false,
})

export default wireBuilderBlocks
