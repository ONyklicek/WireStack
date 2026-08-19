/**
 * RichEditor's contenteditable controller.
 *
 * `statePath` rather than an entangled value: this editor writes through a hidden
 * textarea (`wire:model` on `$refs.textarea`) and reads the property back with
 * `$wire.get()` / `$wire.$watch()`, because a contenteditable cannot be bound
 * two-way without the caret jumping on every keystroke.
 */
const wireRichEditor = (config = {}) => ({
    content: '',
    toolbar: config.toolbar ?? [],
    statePath: config.statePath,
    linkPrompt: config.linkPrompt ?? '',
    activeFormats: {},

    init() {
        const initial = this.$wire.get(this.statePath)
        if (initial) {
            this.content = initial
            this.$nextTick(() => { this.$refs.editor.innerHTML = this.content })
        }

        this.$wire.$watch(this.statePath, (val) => {
            if (document.activeElement !== this.$refs.editor) {
                this.content = val || ''
                this.$refs.editor.innerHTML = this.content
            }
        })
    },

    onInput() {
        this.content = this.$refs.editor.innerHTML
        this.$refs.textarea.value = this.content
        this.$refs.textarea.dispatchEvent(new Event('input'))
        this.updateActiveFormats()
    },

    exec(command, value = null) {
        this.$refs.editor.focus()
        document.execCommand(command, false, value)
        this.onInput()
    },

    updateActiveFormats() {
        this.activeFormats = {
            bold: document.queryCommandState('bold'),
            italic: document.queryCommandState('italic'),
            underline: document.queryCommandState('underline'),
            strikeThrough: document.queryCommandState('strikeThrough'),
            insertOrderedList: document.queryCommandState('insertOrderedList'),
            insertUnorderedList: document.queryCommandState('insertUnorderedList'),
        }
    },

    insertLink() {
        const url = prompt(this.linkPrompt)
        if (url) {
            this.exec('createLink', url)
        }
    },

    hasButton(name) {
        return this.toolbar.includes(name)
    },
})

export default wireRichEditor
