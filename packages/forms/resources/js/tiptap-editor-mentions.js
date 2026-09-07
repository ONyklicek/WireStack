// The mention node, split out of the core editor bundle exactly like the
// extension addon: an editor with tables and no mentions should not download a
// suggestion engine it never opens. esbuild --splitting keeps the shared
// @tiptap/core + ProseMirror in the chunk every entry imports, so this costs the
// mention node and @tiptap/suggestion, not a second core.
import { mergeAttributes } from '@tiptap/core'
import Mention from '@tiptap/extension-mention'

// The stock node stores `id` + `label` and renders the label as the truth. Ours
// stores an identity and treats the label as a fallback: a mention is written as
// a morph type plus an id, because one `#` may stand for articles, pages and
// products at once, and `12` alone would not say which. The text stays in the
// document only so a record that later disappears still reads as something.
const WireMention = Mention.extend({
    addAttributes() {
        return {
            id: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-id'),
                renderHTML: (attrs) => (attrs.id ? { 'data-id': attrs.id } : {}),
            },
            mentionType: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-mention-type'),
                renderHTML: (attrs) => (attrs.mentionType ? { 'data-mention-type': attrs.mentionType } : {}),
            },
            trigger: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-mention-trigger'),
                renderHTML: (attrs) => (attrs.trigger ? { 'data-mention-trigger': attrs.trigger } : {}),
            },
            // Read back from the text, never written as an attribute — the
            // rendered span already carries it, and storing it twice is how the
            // two copies start disagreeing.
            label: {
                default: null,
                parseHTML: (element) => element.textContent?.replace(/^\W/, '') ?? null,
                renderHTML: () => ({}),
            },
        }
    },

    renderHTML({ node, HTMLAttributes }) {
        return [
            'span',
            mergeAttributes({ 'data-type': 'mention', class: 'wire-mention' }, HTMLAttributes),
            `${node.attrs.trigger ?? ''}${node.attrs.label ?? ''}`,
        ]
    },

    renderText({ node }) {
        return `${node.attrs.trigger ?? ''}${node.attrs.label ?? ''}`
    },
})

window.WireTiptapMentions = { Mention: WireMention }
