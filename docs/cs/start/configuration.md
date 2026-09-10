---
order: 30
summary: Každý config soubor, který tyhle balíčky publikují, o čem který klíč rozhoduje a co dostanete, když neřeknete nic.
---

# Konfigurace

Wire funguje po instalaci hned. Konfigurační soubory publikujte jen když potřebujete změnit výchozí hodnoty pro notifikace, formáty data, uploady, chování tabulky, chování sortable nebo audit logging.

## Publikování konfiguračních souborů

```bash
php artisan vendor:publish --tag=wire-core::config
php artisan vendor:publish --tag=wire-forms::config
php artisan vendor:publish --tag=wire-table::config
php artisan vendor:publish --tag=wire-sortable::config
php artisan vendor:publish --tag=wire-panels::config
php artisan vendor:publish --tag=wire-admin::config
php artisan vendor:publish --tag=wire-boost::config

# Jeden na každý nainstalovaný modul
php artisan vendor:publish --tag=wire-module-users::config
php artisan vendor:publish --tag=wire-module-auth::config
php artisan vendor:publish --tag=wire-module-settings::config
php artisan vendor:publish --tag=wire-module-notifications::config
php artisan vendor:publish --tag=wire-module-audit::config
php artisan vendor:publish --tag=wire-module-media::config
```

Potřebujete jen tagy balíčků, které jste nainstalovali.

## Proměnné prostředí

| Proměnná | Výchozí | Používá |
|----------|---------|---------|
| `WIRE_NOTIFICATIONS_DRIVER` | `session` | Core notifikace |
| `WIRE_AUDIT_ENABLED` | `true` | Core audit log |
| `WIRE_AUDIT_USER_MODEL` | `App\Models\User` | Core audit log |
| `WIRE_FORMS_UPLOAD_DISK` | `public` | Forms upload souborů |
| `WIRE_MOBILE_SHEET` | `true` | Core mobilní bottom-sheety |
| `WIRE_MOBILE_BREAKPOINT` | `sm` | Breakpoint mobilního sheetu |

<a id="javascript-assets"></a>
## JavaScriptové assety

Není co konfigurovat ani co publikovat: každý balíček si své předsestavené bundly
zkopíruje do `public/vendor/<balíček>` a servíruje je jako statické soubory,
s cache-bustingem podle času poslední změny souboru. Jediné, o čem rozhoduje vaše
aplikace, je *kde* se vypíšou — dejte

```blade
@wireStackScripts
```

jednou do `<head>` layoutu a Alpine controllery všech nainstalovaných balíčků jsou
v úvodním dokumentu, což je přesně to, co je udrží funkční napříč `wire:navigate`
(včetně cesty cachovaného Zpět/Vpřed). Předáním jména balíčku —
`@wireStackScripts('wire-table')` — vypíšete jen bundly jednoho balíčku.

`php artisan vendor:publish --tag=laravel-assets --force` udělá tutéž kopii dopředu,
čímž ji sundá z prvního requestu po nasazení — užitečné, nikdy povinné. Konfigurační
klíč k tomu tak jako tak žádný není.

