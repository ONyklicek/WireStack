---
order: 30
summary: "Notifikace, která přežije request — uložená, spočítaná, označená jako přečtená a zobrazená v panelu za zvonečkem."
---

# Perzistentní notifikace

Některé věci stojí za to říct i někomu, kdo se zrovna nedívá na obrazovku.
Perzistentní notifikace se uloží k příjemci, dokud je nepřečtená, počítá se, a
zobrazí se v panelu za zvonečkem — tentýž hodnotový objekt jako toast, jen
uschovaný místo ukázaný.

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

### Komu patří

Perzistentní notifikace patří příjemci. Říct kdo jde dvěma způsoby a záleží na
pořadí: **co řekne notifikace**, pak **kdo je přihlášený**.

```php
Notification::success('Váš export je hotový')->to($user);

NotificationManager::sendTo($user, Notification::success('Váš export je hotový'));
```

`->to()` je pro případ, na který session neumí odpovědět — a to je zároveň
případ, kvůli kterému ukládající drivery existují: frontovaný job, který doběhne
ve tři ráno, nemá přihlášeného nikoho a uživatel, kterého se to týká, je
argument, který dostal. Jeden job takhle adresuje několik lidí v cyklu a nemusí
se nic přebindovávat.

Neřekla nic? Příjemce přijde z `ResolvesNotifiable` — přihlášený uživatel. Když
tam není nikdo, driver **nezapíše nic**: řádek uložený na nikoho si nemá kdo
přečíst. Vlastní resolver navažte, když je odpověď jiná než „přihlášený
uživatel" pro celou aplikaci — impersonace, tenant:

```php
use NyonCode\WireCore\Notifications\Contracts\ResolvesNotifiable;

app()->bind(ResolvesNotifiable::class, fn () => new class implements ResolvesNotifiable {
    public function resolve(): ?Model
    {
        return User::find(session('acting_as'));
    }
});
```

Ta vazba má jednoho vlastníka a shodnou se na ní všichni, kdo čtou: drivery
zapisující řádek, kanál, na kterém broadcastují, i zvoneček, který ho poslouchá.

Příjemce záměrně **není** součástí uloženého payloadu. To JSON je, co notifikace
říká; příjemce je, do kterého řádku se ukládá.

### Z Laravel notifikace

`via() => ['wire']` doručí Laravel notifikaci do zvonečku a přinese s sebou
`ShouldQueue`, `Notifiable`, `via()` rozhodující se podle uživatele, mail
odcházející vedle toho a `Notification::fake()`:

```php
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NyonCode\WireCore\Notifications\Notification as WireNotification;

class InvoicePaid extends Notification implements ShouldQueue
{
    public function __construct(public Invoice $invoice) {}

    public function via($notifiable): array
    {
        return ['mail', 'wire'];                                    // [tl! focus]
    }

    public function toWire($notifiable): WireNotification           // [tl! focus:start]
    {
        return WireNotification::success('Faktura zaplacena')
            ->title($this->invoice->number)
            ->url(route('invoices.show', $this->invoice));
    }                                                               // [tl! focus:end]
}

$user->notify(new InvoicePaid($invoice));
```

Příjemce přichází z Laravelu, ne ze session, takže tohle funguje z queue workeru
tak, jak to stojí. `toWire()`, které si samo zavolá `->to()`, vyhrává —
eskalace adresovaná nadřízenému je reálná věc.

Párujte to s `database`: Laravel notifikace doručená jen přechodným driverem se
doručí té stránce, která se zrovna renderovala, což pro frontovaný job není
žádná. `illuminate/notifications` není závislost tohohle balíčku — channel se
registruje jen tehdy, když ta třída existuje, což v Laravel aplikaci vždycky.

### Retence

Notifikace je jediný druh řádku, který je *navržený* přestat být důležitý, takže
se nedrží věčně, dokud to neřeknete:

```php
// config/wire-core.php
'notifications' => [
    'database' => [
        'retention_days' => 365,        // všechno
        'read_retention_days' => 30,    // co už uživatel viděl
    ],
],

// routes/console.php
Schedule::command('wire-core:notifications-prune')->daily();
```

Dvě okna, protože „přečtené" a „nikdy se na to nepodíval" jsou různá tvrzení.
Obě můžou být null, což znamená nechat. `--days` a `--read-days` to pro jeden běh
přebijí a bez periody i bez volby to příkaz řekne a nesmaže nic — mazat řádky,
protože nikdo neřekl, že ne, není výchozí chování, které stojí za to mít.

