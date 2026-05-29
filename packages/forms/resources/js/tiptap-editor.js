import { Editor } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Underline from '@tiptap/extension-underline'
import Placeholder from '@tiptap/extension-placeholder'
import TextAlign from '@tiptap/extension-text-align'
import Highlight from '@tiptap/extension-highlight'
import Image from '@tiptap/extension-image'
import Table from '@tiptap/extension-table'
import TableRow from '@tiptap/extension-table-row'
import TableHeader from '@tiptap/extension-table-header'
import TableCell from '@tiptap/extension-table-cell'
import CharacterCount from '@tiptap/extension-character-count'

const tiptapEditor = (config = {}) => {
    // IMPORTANT: the Editor instance is kept OUT of Alpine's reactive data (it is
    // a closure variable, not `this.editor`). If it were a reactive property,
    // Alpine would wrap the editor and its ProseMirror state in Proxies, and
    // ProseMirror's identity checks would then fail with
    // "RangeError: Applying a mismatched transaction" on every command.
    let editor = null
    // Last value pushed to Livewire — lets the $watch ignore the echo and avoid a
    // feedback loop that would re-enter ProseMirror mid-transaction.
    let lastEmitted = null

    const read = () => config.outputFormat === 'json'
        ? JSON.stringify(editor.getJSON())
        : editor.getHTML()

    return {
        // Reactive heartbeat: bumped on every editor change/selection so toolbar
        // active states (which read it in isActive()) re-render. The editor itself
        // stays non-reactive.
        updatedAt: Date.now(),
        characterCount: 0,

        init() {
            const mount = this.$refs.editorContent

            // Remove any stale ProseMirror left in the (wire:ignore'd) mount so we
            // never create a second editor over an existing view.
            mount.querySelector('.ProseMirror')?.remove()

            const initialRaw = this.$wire.get(config.wireAttribute) ?? ''
            const initialContent = config.outputFormat === 'json'
                ? safeParse(initialRaw)
                : (initialRaw || '')

            editor = new Editor({
                element: mount,
                extensions: buildTiptapExtensions(config),
                content: initialContent,
                editable: !config.disabled && !config.readOnly,
                onCreate: ({ editor: ed }) => {
                    if (config.maxLength) {
                        this.characterCount = ed.storage.characterCount?.characters() ?? 0
                    }
                },
                onUpdate: ({ editor: ed }) => {
                    this.updatedAt = Date.now()

                    const value = config.outputFormat === 'json'
                        ? JSON.stringify(ed.getJSON())
                        : ed.getHTML()
                    lastEmitted = value
                    // Sync to Livewire via the hidden input carrying wire:model.
                    this.$refs.hiddenInput.value = value
                    this.$refs.hiddenInput.dispatchEvent(new Event('input', { bubbles: true }))

                    if (config.maxLength) {
                        this.characterCount = ed.storage.characterCount?.characters() ?? 0
                    }
                },
                onSelectionUpdate: () => { this.updatedAt = Date.now() },
                onFocus: () => { this.updatedAt = Date.now() },
                onBlur: () => { this.updatedAt = Date.now() },
            })

            // Reflect server-driven value changes (form reset / fill) only — never
            // our own edits, and never while the user is editing.
            this.$wire.$watch(config.wireAttribute, (val) => {
                if (!editor || editor.isFocused) return
                if (val === lastEmitted) return
                if (val === read()) return

                editor.commands.setContent(
                    config.outputFormat === 'json' ? safeParse(val) : (val || ''),
                    false,
                )
            })
        },

        destroy() {
            editor?.destroy()
            editor = null
        },

        // ─── Active state ───────────────────────────────────────────

        isActive(name, attrs = {}) {
            this.updatedAt // create a reactive dependency so :class updates
            return editor ? editor.isActive(name, attrs) : false
        },

        // ─── Formatting commands ────────────────────────────────────

        toggleBold()        { editor?.chain().focus().toggleBold().run() },
        toggleItalic()      { editor?.chain().focus().toggleItalic().run() },
        toggleUnderline()   { editor?.chain().focus().toggleUnderline().run() },
        toggleStrike()      { editor?.chain().focus().toggleStrike().run() },
        toggleCode()        { editor?.chain().focus().toggleCode().run() },
        toggleHighlight()   { editor?.chain().focus().toggleHighlight().run() },
        toggleBulletList()  { editor?.chain().focus().toggleBulletList().run() },
        toggleOrderedList() { editor?.chain().focus().toggleOrderedList().run() },
        toggleBlockquote()  { editor?.chain().focus().toggleBlockquote().run() },
        toggleCodeBlock()   { editor?.chain().focus().toggleCodeBlock().run() },
        setHeading(level)   { editor?.chain().focus().toggleHeading({ level }).run() },
        setAlign(align)     { editor?.chain().focus().setTextAlign(align).run() },
        undo()              { editor?.chain().focus().undo().run() },
        redo()              { editor?.chain().focus().redo().run() },

        insertLink() {
            const prev = editor?.getAttributes('link').href ?? ''
            const url = prompt('URL', prev || 'https://')
            if (url === null) return
            if (url === '') {
                editor?.chain().focus().unsetLink().run()
            } else {
                editor?.chain().focus()
                    .extendMarkRange('link')
                    .setLink({ href: url, target: '_blank' })
                    .run()
            }
        },

        insertImage() {
            const url = prompt('Image URL')
            if (url) editor?.chain().focus().setImage({ src: url }).run()
        },

        insertTable() {
            editor?.chain().focus()
                .insertTable({ rows: 3, cols: 3, withHeaderRow: true })
                .run()
        },

        addColumnBefore()  { editor?.chain().focus().addColumnBefore().run() },
        addColumnAfter()   { editor?.chain().focus().addColumnAfter().run() },
        deleteColumn()     { editor?.chain().focus().deleteColumn().run() },
        addRowBefore()     { editor?.chain().focus().addRowBefore().run() },
        addRowAfter()      { editor?.chain().focus().addRowAfter().run() },
        deleteRow()        { editor?.chain().focus().deleteRow().run() },
        deleteTable()      { editor?.chain().focus().deleteTable().run() },
    }
}

