---
order: 10
summary: Celý stack jedním require a jeden interaktivní příkaz, který z čistého Laravelu udělá fungující administraci.
---

# Instalace Wire

```bash
composer require nyoncode/wire-suite
php artisan wire:install
```

První řádek přinese core, formuláře, tabulky, sortable, resources a admin shell.
Druhý je nastaví — interaktivně, část po části, a pouští přitom vlastní
instalátor každého balíčku místo jeho kopie.

## Co instalátor dělá

```text
 INFO  Found in this application

  • Core — nyoncode/wire-core
  • Forms — nyoncode/wire-forms
  • Tables — nyoncode/wire-table
  • Admin shell — nyoncode/wire-admin

 Which parts should be set up? [All of them]

 INFO  Available, not installed here

  User administration, with roles where the application has them.
  composer require nyoncode/wire-module-users
  …
```

Všechno je nabídnuté předvybrané: instalátor, jehož výchozí stav je „nic“, dělá
z běžného případu ten zdlouhavý. `--all` otázku přeskočí, což je to, co chce
skriptované nasazení.

**Composer nespouští.** Nabídnout modul, který není nainstalovaný, je smyslem
toho druhého seznamu — a odpovědí je řádek k vložení. Artisan příkaz, který volá
composer, běží **uvnitř** aplikace, kterou se chystá změnit; autoloader, který
používá, je ten, který composer přepisuje. A způsoby, jak to selže (limit paměti,
pluginy, produkční image bez composeru), jsou přesně ty, které se ze stack trace
ladit nedají.

**Část, jejíž provider není načtený, se ohlásí, nezhavaruje.** Třída může být
autoloadovatelná, zatímco provider chybí — `dont-discover`, balíček registrovaný
jen v jednom prostředí — a volání neexistujícího příkazu by jinak shodilo celý
běh.

## Moduly

Každý je vlastní `composer require`, protože aplikace, která chce uživatele a nic
jiného, nemá proč tahat knihovnu médií:

| Modul | Balíček |
| --- | --- |
| [Uživatelé](../modules/users.md) | `nyoncode/wire-module-users` |
| [Nastavení](../modules/settings.md) | `nyoncode/wire-module-settings` |
| [Audit log](../modules/audit.md) | `nyoncode/wire-module-audit` |
| [Notifikace](../modules/notifications.md) | `nyoncode/wire-module-notifications` |
| [Média](../modules/media.md) | `nyoncode/wire-module-media` |

Nainstalujte modul a spusťte `php artisan wire:install` znovu; zaregistruje se
sám, takže do configu se nic dopisovat nemusí.

## Co zůstává na vás

Dvě věci, které instalátor nerozhodne:

```php
// routes/web.php — middleware a prefix jsou vaše
Route::middleware(['web', 'auth'])->prefix('admin')->group(fn () => Route::wireResources());
```

```bash
php artisan migrate
```

## Všechny příkazy, které se s tím vezou

`wire:install` spustí instalátory jednotlivých balíčků za vás; každý jde zavolat
i samostatně, po čemž sáhne aplikace, která si nějaký balíček přidá později:

```bash
php artisan wire:install                      # interaktivní setup nad vším nainstalovaným
php artisan wire-core:install                 # po jednom balíčku — config, assety, překlady
php artisan wire-forms:install
php artisan wire-table:install
php artisan wire-sortable:install
php artisan wire-admin:install                # navíc zapíše layout a řádek @source pro Tailwind
php artisan wire-module-users:install         # …a jeden na každý nainstalovaný modul
php artisan wire-boost:install --agent=claude # AI guidelines a vstup pro MCP
```

K tomu patří dva generátory a tři údržbové příkazy:

| Příkaz | Co dělá |
| --- | --- |
| `make:wire-dashboard` | Třída dashboardu v `app/Dashboards`, připravená pojmenovat widgety — dashboard se píše, nedodává |
| `wire-core:audit-prune --days=` | Smaže auditní záznamy starší než retenční okno ([Auditní log](../core/audit.md)) |
| `wire-core:notifications-prune` | Totéž pro uložené notifikace |
| `wire-module-media:thumbnails` | (Znovu)vygeneruje konverze souborů v knihovně ([Média](../modules/media.md)) |
| `wire-module-media:usage` | Přepočítá, kde se který soubor používá |
| `wire-boost:update` | Obnoví AI guidelines po upgradu |
| `wire-boost:mcp` | Spustí MCP server ([Boost](../boost/mcp-tools.md)) |

## Související

- [Začínáme](getting-started.md) — ruční cesta, balíček po balíčku
- [Admin shell](../admin/overview.md) — layout, ve kterém se stránky vykreslují
- [Moduly](../panels/modules.md) — co je modul a jak napsat vlastní
- [Hotové moduly](../modules/index.md) — co veze která instalovatelná oblast
