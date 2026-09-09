---
order: 50
summary: Typované nastavení aplikace — úložiště, cache a obrazovku dodává modul, co je nastavitelné určuje aplikace.
---

# Modul Nastavení

Každá aplikace nakonec má hrstku věcí, které chce někdo změnit bez nasazení.
Tohle je pro ně tabulka, cache a obrazovka.

```bash
composer require nyoncode/wire-module-settings
php artisan wire-module-settings:install
php artisan migrate
```

## Jak to funguje

**Modul vlastní úložiště a obrazovku; aplikace vlastní to, co je nastavitelné.**
Balíček, který by dodával vlastní seznam nastavení, by hádal, co aplikace
potřebuje — skupina je proto třída, kterou napíšete vy:

```php
use NyonCode\WireModuleSettings\Contracts\SettingsGroup;

final class BrandingSettings implements SettingsGroup    // [tl! focus:start]
{
    public static function group(): string { return 'branding'; }

    public static function label(): string { return __('Branding'); }

    public static function schema(): array
    {
        return [TextInput::make('company_name'), Toggle::make('dark_default')];
    }
}                                                        // [tl! focus:end]
```

```php
// config/wire-module-settings.php
'groups' => [App\Settings\BrandingSettings::class],
```

**Hodnoty si drží svůj typ.** Sloupec je JSON, takže boolean se vrátí jako
boolean a pole přežije — tabulka nastavení, která všechno převede na řetězec,
nutí každého čtenáře přetypovávat ručně, a ti se pak neshodnou.

**Skupina je jeden záznam v cache.** Nastavení se čte skoro při každém requestu a
zapisuje se téměř nikdy; po klíčích by to bylo jedno hledání na čtení, po
skupinách je to jedno pro stránku, která přečte šest hodnot. Každý zápis záznam
skupiny zahodí — včetně zápisu přes model, tedy seederu, datové migrace nebo
tinkeru — protože invalidace patří tam, kde se zapisuje, ne jen do `Settings`.

**Čtení je bezpečné i dřív, než tabulka existuje**, a ta odpověď se nepamatuje.
Volání nastavení sedí v kódu, který běží i během `migrate` a při čisté instalaci,
takže chybějící tabulka odpoví výchozí hodnotou místo výjimky. Záměrně se
necachuje: `rememberForever` znamená navždy, a nasazení, které sáhlo na nastavení
před `migrate`, by jinak nechalo aplikaci odpovídat „prázdno“ do chvíle, než
někdo něco zapíše.

**Skupina se uloží celá, nebo vůbec.** `Settings::fill()` je jedna transakce, a
je to tentýž slib, na kterém stojí obrazovka: edituje jednu skupinu naráz, aby
nikdy nemusela říct „branding se uložil, ale mail ne“ — a smyčka šesti zápisů, ve
které selže čtvrtý, přesně tohle o polích jedné skupiny říká.

**Skupina je URL.** Routa je `settings/{record}`, takže `settings/mail` otevře
nastavení mailu a člověk si ho může uložit do záložek. Proto je přepínač tvořen
odkazy, a ne taby — stav tabu žijící v Livewire snapshotu do záložek uložit
nejde. Skupina, kterou tato aplikace nedeklaruje, je 404, a skupina, na kterou
tento uživatel nemá, je 403: alternativou by v obou případech byl formulář bez
polí, a prázdno se čte jako „tady není co nastavovat“.

## Čtení nastavení

```php
use NyonCode\WireModuleSettings\Support\Settings;

Settings::get('company_name', 'Acme', 'branding');
Settings::set('dark_default', true, 'branding');
Settings::fill(['company_name' => 'Acme', 'dark_default' => true], 'branding');
Settings::has('dark_default', 'branding');
Settings::remove('dark_default', 'branding');
Settings::clear('branding');
Settings::all('branding');
```

`get()` se rozhoduje v jednom pořadí a stojí za to ho znát: **uložená hodnota,
potom výchozí hodnota deklarovaná skupinou, potom `$default`, který jste
předali.** Ten poslední je záchrana pro klíč, který skupina nikdy nedeklarovala.

## Výchozí hodnoty patří skupině

Alternativou je výchozí hodnota žijící na každém místě volání — tentýž
`'noreply@example.com'` napsaný na šesti místech, z nichž se pět shodne. Skupina
je deklaruje jednou, implementací jednoho dalšího rozhraní:

```php
use NyonCode\WireModuleSettings\Contracts\ProvidesSettingsDefaults;
use NyonCode\WireModuleSettings\Contracts\SettingsGroup;

final class MailSettings implements SettingsGroup, ProvidesSettingsDefaults
{
    public static function group(): string { return 'mail'; }

    public static function label(): string { return __('Mail'); }

    public static function schema(): array
    {
        return [TextInput::make('from_address')->email(), Toggle::make('queue')];
    }

    public static function defaults(): array          // [tl! focus:start]
    {
        return ['from_address' => 'noreply@example.com', 'queue' => true];
    }                                                 // [tl! focus:end]
}
```

