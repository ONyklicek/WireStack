# Placeholder

Zobrazení statického textového obsahu. Nedrží data formuláře.

```php
use NyonCode\WireForms\Components\Display\Placeholder;
```

## Použití

```php
Placeholder::make('notice')
    ->content('This action will send an email to the user.')

Placeholder::make('html_notice')
    ->content('<strong>Important:</strong> changes are irreversible.')
    ->allowHtml()

// Zkratka
Placeholder::make('info')->html('<em>Formatted</em> text')
```

## Metody

| Metoda | Popis |
|--------|-------------|
| `content(string\|Closure)` | Textový obsah |
| `allowHtml()` | Vykreslit obsah jako HTML |
| `html(string)` | Nastavit obsah a zapnout HTML |
