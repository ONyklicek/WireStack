/**
 * MarkdownEditor: a textarea with a toolbar and a rendered preview.
 *
 * The renderer below used to live inside an `x-data` attribute, where the HTML
 * parser owned it before JavaScript ever saw it — every quote had to be written
 * as an entity (a raw `"` ended the attribute mid-regex and killed the component
 * with an invalid-regexp error), and every escape had to be written twice over to
 * survive decoding. That second rule was load-bearing for the sanitiser: written
 * once, `.replace(/&/g, '&amp;')` decoded to `replace(ampersand, ampersand)` — a
 * no-op that let raw HTML reach `x-html` unescaped. In a real `.js` file none of
 * that applies and the code says what it means.
 */
const wireMarkdownEditor = (config = {}) => ({
    content: config.state,
    tab: 'write',
    livePreview: config.livePreview ?? false,

    renderMd(text) {
        if (! text) return ''

        let html = text
            // Neutralise raw HTML first, including the double quote so a link URL
            // can never break out of the href attribute (DOM-XSS).
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
            .replace(/^### (.+)$/gm, '<h3 class="text-base font-semibold mt-3 mb-1">$1</h3>')
            .replace(/^## (.+)$/gm, '<h2 class="text-lg font-bold mt-4 mb-1">$1</h2>')
            .replace(/^# (.+)$/gm, '<h1 class="text-xl font-bold mt-4 mb-2">$1</h1>')
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.+?)\*/g, '<em>$1</em>')
            .replace(/~~(.+?)~~/g, '<del>$1</del>')
            .replace(/`([^`\n]+)`/g, '<code class="bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded text-xs font-mono">$1</code>')
            .replace(/\[(.+?)\]\((.+?)\)/g, (m, label, url) => {
                // Only allow safe URL schemes — block javascript:/data: etc.
                const safe = /^(https?:|mailto:|#|\/)/i.test(url.trim()) ? url : '#'
                return '<a href="' + safe + '" class="text-primary-600 hover:underline" target="_blank" rel="noopener noreferrer">' + label + '</a>'
            })
            .replace(/^> (.+)$/gm, '<blockquote class="border-l-4 border-gray-300 dark:border-gray-600 pl-3 text-gray-600 dark:text-gray-400 italic">$1</blockquote>')
            .replace(/^- (.+)$/gm, '<li class="ml-4 list-disc">$1</li>')
            .replace(/^\d+\. (.+)$/gm, '<li class="ml-4 list-decimal">$1</li>')
            .replace(/\n\n/g, '</p><p class="mb-2">')

        return '<p class="mb-2">' + html + '</p>'
    },

    insertAround(before, after) {
        const el = this.$refs.editor
        const start = el.selectionStart, end = el.selectionEnd
        const selected = this.content.substring(start, end) || 'text'
        this.content = this.content.substring(0, start) + before + selected + after + this.content.substring(end)
        this.$nextTick(() => {
            el.focus()
            el.setSelectionRange(start + before.length, start + before.length + selected.length)
        })
    },

    insertLine(prefix) {
        const el = this.$refs.editor
        const lineStart = this.content.lastIndexOf('\n', el.selectionStart - 1) + 1
        this.content = this.content.substring(0, lineStart) + prefix + this.content.substring(lineStart)
        this.$nextTick(() => el.focus())
    },
})

export default wireMarkdownEditor
