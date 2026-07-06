---
order: 70
---

# Notifikace tabulky

Wire používá notifikace k potvrzení dokončených akcí, hlášení selhání a zobrazení práce na pozadí uživateli.

## Výchozí použití

Nejjednodušší cesta je nechat akce odeslat úspěšné nebo chybové zprávy.

```php
use NyonCode\WireCore\Actions\Action;

Action::make('save')
    ->successNotification('User saved.')
    ->failureNotification('User could not be saved.')
    ->action(function (User $record, Action $action) {
        try {
            $record->save();
            $action->sendSuccessNotification();
        } catch (\Throwable $e) {
            $action->sendFailureNotification();
        }
    })
```

## Manuální notifikace

Manuální notifikace použijte, když výsledek závisí na runtime podmínkách.

```php
use NyonCode\WireCore\Notifications\Notification;

Action::make('process')
    ->action(function (User $record, Action $action) {
        if ($record->hasWarnings()) {
            $action->sendNotification(
                Notification::warning('Processed with warnings.')
            );

            return;
        }

        $action->sendNotification(
            Notification::success('Processing finished.')
        );
    })
```

## Typy notifikací

| Typ | Typické použití |
|------|-------------|
| `success` | Uložení, smazání, import, publikování |
| `error` | Selhání, výjimka, neplatný externí stav |
| `warning` | Částečné dokončení, rizikové navazující kroky |
| `info` | Spuštěný job, zařazená práce, neutrální zpětná vazba |

## Toast kontejner

Pokud chcete vestavěné vykreslování toastů, přidejte kontejner do layoutu:

```blade
<x-wire-notifications::toast-container />
```

Bez něj lze notifikace stále odesílat přes vlastní driver, ale výchozí vizuální kontejner se nevykreslí.

## Drivery

Doručování notifikací je založené na driverech.

| Driver | Použijte když |
|--------|----------|
| SessionDriver | Výchozí Laravel flash-style doručování |
| LivewireEventDriver | Už máte JS toast vrstvu |
| FlasherDriver | Používáte `php-flasher` |
| Vlastní driver | Potřebujete integraci specifickou pro projekt |

### Přepis per tabulka

```php
use NyonCode\WireCore\Notifications\Drivers\LivewireEventDriver;

->notificationDriver(new LivewireEventDriver('wire-toast'))
```

### Globální konfigurace

```php
// config/wire-table.php
'notification_driver' => null,
```

Zde nastavte třídu driveru, pokud chcete jeden výchozí pro celou aplikaci.

## Vlastní objekt notifikace

Když potřebujete více kontroly, sestavte notifikaci explicitně.

```php
Notification::success('User saved.')
    ->title('Done')
    ->duration(4000)
    ->icon('check')
    ->position('top-right')
```

`TableNotification` stále existuje jako zpětně kompatibilní alias, ale nový kód by měl používat `Notification`.

## Související dokumentace

- [Začínáme](../getting-started.md)
- [Akce tabulky](actions.md)
- [Core Notifikace](../core/notifications.md)
