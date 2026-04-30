# Textarea

Multi-line text input.

```php
use NyonCode\WireForms\Components\Textarea;
```

## Usage

```php
Textarea::make('description')
    ->rows(5)
    ->cols(40)
    ->minLength(10)
    ->maxLength(1000)
    ->autosize()       // auto-resize based on content
```

## Methods

| Method | Description |
|--------|-------------|
| `rows(int)` | Number of visible rows |
| `cols(int)` | Number of visible columns |
| `minLength(int)` | Minimum character count |
| `maxLength(int)` | Maximum character count |
| `autosize()` | Auto-resize textarea height |