function buildTiptapExtensions(config) {
    const extensions = [
        StarterKit.configure({ heading: { levels: [1, 2, 3] } }),
        Link.configure({
            openOnClick: false,
            HTMLAttributes: { class: 'text-primary-600 underline cursor-pointer' },
        }),
        Underline,
        Placeholder.configure({ placeholder: config.placeholder ?? '' }),
    ]

    if (config.withTextAlign) {
        extensions.push(TextAlign.configure({ types: ['heading', 'paragraph'] }))
    }
    if (config.withHighlight) {
        extensions.push(Highlight)
    }
    if (config.withImages) {
        extensions.push(Image.configure({ inline: false }))
    }
    if (config.withTables) {
        extensions.push(
            Table.configure({ resizable: true }),
            TableRow,
            TableHeader,
            TableCell,
        )
    }
    if (config.maxLength) {
        extensions.push(CharacterCount.configure({ limit: config.maxLength }))
    }

    return extensions
}

function safeParse(value) {
    if (!value) return {}
    try { return JSON.parse(value) } catch { return {} }
}

// ─── Self-registration ──────────────────────────────────────────
// The package ships this file pre-bundled and the Blade view injects it, so
// consumers need no build step or manual import.
let registered = false
function registerTiptapEditor() {
    if (registered || !window.Alpine) return
    registered = true
    window.Alpine.data('tiptapEditor', tiptapEditor)
}

if (window.Alpine) {
    // Alpine already started (e.g. script loaded after a Livewire navigation).
    registerTiptapEditor()
} else {
    document.addEventListener('alpine:init', registerTiptapEditor)
}
