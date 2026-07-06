---
order: 40
---

# Konfigurace

Wire funguje po instalaci hned. Konfigurační soubory publikujte jen když potřebujete změnit výchozí hodnoty pro notifikace, formáty data, uploady, chování tabulky, chování sortable nebo audit logging.

## Publikování konfiguračních souborů

```bash
php artisan vendor:publish --tag=wire-core::config
php artisan vendor:publish --tag=wire-forms::config
php artisan vendor:publish --tag=wire-table::config
php artisan vendor:publish --tag=wire-sortable::config
php artisan vendor:publish --tag=wire-boost::config
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
    ],

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

Příklady použití viz [Core Notifikace](core/notifications.md).

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
[Core → Foundation → Ikony](core/foundation.md#icons).

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

Třídy pluginů, životní cyklus, závislosti, makra, hooky, type registry, query pipes a konfiguraci pluginů viz [Core Pluginy](core/plugins.md).

### Modály

Hodnoty šířky modalu jsou size tokeny ve stylu Tailwindu jako `sm`, `md`, `lg`, `xl`, `2xl` nebo `full`.

```php
'modals' => [
    'default_width' => 'lg',
    'slide_over_width' => 'xl',
    'close_on_click_away' => false,
    'close_on_escape' => true,
],
```

Modální akce a slide-overy viz [Core Modály](core/modals.md).

### Mobil

Plovoucí panely (dropdowny, menu skupin akcí, select/date/tag pickery, panely filtrů a přepínání sloupců tabulky) a mobilní varianty modalů se pod breakpointem zobrazí jako **bottom sheet**. Toto jsou globální výchozí hodnoty — každá komponenta je přepisuje per instance.

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

Přepisy per komponenta (vyhrávají nad globálními výchozími):

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

Priorita: per-komponenta (`->sheetOnMobile()` / `->mobileBreakpoint()`) > searchable-auto-floating > globální konfigurace. Searchable selecty jsou defaultně plovoucí, aby vyhledávací pole zůstalo použitelné. Sheety automaticky přidávají safe-area padding, úchyt pro zavření tažením a focus trap.

## Forms

Konfigurace `wire-forms` řídí výchozí hodnoty data a času, uploady a toolbar rich editoru.

```php
return [
    'date_format' => 'd.m.Y',
    'time_format' => 'H:i',
    'datetime_format' => 'd.m.Y H:i',
    'first_day_of_week' => 1,

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

Pro přesun uploadů na jiný filesystem disk použijte `WIRE_FORMS_UPLOAD_DISK`:

```env
WIRE_FORMS_UPLOAD_DISK=s3
```

Volby specifické pro pole viz [Reference polí](forms/fields/index.md).

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

Viz [Přehled tabulek](table/overview.md), [Sloupce](table/columns/index.md) a [Exporty](table/exports.md).

## Sortable

Konfigurace `wire-sortable` řídí řazení řádků a načítání SortableJS.

```php
return [
    'order_column' => 'sort_order',
    'sortablejs_cdn' => 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js',
    'animation' => 150,
    'user_model' => 'App\\Models\\User',
    'user_key_type' => 'id', // 'uuid' / 'ulid' pro neceločíselné klíče uživatele
];
```

Nastavte `sortablejs_cdn` na `null`, když si SortableJS bundluje vaše aplikace sama. Nastavte `user_key_type` na `uuid` nebo `ulid` (před spuštěním migrace pořadí sloupců), když váš model uživatele používá neceločíselný primární klíč.

Viz [Instalace Sortable](sortable/installation.md).

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
[MCP Server a nástroje](boost/mcp-tools.md).

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

Nastavení, použití modelu a prořezávání viz [Audit Log](core/audit.md).
