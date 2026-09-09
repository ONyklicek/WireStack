---
order: 70
summary: Co tabulka řekne, když akce doběhne, selže nebo předá práci frontě.
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
| CurrentComponentDriver | Vestavěný výchozí — resolvuje aktivní Livewire komponentu a deleguje na `SessionDriver` (flash + live toast s plným payloadem) |
| SessionDriver | Laravel flash-style doručování plus live událost s plným payloadem |
| LivewireEventDriver | Chcete jen live toasty bez session flashe |
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

`TableNotification` a `TableNotificationManager` byly aliasy těchhle dvou a ve 2.0
byly odstraněny — viz [Průvodce upgradem](../start/upgrade.md#odstraneno-kazdy-shim-oznaceny-pro-20-20).

## Související dokumentace

- [Začínáme](../start/getting-started.md)
- [Akce tabulky](actions.md)
- [Core Notifikace](../core/notifications/index.md)