`Settings::get('from_address', null, 'mail')` teď odpoví deklarovanou hodnotou —
a stejně tak obrazovka: pole mají správné hodnoty hned při prvním otevření místo
stránky prázdných inputů.

`has()` je otázka, na kterou výchozí hodnota *neodpovídá*: jestli někdo hodnotu
skutečně uložil. Datová migrace, která tabulku prochází, chce právě tuhle.

## Ikony, popisky a pořadí

Druhé volitelné rozhraní, pro skupinu, která chce nějak vypadat:

```php
use NyonCode\WireModuleSettings\Contracts\DescribesSettingsGroup;

final class MailSettings implements SettingsGroup, DescribesSettingsGroup
{
    // …

    public static function icon(): ?string { return 'outline:envelope'; }    // [tl! focus:start]

    public static function description(): ?string
    {
        return __('Odkud odchází transakční pošta.');
    }

    public static function sort(): int { return 20; }                     // [tl! focus:end]
}
```

Ikona je v přepínači vedle skupiny, popisek pod nadpisem a `sort()` rozhoduje o
pořadí. Skupiny, které pořadí nedeklarují, si drží to, v jakém je vyjmenoval
config — řazení je stabilní, takže skupina, která číslo uvede, nepromíchá ty,
které mlčí. Jediná deklarovaná skupina žádný přepínač nevykreslí: jedna skupina
není volba.

**Kartu pro ploché schéma dodá stránka.** Každý formulář resource dostává svůj
podklad ze `Section`ů, které resource deklaroval — a skupina nastavení, jejímž
celým kontraktem je nadpis a seznam polí, žádný deklarovat nemusí; vykreslená
holá by měla inputy přímo na pozadí stránky, což nedělá žádná jiná obrazovka
panelu. Stránka je proto obalí, a jen tam, kde obal chybí: skupina, která si
přinese vlastní `Section`, `Grid` nebo `Tabs`, se vykreslí tak, jak je — karta
kolem karty je rámeček uvnitř rámečku a skupina už řekla, kde má vlastní okraje.

## Balíček může dodat vlastní tab

`groups` je seznam aplikace a zůstává jím — majitel panelu říká, co se v jeho
panelu nastavuje. Druhá půlka je balíček, který dodává funkci *a* tab, kterým se
nastavuje; ten jinak musí končit README větou „a teď si tuhle třídu přidejte do
configu" — jedinou instrukcí, kterou každá jiná plocha v tomhle frameworku už
dávno dávat přestala. Modul se registruje sám, a jeho nastavení taky.

```php
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\WireModuleSettings\Support\SettingsRegistry;

public function configure(Packager $packager): void
{
    $packager
        ->name('AcmeBilling')
        ->hasShortName('acme-billing')
        ->hasConfig()
        ->bootedPackage(function (): void {
            SettingsRegistry::instance()->register(BillingSettings::class);   // [tl! focus]
        });
}
```

Nic jiného se nemění: je to tentýž `SettingsGroup`, jaký píše aplikace, se
stejnými volitelnými kontrakty a ukládáním do stejné tabulky.

**Kdo vyhraje.** Seznam aplikace se čte první, takže skupina, kterou deklaruje
pod stejným úložným názvem, tu dodanou nahradí — a mezi skupinami bez `sort()`
se řadí před ni. Tab, který nechce vůbec, patří do `except`:

```php
// config/wire-module-settings.php
'except' => ['billing'],
```

Tahle úniková cesta je důvod, proč balíček vůbec smí tab dodávat — obrazovka
dodaná balíčkem, kterou aplikace nemůže odstranit, je přesně to, kvůli čemu lidé
přestanou takové balíčky instalovat.

**Registrujte v `boot`, a bude to fungovat i z `register`.** Pořadí providerů je
v Laravelu pořadí, v jakém je našel composer, ne kontrakt — provider
přispívajícího balíčku tedy klidně poběží dřív než provider tohohle modulu.
`SettingsRegistry::instance()` se naváže při prvním doteku, takže ten, kdo je
tam první, vytvoří jedinou instanci a druhý ji najde. Bez toho by registrace
skončila v zahozeném objektu a tab by na některých strojích chyběl podle
lockfilu.

## Kdo co smí měnit

Dvě úrovně a obě jsou `Gate::allows()` — nic tady kontrolu oprávnění
neimplementuje znovu, takže vlastní gates Laravelu, `spatie/laravel-permission`
i `nyoncode/laravel-permission-extended` na ně odpovídají stejně jako na každou
jinou kontrolu v tomhle frameworku.

**Obrazovku** hlídá jeden řádek konfigurace. Stane se z něj middleware `can:` na
obou routách a schová položku menu, která k nim vede:

```php
// config/wire-module-settings.php
'permission' => 'settings.manage',
```

Ve výchozím stavu null, protože oprávnění vymyšlené tímhle balíčkem by obrazovku
zamklo v každé instalaci, která takovou schopnost nemá. Obrazovka nastavení je
hned po auditním logu ta, u které se ho vyplatí pojmenovat nejvíc: mění se na ní
chování aplikace bez nasazení.

