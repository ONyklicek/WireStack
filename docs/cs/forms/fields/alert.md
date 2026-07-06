# Alert

Alert/callout box pro zobrazování zpráv.

```php
use NyonCode\WireForms\Components\Display\Alert;
```

## Použití

```php
Alert::make('warning')
    ->title('Warning')
    ->message('This action cannot be undone.')
    ->warning()
    ->icon('exclamation')
    ->dismissible()
```

## Helpery barev

```php
Alert::make('x')->info()       // modrá
Alert::make('x')->success()    // zelená
Alert::make('x')->warning()    // žlutá
Alert::make('x')->danger()     // červená
Alert::make('x')->color('primary')  // explicitní barva
```

## Metody

| Metoda | Popis |
|--------|-------------|
| `title(string)` | Titulek alertu |
| `message(string)` / `content(string)` | Tělo alertu |
| `icon(string)` | Název ikony |
| `color(string)` | Barva alertu |
| `info()` / `success()` / `warning()` / `danger()` | Zkratky barev |
| `dismissible()` | Povolit zavření |
