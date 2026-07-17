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
