/**
 * CodeEditor's controller: a textarea that keeps its indentation.
 *
 * Tab in a textarea moves focus by default, which makes it unusable for code —
 * so the key is intercepted, four spaces are spliced in at the selection, and
 * the caret is put back where the typist expects it (after the spaces, not at
 * the end of the value the morph produced).
 *
 * `lines` drives the gutter; it is derived rather than stored so the numbers
 * cannot drift from the content.
 */
const wireCodeEditor = (config = {}) => ({
    content: config.state,

    get lines() {
        return (this.content || '').split('\n')
    },

    onTab(event) {
        event.preventDefault()

        const el = event.target
        const start = el.selectionStart
        const end = el.selectionEnd

        this.content = this.content.substring(0, start) + '    ' + this.content.substring(end)

        this.$nextTick(() => {
            el.selectionStart = el.selectionEnd = start + 4
        })
    },
})

export default wireCodeEditor
