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

> **Publishing the asset (optional).** If you prefer to serve the files through your
> own asset pipeline/CDN, publish them with:
> ```bash
> php artisan vendor:publish --tag=wire-forms::assets
> ```
> This copies the bundles to `public/vendor/wire-forms/`.

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
