# TiptapEditor

Full-featured rich text editor built on [TipTap](https://tiptap.dev/) / ProseMirror. Configurable toolbar, optional extensions (tables, images, text align, highlight), and HTML or JSON output.

```php
use NyonCode\WireForms\Components\TiptapEditor;
```

## Setup

None. The editor's JavaScript ships **pre-bundled inside the package** and the
field's Blade view injects it automatically. There is no npm install, no build
step, and no `app.js` import to add — just use the field and it works out of the box.

The editor is **code-split**: the core bundle (TipTap core + the always-on
extensions) is served at `/wire-forms/tiptap/tiptap-editor.js`, and the opt-in
extensions (`withTables()` / `withImages()` / `withHighlight()` / `withTextAlign()`)
ship in a separate addon bundle that is only loaded when a field on the page
enables one of them. Both share one core chunk, so a page without those extensions
downloads less, and enabling tables never ships a second copy of the editor core.
The `<script type="module">` tags are injected once per page via Livewire's
`@assets` directive; they register the Alpine component `tiptapEditor` that the
view relies on (Alpine ships with Livewire).

> **Publishing the asset (optional).** To have your web server serve the files
> instead of the package route, publish them with:
> ```bash
> php artisan vendor:publish --tag=laravel-assets --force
> ```
> This copies the bundles to `public/vendor/wire-forms/` — the whole stack's, not
> just this package's — and the editor emits those paths from then on, cache-buster
> included. The publish mirrors `dist/` verbatim, so the entries keep resolving their
> shared chunk relative to `vendor/wire-forms/tiptap/`. See
> [Getting Started → JavaScript Assets](../../getting-started.md#javascript-assets).

> **Contributors.** The bundles are generated from
> `packages/forms/resources/js/tiptap-editor.js` and `tiptap-editor-addons.js`, and
> committed (with the shared chunk) to `packages/forms/dist/tiptap/`. Rebuild them
> after editing the source with:
> ```bash
> npm run build:forms-assets
> ```

---

## Basic Usage

```php
TiptapEditor::make('content')
```

## Default Content

The editor opens on the field's `->default()` — the canonical default every
component has, no editor-specific method. It is **markup, not plain text**, so a
template arrives pre-formatted:

```php
TiptapEditor::make('minutes')
    ->default('<h2>Meeting notes</h2><p>Some <strong>text</strong>.</p><ul><li>First point</li></ul>')
```

How it resolves, in order:

1. **The form runtime seeds it.** `fill()` (and a modal action's initial state)
   writes `->default()` into the state bag for any key the caller did not
   provide, so the editor simply opens on a value that is already there.
2. **The editor seeds it when the host did not** — a `null` column, a property
   bound by hand — applying the default whenever the bound value is empty and
   pushing the parsed document back into Livewire, so saving a form the user
   never touched stores the template rather than nothing.
3. **A cleared editor is not empty.** Emptying the content stores `<p></p>`, so
   re-opening a document the user deliberately cleared does *not* bring the
   default back. On an edit form where the column is genuinely `null`, add
   `->defaultOnNull()` to let the default fill it server-side too.

Under `->outputJson()` the default may be a TipTap JSON document string, or the
same HTML — HTML is parsed into a document and stored as JSON either way.

## Custom Toolbar

```php
TiptapEditor::make('content')
    ->toolbarButtons([
        'bold', 'italic', 'underline',
        '|',
        'h2', 'h3',
        '|',
        'bulletList', 'orderedList',
        '|',
        'link', 'undo', 'redo',
    ])
```

Use `'|'` as a visual separator between groups.

## Disable Specific Buttons

```php
TiptapEditor::make('content')
    ->disableToolbarButtons(['codeBlock', 'code'])
```

## No Toolbar

```php
TiptapEditor::make('content')
    ->disableAllToolbarButtons()
```

## Extensions

Enable optional extensions individually:

```php
TiptapEditor::make('content')
    ->withTables()       // table insertion + editing
    ->withImages()       // image insertion (via URL prompt)
    ->withTextAlign()    // left / center / right alignment buttons
    ->withHighlight()    // text highlight button
```

When an extension is enabled, its toolbar button is appended automatically.

## Output Format

```php
// Default: HTML string stored in the model
TiptapEditor::make('body')->outputHtml()

// Store as TipTap JSON document (serialised as a JSON string)
TiptapEditor::make('body')->outputJson()
```

## Character Limit

```php
TiptapEditor::make('summary')
    ->maxLength(2000)    // shows a live counter, enforced by CharacterCount extension
```

## Height

```php
TiptapEditor::make('content')
    ->minHeight(400)     // minimum height in pixels (default 240)
```

## Read-Only / Disabled

```php
TiptapEditor::make('content')
    ->readOnly()
    ->disabled(fn () => ! $this->canEdit)
```

## Localization

The editor carries no English of its own. Toolbar tooltips, the heading titles
and the browser prompts opened by the link and image buttons all resolve from
`wire-forms::fields.editor.*`, so the field follows `app()->getLocale()`. English
(`en`) and Czech (`cs`) ship with the package — a Czech app shows *Tučné*,
*Odrážkový seznam*, *Nadpis 2*, and prompts *URL odkazu*.

The prompt titles are resolved in PHP and handed to the editor's Alpine config,
which is why a locale change reaches strings that live inside the JS bundle.

[RichEditor](rich-editor.md#localization) and
[MarkdownEditor](markdown-editor.md#localization) title their toolbars from the
very same keys, so the three editors read alike in every locale.

Reword a string, or add a locale, by publishing the translations and editing
`lang/vendor/wire-forms/{locale}/fields.php`:

```bash
php artisan vendor:publish --tag=wire-forms::translations
```

The button glyphs stay `H1` / `H2` / `H3` in every locale — those are symbols,
not words; the tooltip is what gets translated.

## Available Toolbar Buttons

| Key | Description |
|-----|-------------|
| `bold` | Bold |
| `italic` | Italic |
| `underline` | Underline |
| `strike` | Strikethrough |
| `code` | Inline code |
| `highlight` | Highlight (requires `withHighlight()`) |
| `h1` | Heading 1 |
| `h2` | Heading 2 |
| `h3` | Heading 3 |
| `bulletList` | Unordered list |
| `orderedList` | Ordered list |
| `blockquote` | Blockquote |
| `codeBlock` | Code block |
| `link` | Hyperlink (opens URL prompt) |
| `image` | Image (requires `withImages()`) |
| `table` | Insert table (requires `withTables()`) |
| `alignLeft` | Left align (requires `withTextAlign()`) |
| `alignCenter` | Centre align (requires `withTextAlign()`) |
| `alignRight` | Right align (requires `withTextAlign()`) |
| `undo` | Undo |
| `redo` | Redo |
| `\|` | Visual separator |

## Comparison with RichEditor

| Feature | RichEditor | TiptapEditor |
|---------|-----------|--------------|
| Engine | `document.execCommand` (deprecated) | ProseMirror (stable) |
| Cross-browser | Inconsistent | Consistent |
| Extensions | None | Tables, images, align, highlight, … |
| Output | HTML | HTML or JSON |
| npm dependency | No | Yes |
| Setup effort | Zero | `npm install` + one import |

## Methods

| Method | Type | Description |
|--------|------|-------------|
| `toolbarButtons(array)` | array | Override the toolbar button list |
| `disableToolbarButtons(array)` | array | Remove specific buttons |
| `disableAllToolbarButtons()` | — | Hide the toolbar entirely |
| `default(string\|Closure)` | string | Pre-formatted document the editor opens on when empty |
| `defaultOnNull()` | — | Let `default()` also fill an existing `null` on fill |
| `outputHtml()` | — | Store content as HTML (default) |
| `outputJson()` | — | Store content as TipTap JSON string |
| `withImages(bool)` | bool | Enable image extension + button |
| `withTables(bool)` | bool | Enable table extension + button |
| `withTextAlign(bool)` | bool | Enable text-align extension + buttons |
| `withHighlight(bool)` | bool | Enable highlight extension + button |
| `minHeight(int)` | int | Minimum editor height in pixels (default `240`) |
| `maxLength(int\|null)` | int | Character limit with live counter |
| `disabled(bool\|Closure)` | bool | Disable the editor |
| `readOnly(bool\|Closure)` | bool | Read-only mode |
| `required()` | — | Mark as required |
| `placeholder(string\|Closure)` | string | Placeholder shown when empty |
| `live()` | — | Trigger Livewire update on each change |
| `debounce(int)` | ms | Debounce delay for `live()` |

See [Common Field API](index.md#common-field-api) for label, hint, tooltip, and other shared methods.
