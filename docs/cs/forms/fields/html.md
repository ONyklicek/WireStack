# Html

Injektování raw HTML obsahu a statické helpery.

```php
use NyonCode\WireForms\Components\Display\Html;
```

## Použití

```php
Html::make('custom')
    ->content('<div class="text-red-500">Custom HTML</div>')
```

## Statické helpery

```php
Html::divider()                    // vodorovná čára
Html::spacer()                     // prázdné místo
Html::heading('Section Title')     // <h3> nadpis
Html::paragraph('Description')     // <p> text
```
