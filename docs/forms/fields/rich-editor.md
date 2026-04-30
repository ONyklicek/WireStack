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

## File Attachments

```php
RichEditor::make('content')
    ->fileAttachmentsDirectory('content-images')
```

## Character Limit

```php
RichEditor::make('summary')
    ->maxLength(500)
```
