# RichEditor

Rich text editor with configurable toolbar.

```php
use NyonCode\WireForms\Components\RichEditor;
```

## Usage

```php
RichEditor::make('content')
    ->toolbarButtons([
        'bold', 'italic', 'underline',
        'h2', 'h3',
        'bulletList', 'orderedList',
        'link', 'blockquote',
    ])
```

## Default Toolbar

When no `toolbarButtons()` call is made (and no config override), the default toolbar is:

| Button key | Description |
|------------|-------------|
| `bold` | Bold |
| `italic` | Italic |
| `underline` | Underline |
| `strike` | Strikethrough |
| `h2` | Heading 2 |
| `h3` | Heading 3 |
| `bulletList` | Unordered list |
| `orderedList` | Ordered list |
| `link` | Hyperlink |
| `blockquote` | Block quote |
| `codeBlock` | Code block |
| `undo` | Undo |
| `redo` | Redo |

The default can be overridden globally via `config('wire-forms.rich_editor.toolbar')`.

## Disable Specific Buttons

```php
RichEditor::make('content')
    ->disableToolbarButtons(['codeBlock', 'attachFiles'])
```

## Disable All Buttons

```php
RichEditor::make('content')
    ->disableAllToolbarButtons()   // plain rich text, no toolbar
```

## Character Limit

```php
RichEditor::make('summary')
    ->maxLength(500)
```

## Localization

Toolbar tooltips and the link prompt come from the shared editor vocabulary
`wire-forms::fields.editor.*` — the same keys
[TiptapEditor](tiptap-editor.md#localization) and
[MarkdownEditor](markdown-editor.md#localization) use, so the three editors read
alike in every locale. English (`en`) and Czech (`cs`) ship with the package; a
Czech app shows *Tučné*, *Číslovaný seznam*, *Nadpis 2*, and prompts *URL odkazu*.

Reword a string, or add a locale, by publishing the translations and editing
`lang/vendor/wire-forms/{locale}/fields.php`:

```bash
php artisan vendor:publish --tag=wire-forms::translations
```

## Methods

| Method | Type | Description |
|--------|------|-------------|
| `toolbarButtons(array)` | array | Replace the toolbar with this set of buttons |
| `disableToolbarButtons(array)` | array | Remove specific buttons from the toolbar |
| `disableAllToolbarButtons()` | — | Show no toolbar (plain rich text) |
| `maxLength(int\|null)` | int | Character limit |
| `disabled(bool\|Closure)` | bool | Disable the editor |
| `readOnly(bool\|Closure)` | bool | Read-only mode |
| `required()` | — | Mark as required |

See [Common Field API](index.md#common-field-api) for label, hint, tooltip, and other shared methods.