Podrobné vysvětlení v [Začínáme → JavaScriptové assety](getting-started.md#javascriptove-assety).

## Core

Konfigurace `wire-core` řídí sdílené chování UI.

```php
return [
    'notifications' => [
        'default' => env('WIRE_NOTIFICATIONS_DRIVER', 'session'),
    ],

    'icons' => [
        'default_set' => 'default',
        'sets' => [
            'default' => \NyonCode\WireCore\Foundation\Icons\DefaultIconSet::class,
            // 'lucide' => App\Wire\Icons\LucideIconSet::class,   // => "lucide:home"
        ],
        'paths' => [
            // resource_path('icons'),                 // logo.svg => "logo"
            // 'brand' => resource_path('icons/brand'), // mark.svg => "brand-mark"
        ],
        'warn_missing' => env('WIRE_ICONS_WARN_MISSING', false),
    ],

    'colors' => [
        'palette' => [],

        // Role, ne barvy: každá plocha následuje to, na co ukazují. // [tl! focus:start]
        'success' => 'emerald',
        'danger' => 'red',
        'warning' => 'amber',
        'info' => 'cyan',
    ],

    // 'normal' nebo 'compact' — viz Vzhled → Hustota.
    'density' => env('WIRE_DENSITY', 'normal'),

    // 'rounded' nebo 'sharp' — viz Vzhled → Tvar.
    'shape' => env('WIRE_SHAPE', 'rounded'), // [tl! focus:end]

    'plugins' => [
        // App\Wire\Plugins\ExamplePlugin::class,
    ],

    'modals' => [
        'default_width' => 'md',
        'slide_over_width' => 'md',
        'close_on_click_away' => true,
        'close_on_escape' => true,
    ],
];
```

### Notifikace

Vestavěné notifikační drivery jsou:

| Hodnota | Driver |
|-------|--------|
| `session` | Ukládá notifikace do session flash dat |
| `livewire` | Odesílá Livewire browser události |
| `flasher` | Používá Flasher, když ho aplikace má nainstalovaný |
| `null` | Vypne doručování |

```env
WIRE_NOTIFICATIONS_DRIVER=livewire
```

Příklady použití viz [Core Notifikace](../core/notifications/index.md).

### Ikony

| Klíč | Účel |
|-----|---------|
| `default_set` | Který klíč v `sets` je **neprefixovaná** základní sada (výchozí `'default'` = Heroicons). |
| `sets` | Zaregistrované sady ikon. Klíč výchozí sady je neprefixovaný; **každý další klíč je povinný prefix**, takže se jeho ikony adresují jako `prefix:name` (např. `lucide:home`). Registrace ne-výchozí sady bez řetězcového prefixu vyhodí chybu. |
| `paths` | Složky `.svg` souborů automaticky registrovaných jako holo-pojmenované ikony. Řetězcový klíč přidá pomlčkou spojený prefix názvu (`'brand' => …` → `brand-mark`). |
| `warn_missing` | Zaloguje varování (a vykreslí fallback), když je použit neznámý název ikony — hodí se na odchytávání překlepů ve vývoji. |

```php
'icons' => [
    'default_set' => 'default',
    'sets' => [
        'default' => DefaultIconSet::class,   // "pencil"      (Heroicons, 20×20 fill)
        'lucide'  => LucideIconSet::class,    // "lucide:home" (24×24 stroke)
    ],
    'paths' => [
        'brand' => resource_path('icons/brand'),
    ],
    'warn_missing' => env('WIRE_ICONS_WARN_MISSING', false),
],
```

Sady se používají společně s deterministickým, kolizím-odolným vyhodnocením.
Kompletní API, model `prefix:name`, vlastní sady a přístupnost viz
[Core → Foundation → Ikony](../core/foundation/icons.md#ikony).

### Pluginy

Zaregistrujte aplikační nebo balíčkové pluginy v poli `plugins`:

```php
'plugins' => [
    App\Wire\Plugins\TenantPlugin::class,
],
```

Pluginy implementující `HasConfiguration` mohou také číst sloučené volby z `wire-core.plugins.config.{pluginId}`:

```php
'plugins' => [
    App\Wire\Plugins\ExportPlugin::class,

    'config' => [
        'export' => [
            'format' => 'xlsx',
        ],
    ],
],
```

Třídy pluginů, životní cyklus, závislosti, makra, hooky, type registry, query pipes a konfiguraci pluginů viz [Core Pluginy](../core/plugins/index.md).

### Modaly

Hodnoty šířky modalu jsou size tokeny ve stylu Tailwindu jako `sm`, `md`, `lg`, `xl`, `2xl` nebo `full`.

```php
'modals' => [
    'default_width' => 'lg',
    'slide_over_width' => 'xl',
    'close_on_click_away' => false,
    'close_on_escape' => true,
],
```

Modální akce a slide-overy viz [Core Modaly](../core/modals.md).

<a id="mobile"></a>
### Mobil

Plovoucí panely (rozbalovací nabídky, menu skupin akcí, select/date/tag pickery, panely filtrů a přepínání sloupců tabulky) a mobilní varianty modalů se pod breakpointem zobrazí jako **bottom sheet**. Toto jsou globální výchozí hodnoty — každá komponenta je přepisuje u své instance.

```php
'mobile' => [
    // Zobrazit plovoucí panely jako bottom sheet na mobilu. false = klasický
    // plovoucí panel ukotvený k triggeru všude.
    'sheet' => env('WIRE_MOBILE_SHEET', true),

    // Breakpoint, pod kterým se panely stanou sheetem:
    //   'sm' (< 640px, telefony — výchozí)
    //   'md' (< 768px, včetně malých tabletů)
    //   'lg' (< 1024px, včetně tabletu na výšku)
    'breakpoint' => env('WIRE_MOBILE_BREAKPOINT', 'sm'),
],
```

Přepisy u jednotlivých komponent (vyhrávají nad globálními výchozími):

```php
// Sheet zap/vyp
Select::make('role')->options([...])->sheetOnMobile(false);   // vynutit plovoucí
Select::make('country')->searchable()->sheetOnMobile();       // vynutit sheet i když searchable
$table->sheetOnMobile(false);                                 // panely filtrů + přepínání sloupců

// Breakpoint (sm | md | lg)
Select::make('role')->mobileBreakpoint('lg');                 // sheet až do 1024px
$table->mobileBreakpoint('md');
ActionGroup::make([...])->mobileBreakpoint('md');
Action::make('edit')->form([...])->slideOverOnMobile()->mobileBreakpoint('md');
```

```blade
<x-wire::dropdown :sheet-on-mobile="false" :breakpoint="'md'">…</x-wire::dropdown>
```

Priorita: jednotlivá komponenta (`->sheetOnMobile()` / `->mobileBreakpoint()`) > searchable-auto-floating > globální konfigurace. Searchable selecty jsou defaultně plovoucí, aby vyhledávací pole zůstalo použitelné. Sheety automaticky přidávají safe-area padding, úchyt pro zavření tažením a focus trap.

## Forms

Konfigurace `wire-forms` řídí výchozí hodnoty data a času, zápis částek a telefonních
čísel, uploady a toolbar rich editoru.

```php
return [
    'date_format' => 'd.m.Y',
    'time_format' => 'H:i',
    'datetime_format' => 'd.m.Y H:i',
    'first_day_of_week' => 1,

    'money' => [                                            // [tl! focus:start]
        'currency' => 'CZK',
        'decimal_separator' => ',',
        'thousands_separator' => ' ',
    ],

    'phone' => [
        'countries' => [],
        'default_country' => null,
    ],                                                      // [tl! focus:end]

    'file_upload' => [
        'disk' => env('WIRE_FORMS_UPLOAD_DISK', 'public'),
        'directory' => 'uploads',
    ],

    'rich_editor' => [
        'toolbar' => [
            'bold', 'italic', 'underline', 'strike',
            '|', 'heading', 'bulletList', 'orderedList',
            '|', 'link', 'blockquote', 'codeBlock',
            '|', 'undo', 'redo',
        ],
    ],
];
```

`money` říká, jak [MoneyInput](../forms/fields/money-input.md) zapíše částku, pokud pole neurčí jinak,
a `phone` je nabídka [PhoneInputu](../forms/fields/phone-input.md) — prázdný seznam `countries` nabídne
celou tabulku předvoleb a seznam je zároveň validací.

Pro přesun uploadů na jiný filesystem disk použijte `WIRE_FORMS_UPLOAD_DISK`:

```env
WIRE_FORMS_UPLOAD_DISK=s3
```

Volby specifické pro pole viz [Reference polí](../forms/fields/index.md).

## Table

Konfigurace `wire-table` řídí výchozí chování tabulky a chování inline text inputu.

```php
return [
    'defaults' => [
        'per_page' => 10,
        'per_page_options' => [10, 25, 50, 100],
        'searchable' => true,
        'sortable' => true,
        'hoverable' => true,
        'striped' => false,
    ],

    'text_input' => [
        'save_on_blur' => true,
        'save_on_enter' => true,
        'live_validation' => false,
        'live_debounce' => 500,
    ],

    'notification_driver' => null,
];
```

`notification_driver` může zůstat jako `null`; tabulka pak použije core session driver. Nastavte ho jen když tabulka potřebuje jinou třídu driveru.

Viz [Přehled tabulek](../table/overview.md), [Sloupce](../table/columns/index.md) a [Exporty](../table/exports.md).

## Sortable

Konfigurace `wire-sortable` řídí řazení řádků.

```php
return [
    'order_column' => 'sort_order',
    'sortablejs_cdn' => null,
    'animation' => 150,
    'user_model' => 'App\\Models\\User',
    'user_key_type' => 'id', // 'uuid' / 'ulid' pro neceločíselné klíče uživatele
];
```

`sortablejs_cdn` je ve výchozím stavu `null`, protože SortableJS je zkompilovaný přímo
do bundlu balíčku — řazení nepotřebuje žádný CDN request, takže funguje offline i pod
přísnou CSP. Nastavte ho jen tehdy, když **váš vlastní** kód potřebuje globální
`window.Sortable`: tag se pak načte *navíc* k bundlu, nikdy místo něj, a drag
controller používá zabundlovanou kopii tak jako tak.

Nastavte `user_key_type` na `uuid` nebo `ulid` (před spuštěním migrace pořadí sloupců), když váš model uživatele používá neceločíselný primární klíč.

Viz [Instalace Sortable](../sortable/installation.md).

## Panels

Konfigurace `wire-panels` rozhoduje, jestli framework zaregistruje stránky
resource jako routy za vás.

```php
return [
    'routes' => [
        'enabled' => false,
        'prefix' => 'admin',
        'middleware' => ['web', 'auth'],
        'domain' => null,
        'only' => [],
        'except' => [],
    ],
];
```

`enabled` je `false`, protože referenční cestou zůstává `Route::wireResources()`
ve vašem vlastním route souboru — tohle jsou tytéž argumenty skupiny, předané
jednou, pro aplikaci, která si kvůli nim nechce držet route soubor.

Dvě věci, které je dobré vědět, než to zapnete. Providery balíčků bootují dřív než
vaše vlastní, takže tyhle routy se matchují **před** vším v `routes/web.php`;
aplikace s catch-all routou pod stejným prefixem dnes vyhraje a přestala by.
A zapnout tohle *a zároveň* volat `Route::wireResources()` je odmítnuto, ne
smířeno — zaregistrovalo by to každou stránku dvakrát pod jedním jménem routy.

`only` / `except` berou registrované klíče: klíč resource nebo klíč dashboardu,
tentýž, kterým je adresuje menu a `ResourceRoutes::urlFor()`.

Pro víc mount pointů — `admin`, `business`, `production` — přidejte klíč `zones`,
jednu položku na zónu; každá zdědí hodnoty nad sebou a přepíše to, co pojmenuje.
Klíč pole se stane prefixem jména routy, takže tentýž resource ve dvou zónách
dostane dvě jména rout místo dvou rout, které se perou o jedno.

```php
'zones' => [
    'admin' => ['prefix' => 'admin', 'middleware' => ['web', 'auth', 'can:admin']],
    'business' => ['prefix' => 'business', 'only' => ['orders']],
],
```

Zóny viz [Resources](../panels/routing.md#zony), zbytek
[Routování](../panels/routing.md).

## Boost

Konfigurace `wire-boost` řídí MCP server AI nástrojů. Dva nástroje spouštějící kód jsou ve výchozím stavu vypnuté.

```php
return [
    'server' => [
        'name' => 'WireStack Boost',
        'version' => '1.0.0',
    ],
    'tools' => [
        'database_query' => env('WIRE_BOOST_DATABASE_QUERY', false),
        'tinker' => env('WIRE_BOOST_TINKER', false),
        'browser_logs' => env('WIRE_BOOST_BROWSER_LOGS', true),
    ],
    'scan' => [
        'paths' => [app_path()], // kde list-wire-components hledá
    ],
    'docs' => [
        'paths' => [], // extra Markdown adresáře pro search-wire-docs
    ],
    'browser_logs' => [
        'path' => storage_path('wire-boost/browser.log'),
        'max_entries' => 50,
    ],
];
```

Zapněte `database-query` a `tinker` jen když důvěřujete agentovi připojujícímu se k serveru. Viz
[MCP Server a nástroje](../boost/mcp-tools.md).

## Audit

Nastavení audit logu žije v `config/wire-core.php`:

```php
'audit' => [
    'enabled' => env('WIRE_AUDIT_ENABLED', true),
    'model' => \NyonCode\WireCore\Audit\AuditEntry::class,
    'user_model' => env('WIRE_AUDIT_USER_MODEL', 'App\\Models\\User'),
    'events' => null,
    'exclude_columns' => [
        'password',
        'remember_token',
    ],
    'retention_days' => null,
],
```

Nastavte `events` na pole, když chcete logovat jen vybrané typy událostí:

```php
'events' => ['created', 'updated', 'deleted'],
```

Nastavení, použití modelu a prořezávání viz [Audit Log](../core/audit.md).

## Admin

Shell publikuje jediný blok a je to brand — všechno ostatní kolem něj je markup,
který si napíšete (viz [Admin shell](../admin/overview.md)):

```php
// config/wire-admin.php
'brand' => [
    'name' => null,        // fallback je config('app.name')
    'logo' => null,        // široké menu — cesta pod public/, nebo URL
    'logo_dark' => null,   // tmavá varianta téhož
    'mark' => null,        // 64pixelová lišta — fallback je iniciála aplikace
    'height' => 28,        // vykreslená výška loga v pixelech
    'url' => null,         // kam brand odkazuje — výchozí je kořen panelu
],
```

Proč jsou podoby loga dvě a proč se volí dřív, než se stránka vykreslí:
[Branding a motiv](../admin/branding.md#logo-v-liste).

## Moduly

Každý hotový modul publikuje vlastní config a jeho klíče jsou popsané na stránce
modulu, ne tady — modul je celá oblast a jeho volby dávají smysl vedle obrazovek,
které mění:

| Soubor | Co konfiguruje | Stránka |
| --- | --- | --- |
| `wire-module-users.php` | Uživatelský model, resource, role a týmy, profilová obrazovka | [Uživatelé](../modules/users.md) |
| `wire-module-auth.php` | Které obrazovky auth modul registruje a jaké routy si bere | [Přihlašování](../modules/auth.md) |
| `wire-module-settings.php` | Tabulka nastavení, její cache a skupiny, které obrazovka ukazuje | [Nastavení](../modules/settings.md) |
| `wire-module-notifications.php` | Zvoneček, jeho panel a tabulka uložených notifikací | [Notifikace](../modules/notifications.md) |
| `wire-module-audit.php` | Auditní obrazovka nad stopou, kterou zapisuje `wire-core` | [Audit](../modules/audit.md) |
| `wire-module-media.php` | Disky, konverze, povolené typy a picker | [Média](../modules/media.md) |

## Zbytek `wire-core`

Tři klíče v `config/wire-core.php` jsou deklarace, ne nastavení, a každý je
popsaný tam, kde se vysvětluje věc, kterou deklaruje:

| Klíč | Co drží | Stránka |
| --- | --- | --- |
| `resources` | Třídy resourců, které aplikace registruje | [Resources → Registrace](../panels/resources.md#registrace) |
| `dashboards` | Třídy dashboardů, stejným způsobem | [Dashboardy](../core/widgets/dashboards.md) |
| `tenancy` | `enabled` a `column`, na který se aplikuje tenant scope | [Autorizace](authorization.md) |

`config/wire-table.php` má ještě jeden: `preferences` volí driver, do kterého se
ukládá pořadí sloupců pro každého uživatele, jejich viditelnost a velikost stránky
(`null`, `session` nebo `database`, přičemž `guest` pojmenovává driver pro
nepřihlášeného návštěvníka). Viz [Pokročilé funkce](../table/advanced.md).
