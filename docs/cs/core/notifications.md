---
order: 30
---

# Notifikace

Zásuvný notifikační systém s více drivery.

## Drivery

| Driver | Třída | Doručení | Požadavky |
|--------|-------|----------|--------------|
| Aktuální komponenta | `CurrentComponentDriver` | Dekorátor — resolvuje aktivní Livewire komponentu přes `Livewire::current()`, pak deleguje na obalený driver (výchozí `SessionDriver`) | Žádné (výchozí) |
| Session | `SessionDriver` | `session()->flash()` **+** Livewire událost nesoucí **plný** payload | Žádné |
| Livewire | `LivewireEventDriver` | Livewire `$dispatch()` browser událost s **plným** payloadem | Frontend listener (toast kontejner) |
| Flasher | `FlasherDriver` | Integrace [PHP Flasher](https://php-flasher.io) | `php-flasher/flasher-laravel` |
| Database | `DatabaseDriver` | Zapíše notifikaci; přežije request, který ji vyvolal | Tabulka `wire_notifications` (migrace je součástí balíčku) |
| Stack | `StackDriver` | Několik driverů naráz — obvyklá dvojice „toast **a** zvoneček" | Co potřebují obalené drivery |
| Null | `NullDriver` | No-op — zahodí vše | Žádné |

Vestavěný výchozí je **`CurrentComponentDriver`** obalující `SessionDriver`: sám resolvuje právě renderovanou Livewire komponentu, takže call-sites nikdy nemusí předávat `$this`. `SessionDriver` i `LivewireEventDriver` forwardují **plný** payload (`title`, `duration`, `icon`, `actions`, …), takže bohaté toasty přežijí server round-trip.

### Který driver na co?

| Použijte tento driver, když… | Driver |
|-----------------------|--------|
| Chcete zpětnou vazbu bez nastavení, která **přežije redirecty / plná načtení stránky** (flash), s bonusem základního live toastu — dobrý výchozí pro server-rendered a redirect-after-action toky. | `SessionDriver` |
| Vaše UI je **toast kontejner** a chcete **bohaté, okamžité toasty** (titulek, trvání, ikona) bez reloadu. Doporučená kombinace s `<x-wire-notifications::toast-container />`. | `LivewireEventDriver` |
| Vaše aplikace už používá **php-flasher** (adaptéry Toastr / Notyf / SweetAlert) a chcete, aby notifikace tekly do toho existujícího UI. | `FlasherDriver` |
| Chcete **vypnout notifikace** — testy, queued/background joby nebo jakýkoli kontext bez uživatele, kterého notifikovat. | `NullDriver` |

> **Které drivery krmí toast kontejner?** `<x-wire-notifications::toast-container />` je Alpine listener na Livewire browser události, takže ho dosáhnou jen drivery odesílající události: výchozí **`CurrentComponentDriver`**, **`SessionDriver`** a **`LivewireEventDriver`** — všechny forwardují plný `title`/`duration`/`icon`/`actions` payload. `FlasherDriver` vykresluje **vlastní** UI a kontejner obchází; `NullDriver` nezobrazí nic.

## Perzistentní notifikace

Drivery výš doručují na právě renderovanou stránku, což je správná odpověď na
„Uloženo" a špatná na frontovaný export, který doběhne za dvacet minut: to už
není komu dispatchovat ani do čeho flashovat. `DatabaseDriver` notifikaci místo
toho zapíše.

```php
// config/wire-core.php
'notifications' => [
    'default' => ['session', 'database'],
],
```

**Seznam** vybere několik driverů naráz a obvykle je to přesně to, co chceš:
toast hned a záznam ve zvonečku pro uživatele, který se zrovna díval jinam.
Samotný řetězec dál funguje a zůstane jedním driverem.

Perzistentní notifikace patří příjemci, kterého resolvuje `ResolvesNotifiable` —
ve výchozím stavu přihlášený uživatel. Když není přihlášený nikdo, driver
**nezapíše nic**: řádek uložený na nikoho si nemá kdo přečíst. Na queue workeru
je to normální stav, takže když má job adresovat konkrétního uživatele, navaž
vlastní resolver:

```php
use NyonCode\WireCore\Notifications\Contracts\ResolvesNotifiable;

app()->bind(ResolvesNotifiable::class, fn () => new class implements ResolvesNotifiable {
    public function resolve(): ?Model
    {
        return User::find(session('acting_as'));
    }
});
```

### Zvoneček

```blade
@livewire('wire-notification-bell')
@livewire('wire-notification-bell', ['limit' => 5])
```

Počet nepřečtených, posledních pár a dva způsoby, jak je uklidit. Seznam řadí
nepřečtené první — zvoneček je od toho, aby řekl, co jsi neviděl, a dávka
přečtení by třídenní nepřečtenou položku jinak vytlačila pryč.

Pod ním `NotificationCenter` odpovídá na totéž bez komponenty, třeba pro konzolový
příkaz nebo JSON endpoint:

```php
$center = app(NotificationCenter::class);

$center->unreadCount();          // číslo na zvonečku
$center->latest(10);             // nepřečtené první, pak nejnovější
$center->unread(10);
$center->markAsRead($id);        // scopováno na příjemce
$center->markAllAsRead();        // vrátí, kolik jich bylo nepřečtených
```

Všechno je scopované na resolvovaného příjemce, `markAsRead()` včetně — id
přichází z Livewire akce, tedy je to uživatelský vstup, a nescopované vyhledání
by nechalo jednoho uživatele označit notifikaci jiného.

### Tabulka

Migrace odpovídá Laravelovu tvaru `notifications` (id / type / notifiable / data
/ read_at), takže aplikace, která tu tabulku už má, si na ni může
`wire-core.notifications.database.table` nasměrovat a číst obojí přes vlastní
`Notifiable::notifications()`.

Id je v tom uuid sloupci **ULID**. Obojí je řetězec, který se vejde, ale ULID se
řadí podle času vzniku — a pět notifikací z jedné dávkové úlohy padne do stejné
sekundy, kde je `created_at` samo seřadí náhodně.

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
->persistent(bool $on = true): static   // sticky toast: duration 0, bez odpočtové lišty
->icon(?string $icon): static
->position(?string $position): static
->extra(array $data): static      // libovolná extra data (sloučená)
->action(NotificationAction|string $action, ?string $event = null): static  // přidat akční tlačítko
->actions(array $actions): static      // nahradit sadu akčních tlačítek
->toArray(): array                // serializovat do pole

// Odesílání (přes NotificationManager)
// $livewire je volitelný — výchozí CurrentComponentDriver si aktivní
// komponentu resolvuje sám, takže ho běžně vynecháte.
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
3. **Fallback:** vestavěný `CurrentComponentDriver` obalující `SessionDriver`

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
| `event-name` | `table-notification` | `window` událost, které naslouchá (`x-on:{eventName}.window`) |
| `progress` | `true` | zobrazit per-toast odpočtovou lištu (viz níže) |
| `stack` | `false` | sbalit toasty do hromádky, která se na hover rozevře |
| `max` | `0` | omezit počet viditelných toastů (`0` = neomezeno); přebytek se sbalí do pillu „+N more“ |

## Toasty

Vše níže vykresluje `<x-wire-notifications::toast-container />` — drivery jen odesílají payloady; kontejner rozhoduje, jak toast vypadá a jak se chová.

### Odpočtová lišta

Každý auto-zavírající toast má u spodní hrany tenkou **odpočtovou lištu**, která ubývá, jak toast stárne — uživatel tak vidí, za jak dlouho se zavře. **Najetí na jakýkoli toast pauzuje lištu i auto-zavření** (a po odjetí pokračuje). Lišta je defaultně zapnutá a obarvená podle typu notifikace.

- Je **volitelná** — `:progress="false"` ji skryje.
- **Trvalé toasty lištu nemají** — sticky toast neodpočítává, takže by neměl co ukazovat (viz níže).

```blade
<x-wire-notifications::toast-container :progress="false" />  {{-- bez odpočtové lišty --}}
```

### Trvalé toasty

`->persistent()` (nebo `->duration(0)`) udělá toast **sticky**: zůstane, dokud ho uživatel nezavře, a nemá odpočtovou lištu. Ideální pro zprávy vyžadující rozhodnutí.

```php
NotificationManager::send(
    Notification::warning('Platba potřebuje kontrolu, než se zúčtuje.')
        ->title('Vyžaduje akci')
        ->persistent()
);
```

### Akční tlačítka

Přidejte tlačítka, která po kliknutí dispatchnou Livewire událost — afordance „Undo". Hostitelská komponenta poslouchá přes `#[On(...)]`.

```php
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationAction;

// zkratka: label + Livewire událost
NotificationManager::send(
    Notification::success('Položka smazána')->action('Vrátit', 'restore-record')
);

// plná kontrola
NotificationManager::send(
    Notification::success('Objednávka #1042 uložena')->action(
        NotificationAction::make('Vrátit', 'restore-record')
            ->payload(['id' => 1042])   // odešle se s dispatchnutou událostí
            ->color('primary')          // akcent tlačítka (fallback na typ toastu)
            ->keepOpen()                // po kliknutí toast nezavírat
    )
);
```

```php
// v hostitelské Livewire komponentě
#[On('restore-record')]
public function restore(int $id): void
{
    // …
}
```

`NotificationAction` je immutable hodnotový objekt: `make(label, event)`, `->payload([...])`, `->color(...)`, `->keepOpen()`. Klik dispatchne `Livewire.dispatch(event, payload)` a (pokud není `keepOpen()`) zavře toast.

### Skládání a přetečení

- **`stack`** sbalí toasty do úhledné hromádky; najetí na hromádku je rozevře do plného seznamu. Nejnovější toast je nejblíže kotvící hraně.
- **`max`** omezí, kolik je jich vidět naráz; přebytek se sbalí do klikacího pillu **„+N more“**, který odhalí zbytek.

```blade
<x-wire-notifications::toast-container stack :max="5" />
```

### Přístupnost

Kontejner je `aria-live="polite"` region (error toasty používají `role="alert"`), takže screen readery toasty ohlašují, jak přicházejí. Ctí i **`prefers-reduced-motion`**: při požadavku na omezený pohyb se hromádka nikdy nesbaluje/nerozevírá a přechody karet jsou vypnuté.

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
