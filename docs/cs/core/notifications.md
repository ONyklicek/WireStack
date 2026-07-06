---
order: 30
---

# Notifikace

Zásuvný notifikační systém s více drivery.

## Drivery

| Driver | Třída | Doručení | Požadavky |
|--------|-------|----------|--------------|
| Session | `SessionDriver` | `session()->flash()` **+** Livewire událost (jen `type` + `message`) | Žádné (výchozí) |
| Livewire | `LivewireEventDriver` | Livewire `$dispatch()` browser událost s **plným** payloadem | Frontend listener (toast kontejner) |
| Flasher | `FlasherDriver` | Integrace [PHP Flasher](https://php-flasher.io) | `php-flasher/flasher-laravel` |
| Null | `NullDriver` | No-op — zahodí vše | Žádné |

Session driver je výchozí.

### Který driver na co?

| Použijte tento driver, když… | Driver |
|-----------------------|--------|
| Chcete zpětnou vazbu bez nastavení, která **přežije redirecty / plná načtení stránky** (flash), s bonusem základního live toastu — dobrý výchozí pro server-rendered a redirect-after-action toky. | `SessionDriver` |
| Vaše UI je **toast kontejner** a chcete **bohaté, okamžité toasty** (titulek, trvání, ikona) bez reloadu. Doporučená kombinace s `<x-wire-notifications::toast-container />`. | `LivewireEventDriver` |
| Vaše aplikace už používá **php-flasher** (adaptéry Toastr / Notyf / SweetAlert) a chcete, aby notifikace tekly do toho existujícího UI. | `FlasherDriver` |
| Chcete **vypnout notifikace** — testy, queued/background joby nebo jakýkoli kontext bez uživatele, kterého notifikovat. | `NullDriver` |

> **Které drivery krmí toast kontejner?** `<x-wire-notifications::toast-container />` je Alpine listener na Livewire browser události, takže ho dosáhnou jen drivery odesílající události: **`LivewireEventDriver`** (plný `title`/`duration`/`icon`) a **`SessionDriver`** (jen `type`+`message`; `title`/`duration` spadnou na výchozí hodnoty kontejneru). `FlasherDriver` vykresluje **vlastní** UI a kontejner obchází; `NullDriver` nezobrazí nic.

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

```php
// Statické factory (vrací novou instanci Notification)
Notification::make(string $type, string $message): static
Notification::success(string $message): static
Notification::error(string $message): static
Notification::warning(string $message): static
Notification::info(string $message): static

// Fluent immutable modifikátory (každý vrací novou instanci)
->title(?string $title): static
->duration(?int $ms): static      // čas auto-zavření, 0 = trvalé
->icon(?string $icon): static
->position(?string $position): static
->extra(array $data): static      // libovolná extra data (sloučená)
->toArray(): array                // serializovat do pole

// Odesílání (přes NotificationManager)
NotificationManager::send(Notification $n, ?NotificationDriver $driver = null, mixed $livewire = null): void
NotificationManager::success(string $message, ...): void
NotificationManager::error(string $message, ...): void
NotificationManager::warning(string $message, ...): void
NotificationManager::info(string $message, ...): void
```

## Použití v akcích

```php
Action::make('save')
    ->action(function ($record, Action $action) {
        $record->save();
        $action->sendSuccessNotification();
    })
    ->successNotification('Saved!');

// Vlastní notifikace z akce
$action->sendNotification(
    Notification::success('Done')
        ->title('Processed')
        ->duration(3000)
        ->icon('check')
);
```

## Použití v komponentách

```php
use NyonCode\WireCore\Notifications\Concerns\InteractsWithNotifications;
use NyonCode\WireCore\Notifications\Notification;

class MyComponent extends Component
{
    use InteractsWithNotifications;

    public function save(): void
    {
        // ... save logika

        // Typové zkratky (berou řetězec zprávy)
        $this->notifySuccess('Record saved');
        $this->notifyError('Save failed');
        $this->notifyWarning('Careful');
        $this->notifyInfo('Heads up');

        // Nebo odeslat plně sestavenou Notification
        $this->notify(
            Notification::success('Record saved')->title('Done')->duration(5000)
        );
    }
}
```

## Použití ve formulářích

Formuláře automaticky odešlou úspěšnou notifikaci po `save()`, pokud není vypnuta:

```php
Form::make()
    ->schema([...])
    ->model(User::class)
    ->successMessage('User saved!')          // vlastní zpráva
    ->save();

// Vypnout
Form::make()
    ->schema([...])
    ->disableSuccessNotification()
    ->save();
```

## Konfigurace

```php
// config/wire-core.php
return [
    'notifications' => [
        'default' => env('WIRE_NOTIFICATIONS_DRIVER', 'session'), // session, livewire, flasher, null
    ],
];
```

Tato config hodnota řídí **container-bound** `NotificationDriver` (resolvovaný service providerem pro constructor/`app()` injekci).

### Pořadí resolvování driveru

Když zavoláte `NotificationManager::send()` (nebo jeho zkratky), driver se resolvuje v tomto pořadí:

1. **Explicitní** driver předaný do volání / komponenty (`setNotificationDriver()`, argument `$driver`)
2. **Globální výchozí** nastavený přes `NotificationManager::setDefaultDriver()`
3. **Fallback:** vestavěný `SessionDriver`

> **Poznámka:** statický `NotificationManager` **nečte** `wire-core.notifications.default` sám o sobě — ta config jen krmí container binding. Aby se nakonfigurovaný driver stal globálním výchozím pro statické API, přemostěte ho jednou v service provideru:
>
> ```php
> use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
> use NyonCode\WireCore\Notifications\NotificationManager;
>
> NotificationManager::setDefaultDriver(app(NotificationDriver::class));
> ```

## Vlastní drivery

Implementujte kontrakt `NotificationDriver` — jeho jediná metoda `send()` dostane notifikaci a (volitelně) Livewire komponentu v scope:

```php
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Notification;

class SlackDriver implements NotificationDriver
{
    public function send(Notification $notification, mixed $livewireComponent = null): void
    {
        Http::post('https://hooks.slack.com/...', [
            'text' => $notification->title . ': ' . $notification->message,
        ]);
    }
}
```

Zaregistrujte ho jako globální výchozí v service provideru (`boot()`):

```php
use NyonCode\WireCore\Notifications\NotificationManager;

NotificationManager::setDefaultDriver(new SlackDriver());
```

Nebo ho použijte pro jednu komponentu/volání bez změny globálního výchozího:

```php
$this->setNotificationDriver(new SlackDriver());      // per-komponenta (trait)
NotificationManager::send($notification, new SlackDriver()); // per-volání
```

## Blade komponenta

Umístěte toast kontejner do svého layoutu:

```blade
<x-wire-notifications::toast-container />
```

Můžete přizpůsobit pozici, fallback trvání auto-zavření a browser událost, které naslouchá:

```blade
<x-wire-notifications::toast-container
    position="bottom-right"
    :duration="5000"
    event-name="table-notification" />
```

| Prop | Výchozí | Účel |
|------|---------|---------|
| `position` | `top-right` | `top-left` / `top-center` / `top-right` / `bottom-left` / `bottom-center` / `bottom-right` |
| `duration` | `4000` | fallback auto-zavření (ms) pro notifikace bez vlastního `duration` |
| `eventName` | `table-notification` | `window` událost, které naslouchá (`x-on:{eventName}.window`) |

## Spouštění toastů z JavaScriptu

Toast kontejner nainstaluje globální helper `window.wireToast` (a Alpine `$toast` magic), když se namountuje, takže můžete vyvolat toast rovnou z frontendu — bez server round-tripu. Helper jen odešle `eventName` window událost kontejneru se standardním payloadem (`type`, `message`, `title`, `duration`).

```js
// zkratka — type + message
wireToast.success('Saved');
wireToast.error('Something went wrong');
wireToast.warning('Careful');
wireToast.info('Heads up');

// s volbami (title, duration, …)
wireToast.success('Saved', { title: 'Done', duration: 6000 });

// plný payload objekt (type výchozí 'info' při vynechání)
wireToast({ type: 'success', message: 'Saved', title: 'Done' });
wireToast('Plain info toast');
```

Uvnitř Alpine použijte `$toast` magic:

```blade
<button @click="$toast.success('Copied!')">Copy</button>
```

Helper cílí na nakonfigurovaný `eventName` kontejneru, takže vlastní `event-name="my-toast"` je zapojeno automaticky. `window.wireToast` se nainstaluje jednou (vyhrává první kontejner); pokud vykreslíte více kontejnerů s různými názvy událostí, odešlete `CustomEvent` sami pro ty sekundární:

```js
window.dispatchEvent(new CustomEvent('my-toast', {
    detail: { type: 'success', message: 'Saved' },
}));
```