### Živě: říct to ostatním záložkám

`database` řeší, *kam* se notifikace uloží. Neřeší, *kdy* se to uživatel dozví:
řádek je neviditelný, dokud daná záložka příště nepromluví se serverem — a pro
záložku, do které nikdo neklikne, to je nikdy. `broadcast` je půlka, která to
zavírá.

```php
// config/wire-core.php
'notifications' => [
    'default' => ['session', 'database', 'broadcast'],
],
```

Všechny tři dohromady jsou celé uspořádání: **toast** pro záložku, která si
řekla, **řádek** pro později a **pobídka** pro každou další záložku i zařízení.
`broadcast` samotný ohlásí notifikaci, která se nikdy neuložila — klient si ji
načte a nenajde nic.

**Na drátě není nic z té notifikace.** `NotificationReceived` nese jedinou věc,
jméno kanálu příjemce, a `broadcastWith()` je prázdné. Je to pobídka k novému
načtení, ne zpráva k vykreslení: klient na ni odpoví `$wire.$refresh()`, takže
seznam i počet přijdou zpátky přes `NotificationCenter` — přes scoping na
příjemce, což je jediná věc, která brání jednomu uživateli vidět řádky druhého.
Payload by tohle rozhodnutí přesunul na subscription kanálu a text každé
notifikace položil někam, kam si odposlechnutý socket přečte.

Proto je také přeslechnutá pobídka přežitelná: další render je správný tak jako
tak. Žádné Echo na stránce, socket, který spadl přes oběd, odmítnutá
subscription — zvoneček je **pozdě, nikdy špatně**.

Událost je `ShouldBroadcastNow`, záměrně. Frontovaný broadcast v úplně běžném
nastavení nakonfigurované fronty bez běžícího workeru neudělá vůbec nic, tiše —
a tady pod tím není žádný polling, který by to zakryl. Cena je řečena naplno:
zápis počká na HTTP volání broadcasteru, než odpoví.

#### Kanál

Jeden privátní kanál na příjemce, pojmenovaný přes `NotificationChannel`, který
vlastní jméno i autorizaci, takže se ty dvě nemohou rozejít:

```php
NotificationChannel::for($user);   // wire-notifications.App-Models-User.7
NotificationChannel::PATTERN;      // wire-notifications.{notifiable}.{key}
```

Morph třída cestuje s backslashi nahrazenými za `-`. Laravel kompiluje
`{placeholder}` na `([^\.]+)`, takže tečkovaná třída by wildcardem nešla
namatchovat vůbec, a backslash stejně není legální znak kanálu. Zpátky se nikdy
nedekóduje: alias v morph mapě může legitimně obsahovat pomlčku
(`'blog-post' => BlogPost::class`), takže autorizace porovnává **zakódovanou**
morph třídu diváka proti segmentu.

Autorizace se registruje za vás, když je `broadcast` v seznamu driverů, a to tím
nejpřísnějším pravidlem, jaké existuje — divák smí na svůj vlastní kanál a na
žádný jiný. Vypněte ji a napište si vlastní, pokud tohle není vaše politika:

```php
// config/wire-core.php
'notifications' => [
    'broadcast' => ['authorize' => false],
],

// routes/channels.php
use NyonCode\WireCore\Notifications\Support\NotificationChannel;

NotificationChannel::authorize(                                     // [tl! focus:start]
    fn ($user, string $notifiable, string $key): bool => $user->isSupervisor()
        || NotificationChannel::matches($user, $notifiable, $key),  // [tl! focus:end]
);
```

Oba segmenty přicházejí od klienta a předávají se přesně tak, jak dorazily —
zakódované, nerozresolvované a bez důvěry, že jsou to jména tříd.

**Odmítnutá subscription je jediné selhání, o kterém stojí za to křičet**, právě
protože jako jediné vypadá jako úspěch: zvoneček se dál aktualizuje při každém
renderu, takže nic nevypadá rozbitě, a živá půlka je přitom prostě mrtvá. Most
napíše jeden `console.warn` a skončí.

Který broadcaster to nese, je čistě vaše věc. Tohle je obyčejná Laravel broadcast
událost se stringovými jmény kanálů a klientská půlka nevolá nic než
`window.Echo.private()` a `window.Echo.leave()`.

### Zvoneček

