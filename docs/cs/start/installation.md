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
   ╭───────●
   ╰─────╮      WireStack
 ●───────╯      the whole stack, set up in one pass

 INFO  Found in this application

  • Core — nyoncode/wire-core
  • Forms — nyoncode/wire-forms
  • Tables — nyoncode/wire-table
  • Admin shell — nyoncode/wire-admin

 INFO  Already set up, left alone

  • Core
  • Forms
  Run with --force to set these up again and publish over what they wrote.

 ┌ Which parts should be set up? ───────────────┐
 │ › ◼ Tables                                   │
 │   ◼ Admin shell                              │
 └──────────────────────────────────────────────┘
  Space unticks one, enter confirms.

 INFO  Available, not installed here

  Users — User administration, with roles where the application has them.
  composer require nyoncode/wire-module-users
  …
```

Všechno je nabídnuté předvybrané: instalátor, jehož výchozí stav je „nic“, dělá
z běžného případu ten zdlouhavý — otázka je tedy multiselect, kde je všechno už
zaškrtnuté a mezerníkem se odškrtává to, co nechcete. `--all` otázku přeskočí
úplně, což je to, co chce skriptované nasazení, a `--dry-run` ukáže celý plán,
aniž by cokoli udělal. Značka se kreslí jen tam, kde se někdo dívá — banner
v deploy logu je šum na jediném místě, kde výstup čte stroj.

**Spustí jen to, co má ještě co dělat.** Část, jejíž instalátor už zapsal
všechno, co publikuje, se pojmenuje a přeskočí — a to je pravidlo o správnosti,
ne o rychlosti. Migrace, které tento framework dodává, nemají datový prefix,
takže dostanou razítko podle času, kdy se sestavila publikační mapa: cílová cesta
je v každém procesu jiná, `vendor:publish` se podívá tam, nic nenajde a zapíše
**druhou** kopii migrace, kterou aplikace už má. Dva soubory
`create_wire_preferences_table` a `php artisan migrate` na tom druhém spadne.
Opakované spuštění po přidání modulu je proto bezpečné a vypadá přesně jako výpis
výše: modul se nastaví a všechno, co už tam bylo, se pojmenuje a nechá být.

**`--force` nastaví každou část bez ohledu na to** a předá `--force` dál, takže
každý instalátor publikuje přes to, co zapsal. To je přepínač pro upgrade — a ten,
po kterém sáhnout, když se přeskočila část, kterou jste spustit chtěli.

**Selhaný instalátor shodí celý příkaz.** Návratový kód instalátoru každého
balíčku je zároveň návratovým kódem tohoto příkazu a části, které selhaly, se
vypíšou jménem — skriptované nasazení, které nevidí selhané publikování, je horší
než žádné.

**`--no-interaction` dorazí až k instalátorům, které se spouští.** Každý z nich se
před zásahem do produkční aplikace ptá, a ten dotaz se kreslí na výstup tohoto
příkazu zevnitř běžícího progress spinneru — na jediném místě, kde ho nikdo
nemůže zodpovědět. Běh, kterému se řekne, ať se neptá, to musí platit až dolů.

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
| [Přihlášení](../modules/auth.md) | `nyoncode/wire-module-auth` |
| [Uživatelé](../modules/users.md) | `nyoncode/wire-module-users` |
| [Nastavení](../modules/settings.md) | `nyoncode/wire-module-settings` |
| [Audit log](../modules/audit.md) | `nyoncode/wire-module-audit` |
| [Notifikace](../modules/notifications.md) | `nyoncode/wire-module-notifications` |
| [Média](../modules/media.md) | `nyoncode/wire-module-media` |

Nainstalujte modul a spusťte `php artisan wire:install` znovu; zaregistruje se
sám, takže do configu se nic dopisovat nemusí, a části, které už byly nastavené,
zůstanou nedotčené.

[`wire-boost`](../boost/guidelines-and-skills.md) je vypsaný vedle nich a nikdy se
nespouští: `wire-boost:install` se ptá, které AI agenty nakonfigurovat, a to není
otázka, kterou by měl tento příkaz zodpovídat za někoho jiného.

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
php artisan wire:install --all --no-interaction  # …bezobslužně: bez otázky i bez dotazů pod ní
php artisan wire:install --dry-run            # …celý plán, beze změn
php artisan wire:install --all --force        # …každou část znovu, s přepsáním toho, co zapsala
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
