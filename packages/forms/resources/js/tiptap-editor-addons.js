// Opt-in TipTap extensions, split out of the core editor bundle. With esbuild
// ESM code-splitting the shared @tiptap/core + ProseMirror core lands in a chunk
// both this addon and the base editor import, so enabling tables loads the ~12 KB
// gzip of extra extensions, NOT a second copy of the ~270 KB core.
import TextAlign from '@tiptap/extension-text-align'
import Highlight from '@tiptap/extension-highlight'
import Image from '@tiptap/extension-image'
// v3 dropped the default export from @tiptap/extension-table (Table is now a
// named export alongside TableKit); the row/header/cell packages keep theirs.
import { Table } from '@tiptap/extension-table'
import TableRow from '@tiptap/extension-table-row'
import TableHeader from '@tiptap/extension-table-header'
import TableCell from '@tiptap/extension-table-cell'

window.WireTiptapAddons = { TextAlign, Highlight, Image, Table, TableRow, TableHeader, TableCell }