```blade
@livewire('wire-notification-bell')
@livewire('wire-notification-bell', ['limit' => 5])
```

Počet nepřečtených a za ním **slide-over panel**: posledních pár se dvěma
záložkami (*Vše* a *Nepřečtené*), označení jedné i všech a odkaz na plný seznam
tam, kde je nějaký nasměrovaný. Panel místo 320px dropdownu, protože inbox je
místo, kam se jde, ne menu, o které se cestou otřete — je tu místo na zprávu pod
titulkem i na záložku, která skryje přečtené.

**Značka má tři stavy, ne dva.** Zvoneček bez odznaku neumí říct, jestli se nic
nestalo, nebo jestli jste všechno přečetli — tak říká obojí: prázdný zvoneček pro
nic, tichá šedá tečka pro „něco tam je, nic nečeká" a počet — jediná hlasitá věc
tady — pro nepřečtené. Počet sedí v prstenci barvy plochy za ním, takže se na
16 px čte jako čip přilepený ke zvonečku, ne jako flek přes něj, a opakuje se
v přístupném názvu tlačítka, protože barevné kolečko čtečce obrazovky neřekne nic.

**Seznam se čte jako časová osa**, nejnovější první, pod hlavičkami *Dnes* /
*Včera* / *Dříve*, které drží nahoře, dokud jejich vlastní úsek projíždí kolem.
Dřív se nepřečtené vyplavovaly nahoru, aby dávka přečtení nevytlačila starou
nepřečtenou položku z deseti řádků — na to teď odpovídá záložka **Nepřečtené**
přímo a pro každý řádek, zatímco řazení odpovídalo jen pro prvních deset.

**Řádek vede tam, kam notifikace řekne.** `->url()` je ta věc, o kterou jde —
faktura, export — a řádek odkazuje tam; vlastní stránka notifikace je záloha
a existuje jen tam, kde něco routuje klíč `notifications`, což udělá instalace
[modulu notifikací](../../modules/notifications.md). Zvoneček se zeptá
`ResolvesPageUrls` a při odpovědi null vykreslí panel bez odkazů, takže není co
konfigurovat ani v jednom případě.

Řádek si nechává skutečný `href`, aby šel odkaz zkopírovat a otevřít prostředním
tlačítkem, zatímco obyčejné levé kliknutí jde přes server — a to je, co ho cestou
označí za přečtený, místo aby závodilo s navigací, kterou má prohlížeč právo
vyhrát.

**Uložená akční tlačítka se vykreslí taky** a odkaz je ten druh, po kterém sáhnout:
událost dosáhne na Livewire listenera, který musí být na stránce, což notifikace
otevřená za tři dny nemá důvod čekat. Jejich barvy jsou těch šest, co zná toast
kontejner — `success`, `error`, `warning`, `info`, `primary`, `gray` — se
zálohou na typ notifikace, takže akce napsaná jednou čte stejně na obou
plochách.

**Zvoneček si nese svou zónu.** `Zone::current()` na Livewire round tripu
neodpoví nic (ADR 0027 §3), takže ji zvoneček přečte jednou při renderu stránky
a odtud ji nese — jeho odkazy pak míří zpátky do zóny, ve které se otevřel. Pro
zvoneček v shellu, který sám není wire route, ji předejte:
`@livewire('wire-notification-bell', ['zone' => 'admin'])`.

**Slovesa jsou ta, co má schránka**: označit přečtené, označit nepřečtené a smazat
u řádku, označit vše a uklidit přečtené v patičce. Každé spíš chybí, než by bylo
neaktivní, když nemá co dělat. Záměrně tu není „smazat všechno" — přečtené je to,
co už uživatel viděl, takže úklid přečteného mu nemůže vzít nic, na co se
nepodíval, a tlačítko, které to umí, je tlačítko, které schránka mít nemá.

S `broadcast` v seznamu driverů se zvoneček přihlásí na kanál příjemce a načítá
znovu, jak notifikace přicházejí. Bez něj je správný při každém renderu a dřív
ne: přidejte si `wire:poll`, nebo z aplikace, která ví, že něco dorazilo,
dispatchněte `wire-notification-received`.

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

## Související

- [Notifikace](index.md) — objekt a jeho builder
- [Toasty](toasts.md) — táž notifikace doručená na obrazovku
- [Modul notifikací](../../modules/notifications.md) — celá obrazovka s historií nad těmito řádky
- [Vlastní drivery](custom-drivers.md) — jak je uložit jinam
