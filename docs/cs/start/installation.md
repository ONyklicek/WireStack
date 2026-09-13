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

## Nastavení aplikace

Nainstalovat balíček a mít funkční aplikaci jsou dvě různé věci a doteď měla
příkaz jen ta první. Každý instalátor balíčku věděl, co ještě chybí, a řekl to —
„Run: php artisan migrate", „name an ability in `wire-module-users.permissions`",
„`wire-core.audit.enabled` is off — nothing is being recorded yet" — devět
takových řádků napříč sedmi balíčky, a za každým z nich nic.

Druhá půlka `wire:install` je proto prochází, jeden po druhém:

```text
 INFO  Setting up this application

  Database tables ..................... run 2 pending migrations   WOULD RUN
  Routes ........................ no routes/web.php to add them to   WAITING
  First administrator . an account already exists, so you can sign in   DONE
  Media links ...................... public/storage is already linked   DONE
  Audit recording ........................ changes are being recorded   DONE
  Stored notifications ............... notifications are being stored   DONE
  Settings cache ................................... cached in `file`   DONE
  Frontend build ...... no package.json, so there is nothing to build   DONE
```

| Krok | Co dělá a proč to není poznámka pod čarou |
| --- | --- |
| Database tables | Spustí čekající migrace. Tři moduly si o to řekly ve vlastních instalátorech a ani jeden s tím nemohl nic udělat |
| [Routes](../panels/modules.md) | Zapíše do `routes/web.php` skupinu s `Route::wireResources()`, pod prefixem a middlewarem, na které se zeptá. Bez toho je každá obrazovka 404 |
| [First administrator](../modules/users.md) | Založí účet, kterým se přihlásíte — a super-admin roli tam, kde aplikace role má. Nic v celém stacku ho předtím nevytvořilo; odpovědí byl `php artisan tinker`. Vždycky jen ten *první*; každý další účet je `php artisan wire:user` |
| [Media links](../modules/media.md) | `storage:link`. Bez něj uploady fungují, náhledy se generují a každý obrázek je 404, které nikde nezahlásí chybu |
| [Audit recording](../core/audit.md) | Zapne zaznamenávání, aby obrazovka auditu nebyla pohledem do prázdné tabulky |
| [Stored notifications](../modules/notifications.md) | Přidá vedle toastu driver `database`, aby zvoneček měl co ukazovat |
| [Settings cache](../modules/settings.md) | Přesune cache nastavení z databáze, kde ji čtení stojí zrovna ten dotaz, který měla ušetřit |
| Frontend build | `npm install && npm run build`, aby Tailwind zkompiloval třídy z views balíčků. Dokud neproběhne, shell nemá šířku, barvu ani chybu |

**Zjisti, zeptej se, udělej.** Krok se nejdřív podívá a nabídne se jen tehdy, když
je potřeba — takže druhé spuštění příkazu je tiché. Co už platí, se pojmenuje a
nechá být.

**Krok, který nemůže proběhnout, to řekne, místo aby se nabídl.** Žádná databáze,
žádný model uživatele, žádné `routes/web.php` — každé z toho se ohlásí jako věc
k nápravě, ne jako otázka, jejíž „ano" skončí stack tracem. To je sloupec
`WAITING` výše.

**Nic se neudělá bez zeptání** a `--no-interaction` to myslí vážně: krok, který si
vystačí s výchozími hodnotami, proběhne, a krok, který ne — první administrátor
potřebuje heslo — se omluví a řekne proč, místo aby si heslo vymyslel do deploy
logu.

### Další účet, kdykoli později

Krok výše založí jeden účet a skončí, protože instalátor, který by při každém
spuštění nabízel dalšího administrátora, je instalátor, který nikdo nemůže
bezpečně pustit dvakrát. Příkaz je druhá cesta dovnitř — neptá se sám od sebe,
ptáte se ho vy:

```bash
php artisan wire:user
php artisan wire:user --name=Jana --email=jana@example.com --password=… --admin
php artisan wire:user --email=… --password=… --role=editor --role=support
```

Co nedostane, na to se zeptá; co nedostane **a** na co se nemá koho zeptat, běh
zastaví, místo aby si to vymyslelo — vygenerované heslo v deploy logu je heslo
v logu. Tam, kde aplikace role má, nabídne ty, které existují — se super-adminem
předvybraným jen u prvního účtu, protože ten je někdo, kdo si otevírá dveře, a
každý další je běžný uživatel, dokud se neřekne jinak.

### Jak přidat vlastní krok

Balíček do toho seznamu přispěje registrací kroku, stejně jako registruje cokoli
jiného. Instalátor se nikdy nedozví, o co v tom kroku jde:

```php
use NyonCode\WireCore\Foundation\Setup\SetupRegistry;

$packager->registeredPackage(fn () => SetupRegistry::instance()->register(WarmTheIndex::class));
```

Třída implementuje `NyonCode\WireCore\Foundation\Setup\Contracts\SetupStep`:
`label()`, `state()` (`Done`, `Pending` nebo `Blocked`), `summary()`, `apply()` a
`sort()`. `state()` se smí jen dívat — běží i pod `--dry-run` — a `apply()` se ptá
přes `SetupConsole`, ne přes příkaz, což je to, co dovolí krok otestovat bez
terminálu.

## Co zůstává na vás

```php
// routes/web.php, pokud si to raději napíšete sami, než aby se vás příkaz ptal
Route::middleware(['web', 'auth'])->prefix('admin')->group(fn () => Route::wireResources());
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
php artisan wire:user                         # další účet, kdykoli — instalátor zakládá jen ten první
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
