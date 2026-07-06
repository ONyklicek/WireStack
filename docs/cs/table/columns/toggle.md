---
order: 23
nav: false
---

# ToggleColumn

Inline přepínač — ukládá okamžitě při kliknutí. Odesílá události `CellUpdating` / `CellUpdated`.

```php
use NyonCode\WireTable\Columns\ToggleColumn;
```

## Základní použití

```php
ToggleColumn::make('is_active')
ToggleColumn::make('is_featured')
```

## Vlastní barvy

```php
ToggleColumn::make('is_published')
    ->onColor('success')       // zelený když zapnuto
    ->offColor('danger')       // červený když vypnuto
```

## Vlastní ikony

```php
ToggleColumn::make('notifications_enabled')
    ->onIcon('bell')
    ->offIcon('bell-slash')
```

## Disabled stav

```php
ToggleColumn::make('is_admin')
    ->disabled(fn ($record) => $record->id === auth()->id())  // sám sebe nelze přepnout

ToggleColumn::make('is_locked')
    ->disabled()               // vždy disabled (jen zobrazení)
```

## API ToggleColumn

```php
->onColor(string $color)             // výchozí: 'primary'
->offColor(string $color)            // výchozí: 'gray'
->onIcon(?string $icon)              // ikona když zapnuto
->offIcon(?string $icon)             // ikona když vypnuto
->disabled(bool|Closure $disabled = true)
->isDisabled(Model $record): bool
```
