import wireDateTimePicker from './fields/date-time-picker'
import wireTimePicker from './fields/time-picker'
import wireTagsInput from './fields/tags'
import wireRating from './fields/rating'
import wireRichEditor from './fields/rich-editor'
import wireMarkdownEditor from './fields/markdown-editor'

/**
 * The wire-forms field controllers.
 *
 * Each of these was an inline `x-data` object literal in its field view, which
 * meant every instance on a page shipped the whole body again: a DateTimePicker
 * measured 28.4 kB of HTML per field against a TextInput's 1.6 kB, and on a
 * 20-Select page the inlined blobs were 45.7% of the document. Registering them
 * as `Alpine.data()` factories leaves only the per-instance config in the markup.
 *
 * They live in one bundle rather than one per field because a page with a date
 * picker and a tag input would otherwise fetch two files, and because the
 * pickers share the typing parser.
 *
 * Registration is unconditional AND on `alpine:init`, per architecture/assets.md:
 * `alpine:init` fires once per document and a `wire:navigate` visit never
 * restarts Alpine, so a bundle arriving with a new page and listening only for
 * that event registers nothing.
 */

// The `registered` guard is load-bearing: `@wireStackScripts` and a per-surface
// partial can both emit the same src, and the browser executes it twice.
let registered = false

const registerWireFormsFields = () => {
    if (registered || ! window.Alpine) return
    registered = true

    window.Alpine.data('wireDateTimePicker', wireDateTimePicker)
    window.Alpine.data('wireTimePicker', wireTimePicker)
    window.Alpine.data('wireTagsInput', wireTagsInput)
    window.Alpine.data('wireRating', wireRating)
    window.Alpine.data('wireRichEditor', wireRichEditor)
    window.Alpine.data('wireMarkdownEditor', wireMarkdownEditor)
}

if (window.Alpine) registerWireFormsFields()
else document.addEventListener('alpine:init', registerWireFormsFields)
