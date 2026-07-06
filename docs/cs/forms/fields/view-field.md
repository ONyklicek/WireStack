# ViewField

Vykreslí vlastní Blade pohled uvnitř formuláře.

```php
use NyonCode\WireForms\Components\Display\ViewField;
```

## Použití

```php
ViewField::make('preview')
    ->view('components.order-preview')
    ->viewData(['key' => 'value'])
```

## Metody

| Metoda | Popis |
|--------|-------------|
| `view(string)` | Název Blade pohledu |
| `viewData(array)` | Data předaná pohledu |
| `content(string)` | Statický obsah (alternativa k view) |
| `escape()` | HTML-escapovat obsah |
