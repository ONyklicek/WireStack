---
order: 10
summary: "Jeden objekt notifikace, několik driverů a fluent builder, který popíše, co říct, dřív než cokoli rozhodne kde."
---

# Notifikace

Notifikace je nejdřív hodnotový objekt a až potom doručení: popíšeš, co se stalo
— titulek, text, barva, ikona, případně akce — a kam to dopadne, rozhodne
**driver**. Výměna driveru nemění na volajícím kódu nic, a právě proto může být
tentýž řádek toastem v prohlížeči i řádkem v databázi.

## Drivery

| Driver | Třída | Doručení | Požadavky |
|--------|-------|----------|--------------|
| Aktuální komponenta | `CurrentComponentDriver` | Dekorátor — resolvuje aktivní Livewire komponentu přes `Livewire::current()`, pak deleguje na obalený driver (výchozí `SessionDriver`) | Žádné (výchozí) |
| Session | `SessionDriver` | `session()->flash()` **+** Livewire událost nesoucí **plný** payload | Žádné |
| Livewire | `LivewireEventDriver` | Livewire `$dispatch()` browser událost s **plným** payloadem | Frontend listener (toast kontejner) |
| Flasher | `FlasherDriver` | Integrace [PHP Flasher](https://php-flasher.io) | `php-flasher/flasher-laravel` |
| Database | `DatabaseDriver` | Zapíše notifikaci; přežije request, který ji vyvolal | Tabulka `wire_notifications` (migrace je součástí balíčku) |
| Broadcast | `BroadcastDriver` | Řekne **ostatním otevřeným stránkám** příjemce, že něco přišlo — pobídka přes websockety, bez payloadu | Laravel broadcasting a `window.Echo` na stránce |
| Stack | `StackDriver` | Několik driverů naráz — obvyklá dvojice „toast **a** zvoneček" | Co potřebují obalené drivery |
| Null | `NullDriver` | No-op — zahodí vše | Žádné |

Vestavěný výchozí je **`CurrentComponentDriver`** obalující `SessionDriver`: sám resolvuje právě renderovanou Livewire komponentu, takže call-sites nikdy nemusí předávat `$this`. `SessionDriver` i `LivewireEventDriver` forwardují **plný** payload (`title`, `duration`, `icon`, `actions`, …), takže bohaté toasty přežijí server round-trip.

### Který driver na co?

| Použijte tento driver, když… | Driver |
|-----------------------|--------|
| Chcete zpětnou vazbu bez nastavení, která **přežije redirecty / plná načtení stránky** (flash), s bonusem základního live toastu — dobrý výchozí pro server-rendered a redirect-after-action toky. | `SessionDriver` |
| Vaše UI je **toast kontejner** a chcete **bohaté, okamžité toasty** (titulek, trvání, ikona) bez reloadu. Doporučená kombinace s `<x-wire-notifications::toast-container />`. | `LivewireEventDriver` |
| Vaše aplikace už používá **php-flasher** (adaptéry Toastr / Notyf / SweetAlert) a chcete, aby notifikace tekly do toho existujícího UI. | `FlasherDriver` |
| **Frontovaný job** doběhne a uživatel se dívá na jinou záložku nebo na jiné zařízení. Párujte s `database` — samotný ohlásí něco, co se nikdy neuložilo. | `BroadcastDriver` |
| Chcete **vypnout notifikace** — testy, queued/background joby nebo jakýkoli kontext bez uživatele, kterého notifikovat. | `NullDriver` |

> **Které drivery krmí toast kontejner?** `<x-wire-notifications::toast-container />` je Alpine listener na Livewire browser události, takže ho dosáhnou jen drivery odesílající události: výchozí **`CurrentComponentDriver`**, **`SessionDriver`** a **`LivewireEventDriver`** — všechny forwardují plný `title`/`duration`/`icon`/`actions` payload. `FlasherDriver` vykresluje **vlastní** UI a kontejner obchází; `NullDriver` nezobrazí nic.

## Notification builder

`Notification` je immutable hodnotový objekt. Vytvořte přes statickou factory, pak odešlete přes `NotificationManager`.

```php
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationManager;

// Zkratkové factory — vytvořit a odeslat okamžitě
NotificationManager::success('User saved');
NotificationManager::error('Failed to delete');

// Sestavit notifikaci, pak odeslat
$notification = Notification::success('The user was successfully updated.')
    ->title('Record Saved');

NotificationManager::send($notification);

// Plné přizpůsobení
$notification = Notification::make('success', 'Changes saved.')
    ->title('Done')
    ->icon('check')
    ->duration(5000)            // ms, 0 = trvalé
    ->position('top-right')     // top-right, top-left, bottom-right, bottom-left
    ->extra(['link' => '/details']);

NotificationManager::send($notification);
```

### API Notification

Statické factory — každá vrací novou `Notification`:

```php
Notification::make(string $type, string $message): static
Notification::success(string $message): static
Notification::error(string $message): static
Notification::warning(string $message): static
Notification::info(string $message): static
```

Fluent modifikátory. Objekt je immutable, takže každý vrací **novou** instanci:

```php
->title(?string $title): static
->duration(?int $ms): static      // čas auto-zavření, 0 = trvalé
->persistent(bool $on = true): static   // sticky toast: duration 0, bez odpočtové lišty
->icon(?string $icon): static
->position(?string $position): static
->extra(array $data): static      // libovolná extra data (sloučená)
->url(?string $url): static       // kam vede — faktura, ne notifikace
->to(?Model $notifiable): static  // komu patří; přebíjí ResolvesNotifiable
->action(NotificationAction|string $action, ?string $event = null): static
->actions(array $actions): static      // nahradit sadu akčních tlačítek
->toArray(): array                // jen payload — nikdy příjemce
```

Akční tlačítka. **Odkaz** přežije uložení; **událost** potřebuje svého listenera
na stránce, což notifikace otevřená za tři dny nemá důvod čekat:

```php
NotificationAction::make(string $label, string $event): static
NotificationAction::link(string $label, string $url): static
->payload(array $payload): static      // pošle se s dispatchnutou událostí
->url(?string $url): static            // jít sem místo dispatche
->color(?string $color): static        // akcent tlačítka; jinak podle typu
->keepOpen(bool $keepOpen = true): static   // po kliknutí toast nezavírat
```

Odesílání. `$livewire` je volitelný — výchozí `CurrentComponentDriver` si aktivní
komponentu resolvuje sám:

```php
NotificationManager::send(Notification $n, ?NotificationDriver $driver = null, mixed $livewire = null): void
NotificationManager::sendTo(Model $notifiable, Notification $n, ...): void
NotificationManager::success(string $message, ...): void
NotificationManager::error(string $message, ...): void
NotificationManager::warning(string $message, ...): void
NotificationManager::info(string $message, ...): void
```

## Konfigurace

```php
// config/wire-core.php
return [
    'notifications' => [
        // session, livewire, flasher, database, broadcast, null — nebo seznam
        'default' => env('WIRE_NOTIFICATIONS_DRIVER', 'session'),
    ],
];
```

Tato config hodnota řídí **container-bound** `NotificationDriver` (resolvovaný service providerem pro constructor/`app()` injekci).

### Pořadí resolvování driveru

Když zavoláte `NotificationManager::send()` (nebo jeho zkratky), driver se resolvuje v tomto pořadí:

1. **Explicitní** driver předaný do volání / komponenty (`setNotificationDriver()`, argument `$driver`)
2. **Globální výchozí** nastavený přes `NotificationManager::setDefaultDriver()`
3. **Fallback:** vestavěný `CurrentComponentDriver` obalující `SessionDriver`

> **Poznámka:** statický `NotificationManager` **nečte** `wire-core.notifications.default` sám o sobě — ta config jen krmí container binding. Aby se nakonfigurovaný driver stal globálním výchozím pro statické API, přemostěte ho jednou v service provideru:
>
> ```php
> use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
> use NyonCode\WireCore\Notifications\NotificationManager;
>
> NotificationManager::setDefaultDriver(app(NotificationDriver::class));
> ```

## V této sekci

| Stránka | Co pokrývá |
| --- | --- |
| [Toasty](toasts.md) | Driver na obrazovku, jeho Blade komponenta a spuštění z JavaScriptu |
| [Perzistentní notifikace](persistent.md) | Ukládaný druh — zvoneček, panel, stav přečtení |
| [Odkud je posílat](usage.md) | Akce, obyčejné komponenty a formuláře |
| [Vlastní drivery](custom-drivers.md) | Doručení někam, o čem tenhle balíček neví |

## Související

- [Modul notifikací](../../modules/notifications.md) — hotová obrazovka s historií
- [Akce](../actions/index.md) — to, co notifikaci obvykle vyvolá
- [Barvy](../foundation/colors.md) a [Ikony](../foundation/icons.md) — slovník, kterým notifikace mluví
- [Konfigurace](../../start/configuration.md) — celý notifikační blok
