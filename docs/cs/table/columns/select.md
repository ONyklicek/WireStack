---
order: 23
nav: false
---

# SelectColumn

Inline select dropdown — ukládá okamžitě při změně.

```php
use NyonCode\WireTable\Columns\SelectColumn;
```

## Základní použití

```php
SelectColumn::make('status')
    ->options([
        'draft' => 'Draft',
        'review' => 'In Review',
        'published' => 'Published',
        'archived' => 'Archived',
    ])
```

## Options z relace

```php
SelectColumn::make('category_id')
    ->relationship('category', 'name')   // načíst options ze souvisejícího modelu
```

## Options z enumu

Předejte třídu PHP enumu pro rozvinutí jeho case na options `value => label`. Labely pocházejí z
`getLabel()`, když enum implementuje `Foundation\Contracts\Enum\HasLabel`, jinak se
z názvu case udělá headline. Kontrakty viz [Enum a JSON casty](casts.md).

```php
SelectColumn::make('status')->options(OrderStatus::class)
```

## Nativní vs stylovaný

```php
// Nativní HTML <select> (výchozí)
SelectColumn::make('type')->options([...])->native()

// Vlastní stylovaný dropdown
SelectColumn::make('type')->options([...])->native(false)
```

## Podmíněné disabled

```php
SelectColumn::make('role')
    ->options(['admin' => 'Admin', 'editor' => 'Editor', 'viewer' => 'Viewer'])
    ->disabled(fn ($record) => $record->is_super_admin)  // super admina nelze změnit
```

## API SelectColumn

```php
->options(array|string|Closure $options) // ['value' => 'Label', ...] nebo třída enumu
->native(bool $native = true)       // použít nativní <select> element
->isNative(): bool
->disabled(bool|Closure $disabled = true)
->isDisabled(Model $record): bool
->relationship(string $name, string $titleAttribute)  // options z relace
```
