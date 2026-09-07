import { Editor } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Placeholder from '@tiptap/extension-placeholder'
import CharacterCount from '@tiptap/extension-character-count'

// TipTap v3 folded Link and Underline into StarterKit, so they are configured
// through StarterKit.configure() below rather than registered as standalone
// extensions — adding them again would trip the duplicate-extension guard.

// The opt-in extensions (tables/images/highlight/text-align) default OFF yet were
// bundled into every editor page. They now ship in a separate ESM chunk
// (tiptap-editor-addons.js) that publishes window.WireTiptapAddons and is injected
// via @assets only when a field enables one of them. esbuild --splitting keeps the
// shared @tiptap/core + ProseMirror in one chunk both bundles import, so a table
// page loads the extra extensions once, not a duplicate core.

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

            const bound = this.$wire.get(config.wireAttribute) ?? ''
            // ->default() is normally already in the state bag (the form runtime
            // seeds it), so this only fires for a host that never seeded — a null
            // column, a hand-bound property. A cleared editor stores '<p></p>',
            // not '', so re-opening one the user emptied does NOT re-seed it.
            const seeded = isBlank(bound) && config.default ? config.default : bound
            const initialContent = toContent(seeded, config.outputFormat)

            editor = new Editor({
                element: mount,
                extensions: buildTiptapExtensions(config, this),
                content: initialContent,
                editable: !config.disabled && !config.readOnly,
                onCreate: ({ editor: ed }) => {
                    if (config.maxLength) {
                        this.characterCount = ed.storage.characterCount?.characters() ?? 0
                    }
                },
                onUpdate: ({ editor: ed }) => {
                    this.updatedAt = Date.now()

                    this.syncValue(config.outputFormat === 'json'
                        ? JSON.stringify(ed.getJSON())
                        : ed.getHTML())

                    if (config.maxLength) {
                        this.characterCount = ed.storage.characterCount?.characters() ?? 0
                    }
                },
                onSelectionUpdate: () => { this.updatedAt = Date.now() },
                onFocus: () => { this.updatedAt = Date.now() },
                onBlur: () => { this.updatedAt = Date.now() },
            })

            // A default we seeded ourselves is only on screen — push it into
            // Livewire (after the tree is wired, so wire:model is listening) or
            // saving an untouched form would store nothing. Read it back from the
            // editor so the stored value is the document TipTap actually parsed.
            if (seeded !== bound) {
                this.$nextTick(() => { if (editor) this.syncValue(read()) })
            }

            // Reflect server-driven value changes (form reset / fill) only — never
            // our own edits, and never while the user is editing.
            this.$wire.$watch(config.wireAttribute, (val) => {
                if (!editor || editor.isFocused) return
                if (val === lastEmitted) return
                if (val === read()) return

                // emitUpdate:false — never re-fire onUpdate for a server-driven
                // fill, or we'd echo the value straight back into Livewire. In
                // TipTap v3 the second arg is an options object (a bare `false`
                // would be ignored and emitUpdate would default back to true).
                editor.commands.setContent(
                    toContent(val, config.outputFormat),
                    { emitUpdate: false },
                )
            })
        },

        /** Push a value to Livewire through the hidden input carrying wire:model. */
        syncValue(value) {
            lastEmitted = value
            this.$refs.hiddenInput.value = value
            this.$refs.hiddenInput.dispatchEvent(new Event('input', { bubbles: true }))
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
            const url = prompt(config.prompts?.linkUrl ?? 'Link URL', prev || 'https://')
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
            // Ask the page for a media picker before asking the person for a URL.
            //
            // The editor cannot require the media library: `wire-forms` sits
            // below it in the package graph and an application may not have
            // installed it at all. So it *offers* the job, and whoever handles it
            // claims the event by calling preventDefault(). Un-claimed, this
            // falls through to the prompt it has always used — which is what an
            // application with no media module still gets.
            const token = 'tiptap-' + Math.random().toString(36).slice(2)

            const onPicked = (event) => {
                if (event.detail?.token !== token) return

                window.removeEventListener('wire-media-picker:picked', onPicked)

                const file = (event.detail?.files ?? [])[0]
                if (! file?.url) return

                // The alt text comes from the library, where it was written once
                // about the file itself. Asking again at every insertion is how
                // half the images on a site end up with none.
                // The id travels with the picture. Without it the saved HTML is
                // a bare URL and the library reports zero uses for a photograph
                // that is in twelve articles — a false all-clear in front of
                // every delete and every replace (ADR 0034).
                editor?.chain().focus().setImage({
                    src: file.url,
                    alt: file.alt ?? '',
                    title: file.title ?? null,
                    mediaId: file.id ?? null,
                }).run()
            }

            window.addEventListener('wire-media-picker:picked', onPicked)

            const request = new CustomEvent('wire-media-picker:open', {
                cancelable: true,
                detail: { token, multiple: false, accepts: 'image/' },
            })

            window.dispatchEvent(request)

            if (request.defaultPrevented) return

            window.removeEventListener('wire-media-picker:picked', onPicked)

            const url = prompt(config.prompts?.imageUrl ?? 'Image URL')
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

function buildTiptapExtensions(config, component) {
    const extensions = [
        StarterKit.configure({
            heading: { levels: [1, 2, 3] },
            link: {
                openOnClick: false,
                HTMLAttributes: { class: 'text-primary-600 underline cursor-pointer' },
            },
        }),
        Placeholder.configure({ placeholder: config.placeholder ?? '' }),
    ]

    // Opt-in extensions come from the addon chunk's registry. The `&& addons.X`
    // guard is defensive: if a field enables tables but the addon somehow did not
    // load, the editor still boots (without tables) rather than throwing.
    const addons = window.WireTiptapAddons ?? {}

    if (config.withTextAlign && addons.TextAlign) {
        extensions.push(addons.TextAlign.configure({ types: ['heading', 'paragraph'] }))
    }
    if (config.withHighlight && addons.Highlight) {
        extensions.push(addons.Highlight)
    }
    if (config.withImages && addons.Image) {
        extensions.push(addons.Image.configure({ inline: false }))
    }
    if (config.withTables && addons.Table) {
        extensions.push(
            addons.Table.configure({ resizable: true }),
            addons.TableRow,
            addons.TableHeader,
            addons.TableCell,
        )
    }
    if (config.maxLength) {
        extensions.push(CharacterCount.configure({ limit: config.maxLength }))
    }

    // One configured node per trigger: `@` and `#` are separate lists with
    // separate sources, and TipTap keys a suggestion plugin by its char.
    const mentions = window.WireTiptapMentions ?? {}

    if (mentions.Mention) {
        for (const mention of config.mentions ?? []) {
            extensions.push(mentions.Mention.extend({ name: `mention${mention.trigger}` }).configure({
                suggestion: buildSuggestion(mention, config, component),
            }))
        }
    }

    return extensions
}

/**
 * Suggestion wiring for one trigger.
 *
 * Everything that decides *what* can be mentioned stays on the server — the
 * field is re-resolved by state path and answers with its own scoped sources —
 * so this only asks and draws. An empty term is never sent: the endpoint would
 * answer it with "every user we have".
 */
function buildSuggestion(mention, config, component) {
    return {
        char: mention.trigger,
        allowSpaces: mention.allowSpaces === true,

        items: async ({ query }) => {
            if (!query) return []

            try {
                return await component.$wire.searchEditorMentions(config.statePath, mention.trigger, query)
            } catch {
                // A failed lookup closes the list rather than freezing it open.
                return []
            }
        },

        command: ({ editor, range, props }) => {
            editor.chain().focus().insertContentAt(range, [
                {
                    type: `mention${mention.trigger}`,
                    attrs: {
                        id: props.id,
                        mentionType: props.type,
                        trigger: mention.trigger,
                        label: props.label,
                    },
                },
                { type: 'text', text: ' ' },
            ]).run()
        },

        render: createSuggestionPopup,
    }
}

/**
 * The suggestion list, drawn without a popup library.
 *
 * `wire-core` already owns floating placement for dropdowns, but this list is
 * inside a `wire:ignore`d ProseMirror mount and follows a caret rather than an
 * element, so it positions against the range rect TipTap hands over and keeps
 * its own keyboard handling. Rows are grouped by source: several models share
 * one trigger, and a heading is an honest way to say a row came from a
 * different table without claiming to rank the two against each other.
 */
function createSuggestionPopup() {
    let element = null
    let items = []
    let selected = 0
    let command = null

    const close = () => {
        element?.remove()
        element = null
        items = []
        selected = 0
    }

    const paint = () => {
        if (!element) return

        element.innerHTML = ''

        if (items.length === 0) {
            close()
            return
        }

        let group = null

        items.forEach((item, index) => {
            if (item.group && item.group !== group) {
                group = item.group
                const heading = document.createElement('div')
                heading.className = 'wire-mention-group'
                heading.textContent = item.group
                element.appendChild(heading)
            }

            const row = document.createElement('button')
            row.type = 'button'
            row.className = 'wire-mention-item' + (index === selected ? ' is-selected' : '')
            row.textContent = item.label
            // mousedown, not click: click fires after the editor has already lost
            // the selection the command needs to replace.
            row.addEventListener('mousedown', (event) => {
                event.preventDefault()
                command?.(item)
            })
            element.appendChild(row)
        })
    }

    const place = (clientRect) => {
        const rect = clientRect?.()
        if (!element || !rect) return

        const margin = 6
        const below = window.innerHeight - rect.bottom
        const height = element.offsetHeight

        element.style.left = `${Math.min(rect.left + window.scrollX, window.innerWidth - element.offsetWidth - margin)}px`
        element.style.top = below < height + margin
            ? `${rect.top + window.scrollY - height - margin}px`
            : `${rect.bottom + window.scrollY + margin}px`
    }

    return {
        onStart(props) {
            items = props.items ?? []
            selected = 0
            command = props.command

            if (items.length === 0) return

            element = document.createElement('div')
            element.className = 'wire-mention-suggestions'
            element.setAttribute('role', 'listbox')
            document.body.appendChild(element)

            paint()
            place(props.clientRect)
        },

        onUpdate(props) {
            items = props.items ?? []
            command = props.command
            selected = 0

            if (items.length === 0) {
                close()
                return
            }

            if (!element) {
                this.onStart(props)
                return
            }

            paint()
            place(props.clientRect)
        },

        onKeyDown(props) {
            if (!element) return false

            const key = props.event.key

            if (key === 'Escape') {
                close()
                return true
            }

            if (key === 'ArrowDown') {
                selected = (selected + 1) % items.length
                paint()
                return true
            }

            if (key === 'ArrowUp') {
                selected = (selected - 1 + items.length) % items.length
                paint()
                return true
            }

            if (key === 'Enter' || key === 'Tab') {
                command?.(items[selected])
                return true
            }

            return false
        },

        onExit: close,
    }
}

/** Nothing to show: no value at all, or an empty JSON document string. */
function isBlank(value) {
    return value === null || value === undefined || value === '' || value === '{}'
}

/**
 * Turn a stored value into something Editor/setContent accepts.
 *
 * Under outputJson() the value is normally a JSON document string — but a
 * ->default() is written as markup ('<p>Draft</p>'), so anything that does not
 * parse as JSON is handed to TipTap as HTML and parsed into a document instead
 * of being dropped for an empty editor.
 */
function toContent(value, outputFormat) {
    if (outputFormat !== 'json') return value || ''
    if (!value) return {}

    try { return JSON.parse(value) } catch { return value }
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
