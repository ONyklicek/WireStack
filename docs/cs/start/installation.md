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
Druhý je nastaví — průvodce v číslovaných krocích, který se zeptá, co chcete,
spustí vlastní instalátor každého vybraného balíčku místo jeho kopie a pak
nastaví aplikaci, kterou ty balíčky potřebují.

## Co instalátor dělá

```text
   ╭───────●
   ╰─────╮      WireStack
 ●───────╯      the whole stack, set up in one pass

 INFO  Step 1 — The framework.

 ┌ Which parts of the stack? ─────────────────────────────────────────────┐
 │ › ◼ Tables — Tables, columns, filters, exports, gestures.              │
 │   ◼ Admin shell — The layout and the sidebar your pages render inside. │
 └────────────────────────────────────────────────────────────────────────┘
  Space unticks one, enter confirms.

 INFO  Step 2 — Ready-made areas.

 ┌ Which of these should the panel have? ────────────────────────────────────────────────────────┐
 │ › ◼ Sign in — Login, password reset, verification and the two-factor challenge, over Fortify. │
 │   ◼ Users — User administration, with roles where the application has them.                   │
 │   ◻ Media library — Uploads, a browsable list and previews.                                   │
 └───────────────────────────────────────────────────────────────────────────────────────────────┘
  Space unticks one, enter confirms.

 INFO  Step 3 — Installing packages.

  Core — nyoncode/wire-core ....................... ALREADY DONE
  Forms — nyoncode/wire-forms ..................... ALREADY DONE
  Tables — nyoncode/wire-table ............................ DONE
  Resources & pages — nyoncode/wire-panels ...... NOTHING TO RUN
  Admin shell — nyoncode/wire-admin ....................... DONE
  Run with --force to set up the parts marked ALREADY DONE again.

 INFO  Available, not installed here

  Users — User administration, with roles where the application has them.
  composer require nyoncode/wire-module-users
  …
```

Jeden řádek na část, zakončený tím, co se s ní stalo — stejný tvar, jaký používá
fáze nastavení níž. Dřív to byly tři seznamy: co se našlo, co už je nastavené a
co se právě spouští — většina částí tam byla dvakrát a některé třikrát, pokaždé
jiným slovníkem.

**Dvě otázky, a druhá jen tehdy, když dává smysl.** Nejdřív stack — formuláře,
tabulky, sortable, resources, shell. Hotové oblasti přijdou na řadu až potom, a
jen když je admin shell součástí odpovědi — ať zaškrtnutý teď, nebo nastavený už
dřívějším během: modul se vykresluje *uvnitř* panelu, a nabízet oblast uživatelů
někomu, kdo si panel nevzal, je nabízet mu obrazovku, která nemá kde se objevit.
Odškrtněte shell a moduly se vypíšou jako `LEFT ALONE`, místo aby se nainstalovaly
za vašimi zády.

**Kroky se počítají, neslibují.** Každá fáze na začátku vypíše `Step N — …` a
číslo odpovídá tomu, kolik fází tento běh opravdu má: `--all` se na nic neptá,
takže jeho prvním nadpisem je instalace, a `--dry-run` napíše „What would be
installed“, místo aby předstíral, že instaluje. Log, který skončí po `Step 3`,
říká, kde se běh zastavil.

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

**Migrace, kterou aplikace už má, se vynechá.** Soubor na disku není jediný způsob,
jak ji mít: `schema:dump --prune` soubory smaže a tabulky nechá, stejnou tabulku
může vytvářet jiný balíček a `fortify:install` i instalátor oprávnění publikují
své migrace bez ohledu na to, co aplikace má. Každá migrace, kterou instalátor
během běhu zapíše, se hned při zápisu porovná s databází a se všemi ostatními
migracemi, které migrátor spustí — a ta, jejíž tabulky a sloupce už všechny
existují, se odstraní a ohlásí. Částečný překryv zůstane a selže při `migrate`,
kde je to vidět.

**`--force` nastaví každou část bez ohledu na to** a předá `--force` dál, takže
každý instalátor publikuje přes to, co zapsal. To je přepínač pro upgrade — a ten,
po kterém sáhnout, když se přeskočila část, kterou jste spustit chtěli.

**Selhaný instalátor shodí celý příkaz.** Návratový kód instalátoru každého
balíčku je zároveň návratovým kódem tohoto příkazu a části, které selhaly, se
vypíšou jménem — skriptované nasazení, které nevidí selhané publikování, je horší
než žádné.

