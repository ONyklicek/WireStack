# MarkdownEditor

Markdown editor with toolbar shortcuts and optional live preview. Stores plain Markdown text.

```php
use NyonCode\WireForms\Components\MarkdownEditor;
```

## Basic Usage

```php
MarkdownEditor::make('description')
    ->minHeight(300)
```

## With Preview Tab

```php
MarkdownEditor::make('content')
    ->withPreview()    // default — adds a Write / Preview tab switcher
```

## Side-by-Side Preview

```php
MarkdownEditor::make('article')
    ->livePreview()    // editor and preview rendered side by side
```

## Without Preview

```php
MarkdownEditor::make('notes')
    ->withPreview(false)
```

## Character Limit

```php
MarkdownEditor::make('bio')
    ->maxLength(500)   // shows a counter and enforces the limit
```

## Toolbar Shortcuts

The toolbar provides keyboard-accessible buttons for:

| Button | Output |
|--------|--------|
| **B** | `**bold**` |
| *I* | `*italic*` |
| ~~S~~ | `~~strikethrough~~` |
| `</>` | `` `inline code` `` |
| H | `## Heading` |
| List | `- item` |
| Quote | `> blockquote` |

## Preview Rendering

The built-in preview handles: headings (`#`, `##`, `###`), bold/italic/strikethrough, inline code, links, blockquotes, and unordered/ordered lists. For full GFM rendering, post-process the stored Markdown on the server side using a library like [CommonMark](https://commonmark.thephpleague.com/).

The preview runs in the browser and writes through `x-html`, so raw HTML in the Markdown is **escaped, not rendered**: `<img src=x onerror=…>` shows as text. Link URLs are additionally restricted to `http(s):`, `mailto:`, `#` and root-relative paths — anything else becomes `#`, so a `javascript:` link cannot be planted through the preview.

## Localization

Toolbar tooltips and the Write/Preview tab labels come from the shared editor
vocabulary `wire-forms::fields.editor.*` — the same keys
[TiptapEditor](tiptap-editor.md#localization) and
[RichEditor](rich-editor.md#localization) use, so the three editors read alike in
every locale. English (`en`) and Czech (`cs`) ship with the package; a Czech app
shows *Tučné*, *Kód v textu*, and the tabs *Psát* / *Náhled*.

Reword a string, or add a locale, by publishing the translations and editing
`lang/vendor/wire-forms/{locale}/fields.php`:

```bash
php artisan vendor:publish --tag=wire-forms::translations
```

## Methods

| Method | Type | Description |
|--------|------|-------------|
| `withPreview(bool)` | bool | Show Write/Preview tabs (default `true`) |
| `livePreview(bool)` | bool | Side-by-side editor and preview |
| `minHeight(int)` | int | Minimum height in pixels (default `200`) |
| `maxLength(int\|null)` | int | Maximum character count |
| `placeholder(string\|Closure)` | string | Textarea placeholder |
| `disabled(bool\|Closure)` | bool | Disable the editor |
| `readOnly(bool\|Closure)` | bool | Read-only mode |
| `required()` | — | Mark as required |
| `live()` | — | Trigger Livewire update on each keystroke |
| `debounce(int)` | ms | Debounce delay for `live()` |

See [Common Field API](index.md#common-field-api) for label, hint, tooltip, and other shared methods.
