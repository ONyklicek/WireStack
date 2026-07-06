# Hidden

Skryté vstupní pole. Automaticky skryté z formuláře — bez labelu, bez wrapperu.

```php
use NyonCode\WireForms\Components\Hidden;
```

## Použití

```php
Hidden::make('user_id')
    ->default(fn () => auth()->id())

Hidden::make('type')
    ->default('post')
```

## Běžné volby

`Hidden` podporuje plné Field API pro validaci a podmíněnou logiku, i když nevykresluje žádné UI:

```php
Hidden::make('status')
    ->default('draft')
    ->rules(['in:draft,published,archived'])
```

Všechny sdílené metody viz [Společné API pole](index.md#common-field-api).
