// Opt-in TipTap extensions, split out of the core editor bundle. With esbuild
// ESM code-splitting the shared @tiptap/core + ProseMirror core lands in a chunk
// both this addon and the base editor import, so enabling tables loads the ~12 KB
// gzip of extra extensions, NOT a second copy of the ~270 KB core.
import TextAlign from '@tiptap/extension-text-align'
import Highlight from '@tiptap/extension-highlight'
import BaseImage from '@tiptap/extension-image'
// v3 dropped the default export from @tiptap/extension-table (Table is now a
// named export alongside TableKit); the row/header/cell packages keep theirs.
import { Table } from '@tiptap/extension-table'
import TableRow from '@tiptap/extension-table-row'
import TableHeader from '@tiptap/extension-table-header'
import TableCell from '@tiptap/extension-table-cell'

// The stock image node stores a URL and nothing else, so a picture inserted from
// a media library is, in the saved HTML, indistinguishable from one somebody
// pasted — and the library cannot answer "where is this file used". The picker
// already hands over the row's id; this is the attribute that keeps it.
//
// `wire-forms` learns nothing about what the id means and still requires no
// media package: it stops discarding a field it is already given. With no
// library installed, nothing answers the picker event and the attribute is never
// written. See ADR 0034.
const Image = BaseImage.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            mediaId: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-media-id'),
                renderHTML: (attributes) => (
                    attributes.mediaId ? { 'data-media-id': attributes.mediaId } : {}
                ),
            },
        }
    },
})

window.WireTiptapAddons = { TextAlign, Highlight, Image, Table, TableRow, TableHeader, TableCell }