**Vlastní výpis každého instalátoru jde stranou.** Tisknou banner, číslovaný krok
na každý publish tag, fajfku na každý soubor a seznam dalších kroků — tucet řádků
na balíček, tedy přesně to, co výpis výše nahrazuje. Odloží se do bufferu a vypíše
celý ve chvíli, kdy některý selže, a s `-v` kdykoli si o něj řeknete.

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
 INFO  Step 4 — Setting up this application.

  Fortify .... publish its config and provider, or the sign-in screens have no routes   WOULD RUN
  Teams ............................ scope roles to teams, if this application has them   WOULD RUN
  Roles & permissions ........ publish the permission config and patch your user model   WOULD RUN
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
| [Fortify](../modules/auth.md) | Spustí `fortify:install` a pak se zeptá, které funkce přihlášení mít — registraci, obnovu hesla, ověření e-mailu, dvoufázové ověření, passkeys — zaškrtnuté tak, jak je má publikovaný config, a každou v `config/fortify.php` zakomentuje nebo odkomentuje, i s celým blokem voleb. Bez toho jsou obrazovky přihlášení zaregistrované, ale jejich routy ne: přihlašovací stránka je 404 |
| [Teams](../modules/teams-and-two-factor.md#jak-zapnout-tymy) | Zeptá se, zda jsou role vázané na týmy; při „ano“ publikuje config oprávnění, pokud ještě není, nastaví `permission.teams` na `true`, zapíše `WIRE_USERS_TEAM_MODEL` a — pokud zadáte jinou — i relaci. Ptá se schválně před rolemi: instalátor rolí končí vlastním `migrate` a Spatie migrace čte `permission.teams` ve chvíli, kdy běží. Tabulky vytvořené bez týmů se berou jako „ne“, takže se znovu neptá |
| [Roles & permissions](../modules/teams-and-two-factor.md) | Spustí `permission-extended:install`, který publikuje config a migraci oprávnění, spustí ji a přidá `HasRoles` na váš model uživatele. Migraci Spatie vynechá, pokud tabulky oprávnění už existují. Do té doby obrazovky rolí prostě chybí. Potřebuje `nyoncode/laravel-permission-extended` a řekne, když chybí |
| Database tables | Spustí čekající migrace. Tři moduly si o to řekly ve vlastních instalátorech a ani jeden s tím nemohl nic udělat |
| [Routes](../panels/modules.md) | Zapíše do `routes/web.php` skupinu s `Route::wireResources()`, pod prefixem a middlewarem, na které se zeptá — obojí je v tom souboru PHP, takže odpověď, která není cesta URL nebo název middlewaru, se zeptá znovu. Bez toho je každá obrazovka 404 |
| [First administrator](../modules/users.md) | Založí účet, kterým se přihlásíte, a tam, kde aplikace role má, se zeptá, zda má být super-admin — ten může všechno, ve všech týmech. Nic v celém stacku ho předtím nevytvořilo; odpovědí byl `php artisan tinker`. Vždycky jen ten *první*; každý další účet je `php artisan wire:user`. Super-admin se přiděluje globálně, takže jím může být i první účet, který v žádném týmu není. Když se role nastavily v tomtéž běhu, přidělí ho `wire:assign-role` v novém PHP procesu, protože tento načetl model uživatele ještě před úpravou |
| [Media links](../modules/media.md) | `storage:link`. Bez něj uploady fungují, náhledy se generují a každý obrázek je 404, které nikde nezahlásí chybu |
| [Audit recording](../core/audit.md) | Zapne zaznamenávání, aby obrazovka auditu nebyla pohledem do prázdné tabulky |
| [Stored notifications](../modules/notifications.md) | Přidá vedle toastu driver `database`, aby zvoneček měl co ukazovat |
| [Settings cache](../modules/settings.md) | Přesune cache nastavení z databáze, kde ji čtení stojí zrovna ten dotaz, který měla ušetřit — a nabídne jen paměťová úložiště, která opravdu odpovídají, protože čerstvý Laravel má `redis` v configu, ať už běží, nebo ne |
| Frontend build | `npm install && npm run build`, aby Tailwind zkompiloval třídy z views balíčků. Dokud neproběhne, shell nemá šířku, barvu ani chybu |

**Zjisti, zeptej se, udělej.** Krok se nejdřív podívá a nabídne se jen tehdy, když
je potřeba — takže druhé spuštění příkazu je tiché. Co už platí, se pojmenuje a
nechá být.

**Odškrtnutá část si vezme své kroky s sebou.** Každý krok říká, ke kterému balíčku
patří, a na balíček, který byl v první půlce nabídnutý a vynechaný, se druhá půlka
neptá — odškrtnout modul médií a pak dostat otázku, zda pro něj propojit veřejný
disk, by byl instalátor, který se ptá na něco, na co už dostal odpověď.
*Nabídnutý* je tu klíčové slovo: modul nainstalovaný minulý měsíc v tomto běhu
otázkou není, takže jeho kroky proběhnou dál, a migrace nepatří žádné části, kterou
by šlo odškrtnout.

**Krok, který vybuchne, se ohlásí, neshodí běh.** `Blocked` pokrývá to, co krok
viděl dopředu; migrace, která koliduje s tou, kterou aplikace už spustila, přijde
jako výjimka. Odchytí se, pojmenuje a běh pokračuje dalšími kroky — příkaz stejně
skončí nenulově.

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
php artisan wire:user --name=Jana --email=jana@example.com --password=… --super-admin
php artisan wire:user --email=… --password=… --role=editor --role=support
```

Co nedostane, na to se zeptá; co nedostane **a** na co se nemá koho zeptat, běh
zastaví, místo aby si to vymyslelo — vygenerované heslo v deploy logu je heslo
v logu. Tam, kde aplikace role má, nabídne ty, které existují.

Adresa musí projít stejným pravidlem jako ve formuláři uživatele — `admin`
adresa není — a adresa, která už účet má, se ohlásí právě takhle. Když ji píšete,
zeptá se znovu, nejvýš třikrát; když přijde jako `--email`, příkaz selže, protože
skript chce návratový kód, ne otázku. Krok instalátoru se ptá stejně.

**Super-admin mezi nimi nikdy není.** Může všechno, ve všech týmech, takže je to
samostatná otázka: `--super-admin`, nebo — jen u prvního účtu, kterým si někdo
otevírá dveře — potvrzení, které přesně tohle řekne. Přiděluje se globálně, takže
nepotřebuje tým, a `--role=super-admin` se odmítne. Totéž platí na obrazovkách:
výběr rolí ho nikdy nenabídne a uložení uživatele ho nikdy neodebere.

Účet, který už existuje, dostane role přes `wire:assign-role`, který nikdy nesahá
na jméno ani heslo:

```bash
php artisan wire:assign-role jana@example.com --super-admin
php artisan wire:assign-role jana@example.com --role=editor --role=support
php artisan wire:assign-role jana@example.com --role=editor --team=3
```

`--super-admin` se všude, kde je kdo odpovědět, nejdřív zeptá, a `--team`
nebere. Každá jiná role jde tam, kde jsou role vázané na týmy, do týmu z `--team`
nebo do aktuálního týmu účtu; tým, jehož členem není, se odmítne, protože role by
ležela tam, kde ji přepínač nikdy nenabídne.

### Jak přidat vlastní krok

Balíček do toho seznamu přispěje registrací kroku, stejně jako registruje cokoli
jiného. Instalátor se nikdy nedozví, o co v tom kroku jde:

```php
use NyonCode\WireCore\Foundation\Setup\SetupRegistry;

$packager->registeredPackage(fn () => SetupRegistry::instance()->register(WarmTheIndex::class));
```

Třída implementuje `NyonCode\WireCore\Foundation\Setup\Contracts\SetupStep`:
`label()`, `state()` (`Done`, `Pending` nebo `Blocked`), `summary()`, `apply()`,
`package()` a `sort()`. `state()` se smí jen dívat — běží i pod `--dry-run` — a
`apply()` se ptá přes `SetupConsole`, ne přes příkaz, což je to, co dovolí krok
otestovat bez terminálu. Konzole má `confirm()`, `ask()`, `secret()`, `choose()`
pro „který“ a `select()` pro „které z těchto“; každá metoda vrátí svou výchozí
hodnotu, když není kdo by odpověděl, takže výchozí hodnota je to, co krok
považuje za bezpečné nechat, jak je.

`package()` je composer jméno balíčku, ke kterému krok patří, a právě to krok váže
na zaškrtávací políčko výše: vraťte jméno svého balíčku. `sort()` určuje pořadí —
nižší běží dřív a migrace běží na `100`, takže krok, který publikuje migraci nebo
nastavuje přepínač, který migrace čte, musí mít nižší číslo.

Krok, který mění publikovaný config soubor, použije
`NyonCode\WireCore\Foundation\Setup\ConfigFile`, a jen pro hodnotu, za kterou
nestojí žádné `env()` — první odpovědí je `EnvFile`. `set('teams', 'true')`
přepíše jeden jednořádkový `'key' => value,`, který se v souboru vyskytuje právě
jednou, a pro cokoli jiného vrátí `false`: klíč ve dvou sekcích, hodnotu přes víc
řádků, soubor, který si aplikace přepsala. Krok, který dostane `false`, řekne
člověku, který řádek má změnit.

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
php artisan wire:assign-role jana@example.com --role=editor  # role pro účet, který už existuje
php artisan wire:assign-role ada@example.com --role=admin --global  # administrátor všech týmů
php artisan wire:revoke-role ada@example.com --role=admin --global  # …a zpět
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
