# CodeEditor

Code editor with monospace styling, line numbers, and Tab-key indentation. No external dependencies — stores plain text.

```php
use NyonCode\WireForms\Components\CodeEditor;
```

## Basic Usage

```php
CodeEditor::make('script')
    ->language('php')
```

## Configuration

```php
CodeEditor::make('config')
    ->language('json')
    ->minHeight(300)
    ->withLineNumbers()
    ->maxLength(10000)
```

## Without Line Numbers

```php
CodeEditor::make('query')
    ->language('sql')
    ->withLineNumbers(false)
```

## Supported Language Labels

The `language()` call is **display only** (shown in the header bar). Any string is accepted:

```php
->language('php')
->language('javascript')
->language('json')
->language('sql')
->language('yaml')
->language('bash')
->language('plaintext')   // default
```

Full syntax highlighting requires integrating an external library (e.g. CodeMirror or Monaco) into the application's JS build.

## Methods

| Method | Type | Description |
|--------|------|-------------|
| `language(string)` | string | Language label shown in the header (default `'plaintext'`) |
| `minHeight(int)` | int | Minimum editor height in pixels (default `200`) |
| `withLineNumbers(bool)` | bool | Show a line-number gutter (default `true`) |
| `maxLength(int\|null)` | int | Maximum character count |
| `placeholder(string\|Closure)` | string | Textarea placeholder |
| `disabled(bool\|Closure)` | bool | Disable the editor |
| `readOnly(bool\|Closure)` | bool | Read-only mode |
| `required()` | — | Mark as required |
| `live()` | — | Trigger Livewire update on each keystroke |
| `debounce(int)` | ms | Debounce delay for `live()` |

See [Common Field API](index.md#common-field-api) for label, hint, tooltip, and other shared methods.
