# Html

Raw HTML content injection and static helpers.

```php
use NyonCode\WireForms\Components\Display\Html;
```

## Usage

```php
Html::make('custom')
    ->content('<div class="text-red-500">Custom HTML</div>')
```

## Static Helpers

```php
Html::divider()                    // horizontal rule
Html::spacer()                     // empty space
Html::heading('Section Title')     // <h3> heading
Html::paragraph('Description')     // <p> text
```