**Jedna skupina** může chtít víc než obrazovka:

```php
use NyonCode\WireModuleSettings\Contracts\GuardsSettingsGroup;

final class BillingSettings implements SettingsGroup, GuardsSettingsGroup
{
    // …

    public static function permission(): ?string { return 'settings.billing'; }
}
```

Skupina, na kterou aktuální uživatel nemá, není v přepínači, otevření její URL je
403 a její uložení také — název skupiny je veřejná property, takže jede v Livewire
snapshotu a vrací se z prohlížeče, a uživatel, který smí otevřít jednu skupinu,
nesmí uložit jinou tím, že přepíše hodnotu, která cestuje.

Uživatel, který neprojde *žádnou* deklarovanou skupinou, dostane 403 i na celou
obrazovku, a ne prázdný stav: ten říká „deklarujte třídu SettingsGroup a uveďte ji
v configu", což je instrukce pro vývojáře a pro všechny ostatní lež. Aplikace,
která skutečně nedeklarovala nic, ho vidí dál.

## Reakce na změnu

Nastavení jsou hodnoty, ze kterých se konfiguruje něco dalšího, takže změna jedné
z nich obvykle musí někam dosáhnout. Po každém zápisu se odešle `SettingsSaved`:

```php
use Illuminate\Support\Facades\Event;
use NyonCode\WireModuleSettings\Events\SettingsSaved;

Event::listen(SettingsSaved::class, function (SettingsSaved $event): void {
    if ($event->group === 'mail') {
        Cache::forget('mail-transport');
    }
});
```

`$event->values` je to, co nesl tenhle zápis, ne celá skupina; posluchač, který
chce zbytek, se zeptá `Settings::all()` — ta už tyhle hodnoty v odpovědi má.

## Kde se to cachuje

```php
// config/wire-module-settings.php
'cache' => [
    'enabled' => env('WIRE_SETTINGS_CACHE', true),
    'store' => env('WIRE_SETTINGS_CACHE_STORE'),
],
```

Na úložišti záleží víc, než to vypadá. Ponechání null použije výchozí úložiště
aplikace, a v aplikaci, jejíž výchozí je `database`, se čtení, kterému má tahle
cache předejít, jen vymění za jiný dotaz. Pojmenujte paměťové úložiště, které už
provozujete, a čtení nastavení přestane sahat do databáze — instalátor to řekne,
když najde výchozí `database`.

Vypnutí cache je na ladění a na testy, které kontrolují přímo tabulku.

## Rozsáhlý příklad

Skupina, která používá všechna tři volitelná rozhraní, a něco, co ji čte:

```php
namespace App\Settings;

use App\Support\Branding;
use Illuminate\Support\Facades\Event;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireModuleSettings\Contracts\DescribesSettingsGroup;
use NyonCode\WireModuleSettings\Contracts\GuardsSettingsGroup;
use NyonCode\WireModuleSettings\Contracts\ProvidesSettingsDefaults;
use NyonCode\WireModuleSettings\Contracts\SettingsGroup;
use NyonCode\WireModuleSettings\Events\SettingsSaved;
use NyonCode\WireModuleSettings\Support\Settings;

final class BrandingSettings implements
    DescribesSettingsGroup,
    GuardsSettingsGroup,
    ProvidesSettingsDefaults,
    SettingsGroup
{
    public static function group(): string
    {
        return 'branding';
    }

    public static function label(): string
    {
        return __('settings.branding');
    }

    public static function schema(): array
    {
        return [
            TextInput::make('company_name')->label(__('settings.company_name'))->required(),
            TextInput::make('support_email')->label(__('settings.support_email'))->email(),
            Toggle::make('dark_by_default')->label(__('settings.dark_by_default')),
        ];
    }

    public static function icon(): ?string          // [tl! focus:start]
    {
        return 'outline:swatch';
    }

    public static function description(): ?string
    {
        return __('settings.branding_description');
    }

    public static function sort(): int
    {
        return 10;
    }

    public static function defaults(): array
    {
        return ['company_name' => config('app.name'), 'dark_by_default' => false];
    }

    public static function permission(): ?string
    {
        return 'settings.branding';
    }                                               // [tl! focus:end]
}
```

```php
// View composer, mailable, hlavička PDF — kdekoliv je hodnota potřeba.
Settings::get('company_name', group: 'branding');

// A cache, kterou si drží něco jiného, zahozená ve chvíli, kdy se hodnota hne.
Event::listen(SettingsSaved::class, function (SettingsSaved $event): void {
    if ($event->group === 'branding') {
        Branding::flush();       // [tl! focus]
    }
});
```

## Související

- [Moduly](../panels/modules.md) — jak balíček dodává takovouhle oblast
- [Resources](../panels/resources.md) — katalog, ve kterém je tahle obrazovka registrovaná
- [Formuláře](../forms/overview.md) — komponenty, kterými se píše schéma skupiny
