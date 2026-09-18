---
title: Audit přes moduly — šest balíčků a seamy mezi nimi
date: 2026-09-17
scope: packages/module-{auth,users,media,settings,audit,notifications} + Module kontrakt,
       packages/{core/src/Core/Modules,panels,admin,suite,boost}
status: audit (nálezy, ne plán oprav)
method: 5 agentů statickým čtením (bez Testbenche, paralelně), runtime ověření
        sériově po nich — 26 z nich CONFIRMED scratch testem, který byl smazán
---

# Audit přes moduly, 2026-09-17

Modulové balíčky v `audit-matrices.md` do dneška nebyly vůbec. Tohle je první
průchod přes všech šest plus kontrakt, který je drží pohromadě.

**Metoda a její mez.** Pět agentů četlo staticky a paralelně; runtime ověření
proběhlo až po nich sériově, protože dva Testbench procesy naráz rozbíjejí
sdílený publikovaný skeleton ([[preview-server-races-the-suite]]). Nálezy níže
jsou proto rozdělené na **CONFIRMED** (scratch Pest test proběhl, výstup je
citovaný) a **PLAUSIBLE** (jen čtení). Dva nálezy prvního kola byly planý
průchod — `A10` a `A11` „prošly", protože fixture v `module-audit` nepoužívá
`HasAuditable` a entry vznikla ve stejné sekundě jako `now()`. Oba se po opravě
testu potvrdily. To je důvod, proč se sem nepíše nic, co neproběhlo.

---

## 1. Verdikt

Moduly jsou hotové jako *funkce* a nehotové jako *hranice*. Devět nálezů je
bezpečnostních a všechny mají stejný tvar: **kontrola existuje, ale ne na tom
konci, kam chodí požadavek.** Policy se ptá bez modelu, scope se aplikuje na
výpis a ne na detail, ability visí na route middlewaru, kterým Livewire round
trip neprojde, validace uploadu žije v jednom ze čtyř vstupů.

Druhá skupina je `Module` kontrakt sám. Tři moduly si přepisují navigační
skupinu, registrace v configu shazuje boot, a translator-cache past drží pět
zkopírovaných komentářů a nic víc.

---

## 2. Bezpečnost (CONFIRMED)

### 2.1 auth F1 — reset token v plaintextu

`packages/module-auth/src/Actions/MailResetCode.php:42` vkládá brokerův token do
`payload`, `Services/DatabaseOneTimeCodes.php:58` ho zapíše jako holé JSON do
`text` sloupce.

```
payload => {"token":"0edb0e264d92e3afac2f6e8462678fe8a55317f2f9f1a4519fa03ea34ccd2180"}
```

Laravel ten samý token ukládá jako bcrypt hash
(`DatabaseTokenRepository.php:72`, s `#[\SensitiveParameter]`). Čtenář jedné
tabulky POSTne `email` + `token` na Fortify `/reset-password` a **šest číslic
nepotřebuje** — mimo počítadlo pokusů, resend okno i route throttle. Zbývá jen
brokerova hodinová expirace.

Protiřečí slibu ve vlastní migraci (ř. 28–30: „A database copy is then a list of
dead hashes rather than a set of live credentials") i `docs/modules/auth.md:262`.

**Oprava:** token v payloadu nedržet. Buď `Crypt` nad payloadem, nebo — lépe —
token z payloadu vyhodit a nechat `PasswordResetCodeController` vydat nový po
úspěšném ověření kódu, aby plaintext nepřežil request, který ho odeslal.

### 2.2 auth F2 — resend je orákulum na existenci účtu

`Actions/SendOneTimeCode.php:46-52` vrací `false` pro „uživatel neexistuje"
i pro „ptáš se moc brzy"; `Http/Controllers/CodeLoginController.php:109-114`
z toho dělá dvě různé hlášky.

```
known    => "If that address is one of ours, the code is on its way."
stranger => "A code has just gone out — give it a moment before asking for another."
```

A prohozeně oproti tomu, co ty věty říkají. Cena za sondu je session + 61 s;
`throttle:6,1` je per-IP, ne per-adresa. Docblock kontroleru (ř. 30–32) i
`SendOneTimeCode` (ř. 20–25) tvrdí, že tahle díra je zavřená.

**Oprava:** `send()` má v jediném toku s nedůvěryhodným identifikátorem říkat
totéž vždy. Autentizované toky (`SecondFactorCodeController`,
`EmailVerificationCodeController`) se netýkají — tam je subjekt už známý.

### 2.3 media #1 — konvenční policy shodí každou mutaci

```
ArgumentCountError: Too few arguments to function update(), 1 passed and exactly 2 expected
  at Gate.php:851 ← MediaAccess.php:55
```

`Livewire/MediaManager.php:938-947` volá `refuse($ability)` bez modelu,
`Support/MediaAccess.php:35` má default `$target = Media::class`, a `Gate` u
string argumentu udělá `array_shift` (`Gate.php:842-852`) a zavolá
`$policy->update($user)`. Policy z `make:policy --model=Media` = 500 při
přejmenování, přesunu, mazání, replace i uložení detailu.

Vlastní testy modulu (`tests/Feature/MediaAccessTest.php:50-88`) deklarují policy
metody **bez parametrů**, takže tenhle tvar nikdy nikdo neprošel.

Druhá půlka defektu: per-record policy („smíš mazat jen svoje nahrané") se
nezeptá nikdy, takže class-level `true` autorizuje jakýkoli řádek.

**Oprava:** předat subjekt do `MediaAccess::allows()` pro per-record ability
(`view`, `update`, `delete`, `replace`); holý class tvar nechat jen pro
`create`/`viewAny`.

### 2.4 media #9 — picker smaže knihovnu

`MediaPicker extends MediaManager` (`Livewire/MediaPicker.php:22-24` mění jen
`$picking = true`) a je přes `PageChrome::add()` na **každé** stránce panelu
(`WireModuleMediaServiceProvider.php:62-68`).

```php
Livewire::test(MediaPicker::class)->set('selected', [$id])->call('deleteSelected');
// Media::count() === 0
```

Kdokoli má na stránce formulář s `MediaField`, má adresovatelné `deleteSelected`,
`deleteFolder`, `moveTo`, `saveRename` a editor nad celou knihovnou. Bez
registrované policy — dokumentovaný default — smaže soubory i řádky.

### 2.5 media #2 — `accepts` a `max_size` nevynucuje nic

Ty klíče čte jedině create formulář `Resources/MediaResource.php:123-124`. Drop
zóna, folder upload, picker a editor jdou rovnou do `Actions/StoreUpload.php`,
který nevaliduje; `rg "validate|rules\(" packages/module-media/src` nevrací nic.

```php
config()->set('wire-module-media.accepts', ['image/png']);
config()->set('wire-module-media.max_size', 1);
// 500 kB x.svg → Media::count() === 1
```

Toast přitom uživateli hlásí „The limit is :size kB" (`manager.blade.php:872`) —
limit, který nikdo nevynucuje. Jediný strop v provozu je globální
`livewire.temporary_file_upload.rules`.

Navazuje na **#3 (PLAUSIBLE)**: `Http/Controllers/MediaController.php:84-97`
servíruje `Content-Type` ze zapsaného řádku, `Content-Disposition: inline`, bez
`X-Content-Type-Options: nosniff`. Nahrané SVG/HTML = stored XSS na vlastním
originu. Na default disku `public` navíc soubor servíruje web server přímo a
kontroler se vůbec nepotká.

**Oprava:** allowlist podle obsahu (ne podle klientské hlavičky) v
`StoreUpload`, `nosniff` vždy, `attachment` pro všechno mimo krátký inline-safe
seznam.

### 2.6 notifications #1 — detail vydá cizí notifikaci a označí ji přečtenou

`Pages/ViewNotification.php:22-29` přepisuje `mountedRecord()`, ne
`resolveRecord()`, takže dědí nescopovaný
`ResolvesOneRecord.php:108-129` (`$model::query()->find($this->record)`).

```
renders Sams payload       => true
read_at after Jane opened it => 2026-09-17 18:20:41
```

`NotificationResource::scopeToViewer()` existuje a je deklarovaný vlastník téhle
otázky (`:106-123`) — volá ho ale jen výpis (`ListNotifications.php:201-213`).
Docblock `ListNotifications.php:41-43` tvrdí opak.

### 2.7 notifications #7 — `scope => 'all'` nemá čím se bránit

```php
config()->set('wire-module-notifications.scope', 'all');
Livewire::test(ListNotifications::class)->call('delete', $someoneElsesId);
// řádek: GONE
```

Modul neposkytuje žádný `permission` klíč; `NotificationResource` nemá ani
`permission()`, ani `guard()`. Oba sourozenci to mají
(`AuditResource:92-98,281-302`, `SettingsResource:70-79,99-149`) a
`docs/modules/notifications.md:60-61` říká, že tenhle režim „wants a permission
on the page rather than a config key alone".

### 2.8 audit #10 — trail zapisuje tokeny a 2FA seedy

`packages/core/config/wire-core.php:301-305` má `exclude_columns` =
`['password', 'remember_token']`; `Audit/AuditLogger.php:133-146` je exact-key
`array_diff_key`, bez `*_token` / `*_secret` pravidla.

```
new_values recorded => [
  "api_token" => "live-token-value"
  "two_factor_secret" => "SECRET-SEED"
  "two_factor_recovery_codes" => "[\"aaa\",\"bbb\"]"
]
```

`password` vyloučený je. `AuditResource.php:253-261` to celé vykreslí na
obrazovku komukoli, kdo log otevře (ve výchozím stavu bez gate), a trail je
navržený jako dlouhověký (`retention_days` default `null`).

### 2.9 settings #8 — ability je jen route middleware

`Pages/SettingsPage.php:136-149` re-kontroluje jen `SettingsGroups::authorized()`,
což vrací `true` pro každou skupinu neimplementující `GuardsSettingsGroup` —
dokumentovaný default. `wire-module-settings.permission` se přes
`RoutePage.php:143-151` stane `can:` na `GET`, a zápis jede přes
`POST /livewire/update`.

```
setting value after the ability was revoked => "written-after-revocation"
```

Komentář na ř. 138–141 ukazuje, že se na tenhle tvar útoku myslelo — u `group`
property, a o úroveň výš se přestalo.

### 2.10 users H1 — vlastní profil má zamčený e-mail

`Support/AccountGuard.php:56` — `mayChangeCredentials()` nemá větev „aktér *je*
ten účet":

```php
return self::mayEdit($account, $actor) && ! self::managesOneTeam($actor);
```

a `managesOneTeam()` (`:120`) je `Teams::enabled() && ! Teams::seesEveryTeam(...)`,
tedy **true pro každého, kdo nemá globální `users.update`** — ne jen pro team
managery.

```
may Mia change the credentials on her own account => false
```

`UserResource.php:208-209` pole vykreslí disabled s hintem, který sám říká
„…or by the person themselves", a `:265-268` adresu ze save vyhodí. Uložení
hlásí úspěch. `AccountProtectionTest.php:131` deklaruje zamýšlené pravidlo,
které kód neimplementuje.

---

## 3. Ztráta dat a integrita (CONFIRMED)

| Nález | Důkaz |
|---|---|
| **audit #11** — `wire-core:audit-prune --days=0` smaže celý trail. `PruneAuditEntriesCommand.php:27-40` bere `is_numeric`, takže i `0` a `-30`; bez `confirm()`, bez `--force`, bez podlahy. `AuditLogger.php:99-102` pak `subDays(0)` = teď. | `entries left after --days=0 => 0`, hlášeno jako úspěch |
| **audit #9** — builder-level mutace nezanechá stopu. `HasAuditable.php:38-70` hákuje jen `created`/`updated`/`deleted`; `Model::query()->update()`, `->delete()`, `insert()`, `upsert()` i `restored`/`forceDeleted` jdou mimo. Prázdný stav (`AuditResource.php:157-161`) slibuje opak. | `updated entries after a builder update of 1 row => 0` |
| **auth F5** — odmítnuté nové heslo spálí kód natrvalo. `PasswordResetCodeController.php:70-81` ověří (a tím zkonzumuje) dřív, než request předá Fortify, jehož password rules ho ještě můžou odmítnout. Uživatel musí začít od `/forgot-password`, kde ho odmítne brokerův 60s throttle. Docblock ř. 50–53 slibuje opak. | `rows left after a rejected password => 0` |
| **auth F3** — konzumace je slepý delete. `DatabaseOneTimeCodes.php:122-128` nemá `id` predikát a zahazuje počet dotčených řádků. Dva souběžné requesty oba projdou `Hash::check` a oba uspějí. | `delete from "wire_auth_one_time_codes" where "purpose" = ? and "identifier" = ?` |
| **auth F4** — `issue()` je delete-then-insert bez transakce proti unique indexu `(purpose, identifier)`. Dvojklik na „Pošli mi kód" = neošetřená `QueryException` na přihlašovací obrazovce. | `delete` + `insert`, žádné `begin transaction` |
| **settings #13** — `Setting::query()->update()` cache neuvidí. `Models/Setting.php:31-39` hákuje jen model eventy, `Support/Settings.php:163` je `forever`. Docblock tvrdí, že to pokrývá „a seeder, a factory, a data migration". | `value read back after a builder update => "old.png"` |
| **settings #12** — `remove()` a `clear()` nedispatchují `SettingsSaved`, takže listener přestavující odvozený stav o mazání neví. | `SettingsSaved dispatches for set + remove => 1` |
| **settings #2** — `wire-module-settings.table` přejmenuje tabulku modelu, migrace hardcoduje `wire_settings`. Čtení pak navždy vrací defaulty bez chyby, první zápis hodí `QueryException`. `aboutData()` tabulku nehlásí. | `app_settings created => false`, `wire_settings created => true` |

---

## 4. Seamy mezi moduly

### 4.1 CONFIRMED

**Navigační skupina `system` má tři vlastníky a poslední vyhrává.**
`NavigationGroups.php:36-39` je `$this->groups[$key] = $group` — nahrazení.
Registrují ji `AuditModule` (sort 95), `NotificationsModule` (96),
`SettingsModule` (97), a pořadí je pořadí discovery providerů, což si repo samo
(`SetupRegistry.php:27-30`) označuje za „not a contract".

```
H1: label=System sort=97   ← nakonfigurovaná "Operations" sort=10 prohrála
```

Nejhorší varianta: vyhraje `NotificationsModule`, jehož vlastní řádek je defaultně
skrytý, a rozhodne nadpis za dva moduly, které řádek mají.
`boost/src/Support/ModeReflector` pak hlásí deklarovanou skupinu, ne tu, co přežila.

**Registrace modulu v `wire-core.plugins` shodí boot.**
`WireCoreServiceProvider.php:777` volá `register()` bez `has()`, proti
`PluginManager.php:78-80`, které hází. Providery modulů se chrání
(`WireModuleAuditServiceProvider.php:36-38` a sourozenci), config smyčka ne.
Container ordering kolizi dělá jistou, ne možnou: moduly používají `resolving`,
config smyčka `afterResolving`.

```
PluginRegistrationException ... thrown in PluginRegistrationException.php on line 22
```

Je to přesně ta oprava, po které sáhne někdo, kdo narazil na kolizi skupin.

**`navigation.icon` má pět významů.** `UsersModule.php:41` ho čte pro skupinu
a `UserResource.php:115` pro řádek; `audit`, `settings` i `notifications`
hardcodují `outline:wrench-screwdriver` a svůj config klíč ignorují;
`MediaModule.php:27` hardcoduje `outline:rectangle-stack` proti configu
`outline:photo`. `Module` neříká, co ten klíč znamená.

**`DomainModule` straší v boost guidelines.**
`boost/resources/boost/guidelines/wire-core.blade.php:144` jmenuje třídu, která
neexistuje — v `core/src/Core/Modules/` je jen `Module.php`.
`composer boost:check-docs` (`scripts/sync-boost-docs.php`) diffuje jen
`docs/**.md`; `guidelines/` a `skills/` jsou mimo jeho korpus.

**Shell suggeruje jen `module-auth`.** Zbylých pět nesuggeruje nic.
`tests/Integration/PackageGraphTest.php:70` `suggest` parsuje a nikdy na něj
neasertuje.

### 4.2 PLAUSIBLE

**`wire:install` nikdy nenabídne modul bez `wire-admin`.**
`WireInstallCommand.php:53` + `:511-528` — otázka na moduly padne jen když je
shell mezi vybranými nebo usazenými, a kód neumí rozlišit *odškrtnuto* od
*nenainstalováno*. `composer require nyoncode/wire-panels nyoncode/wire-module-users`
bez shellu (dokumentovaný opt-out, ADR 0028) → modul se vypíše jako
`LEFT ALONE`, `declined()` (`:400-412`) ho zařadí mezi odmítnuté, `steps()`
(`:360-378`) vyhodí `CreateFirstAdministrator`, `EnableRoles` i `EnableTeams`.
`--all` funguje, interaktivní běh nikdy.

**Instalace admin shellu sama o sobě nedá přihlášení.** Stránky panelu jsou za
`['web','auth']` (`panels/config/wire-panels.php:76`), route `login` vlastní
Fortify a zapojuje ji až komponenta *Sign in* přes
`module-auth/src/Install/ConfigureFortify.php`. Bez ní Laravel 12 vrátí
`response()->noContent(401)` (`Foundation/Exceptions/Handler::unauthenticated`,
`Authenticate::redirectTo()` bez callbacku vrací `null`) — prázdná odpověď,
žádná hláška. Instalátor na tu kombinaci neupozorňuje.

**Translator-cache past drží pět zkopírovaných komentářů.** Workaround je jen
próza v pěti modulech (`UsersModule.php:45-51` a sourozenci). Není v docblocku
`Module::navigation()` (`Module.php:92-103`), v ADR 0029/0030, v `AI_BLUEPRINT.md`
ani v `boost/.../guidelines/wire-modules.blade.php` — a
`docs/panels/modules.md:74` učí eager `->label(__('nav.billing'))` ve
`[tl! focus]` spotlightu. Testy modulů to nechytí: instanciují modul v už
nabootované aplikaci, kde se eager `__()` přeloží správně.

Seamová oprava je dostupná: `bootModules()` volá `navigation()` eagerně; core by
modul mohl uložit a `navigation()` zavolat až při prvním čtení
(`find()`/`all()`), a povinnost by zmizela.

**Sedm hardcodovaných seznamů balíčků bez křížové kontroly.**
`suite/.../Catalogue.php:49-145`, `boost/.../WirePackages.php:30-56`,
`PackageGraphTest::stackOrder()`, root `composer.json` repositories,
`split.yml:16-29`, `coverage-floors.json`, `phpunit.xml`. Gated jsou dva.
`WireInstallTest.php:195-203` asertuje `toContain` na šest jmen ze čtrnácti;
`verify-coverage.php:232-233` u nezapsaného balíčku vypíše
`(no floor recorded)` a neselže.

**`PackageGraphTest` kontroluje jen `require`.** `self.version` pinning,
`require-dev` ani `suggest` neasertuje, přestože `AI_BLUEPRINT.md` pinning
označuje za závazný. Dnes jsou všechny hrany správně; nic je tam nedrží.

**`wire:install` pustí setup kroky i po selhání komponenty**
(`:165-224` zaznamená a pokračuje, `declined()` testuje „chosen", ne „uspělo"),
takže běh skončí exit 1 až poté, co aplikaci změnil.

**`Setup::pending()` vidí jen publish tagy**, takže modul deklarující
`hasViews()`/`hasTranslations()` bez jejich publikování je „ALREADY DONE"
navždy a cesta k nim (`vendor:publish --tag=…::views`) se neobjeví nikde.

---

## 5. Zbytek — PLAUSIBLE, neověřeno

- **users H2** — `SetCurrentTeam` se neregistruje, když je `permission.teams`
  zapnuté a `Teams::enabled()` false (`WireModuleUsersServiceProvider.php:158-160`).
  Přesně ten stav, který `wire:install` nechává za sebou, než aplikace napíše
  `App\Models\Team`. `getPermissionsTeamId()` zůstane `null`, team-scoped role
  zmizí, panel 403. Test pokrývá jen `permission.teams = false`.
- **users M1** — `Concerns/SyncsPermissions.php:19-36` nemá žádnou autorizaci,
  zatímco sourozenec `SyncsRoles` má tři. Dnes chráněno jen route guardem
  v `EditRole`. `RoleGrants::clamp()` navíc *odebere* oprávnění chybějící
  v `$selected` — na hostu bez guardu je to lockout, ne eskalace.
- **users M2** — `EnableTeams.php:191` zapíše `teams.model` do běžícího configu,
  `teams.relation` (`:197-210`) jen do souboru. Zbytek téhož běhu `wire:install`
  hledá starou relaci.
- **users M3** — `AccountGuard::isLastSuperAdmin()` staví SQL z nefallbackovaného
  configu a hardcoduje `"{$roles}.id"` místo `getQualifiedKeyName()`.
- **notifications #3** — `ListNotifications.php:204` hardcoduje
  `DatabaseNotification`, zatímco `NotificationResource::modelClass()` čte config.
  Výpis a detail se pak neshodnou na tom, které řádky existují.
- **notifications #4** — chybí guard i varování instalátoru pro nemigrovanou
  `wire_notifications`; `module-audit` na totéž má `AuditLog::available()`.
- **audit #5** — `AuditResource::navigation()` nemá `visible()`, takže
  nakonfigurovaný `permission` vyrobí položku v menu, která 403.
  Ověřeno: `sidebar entry visible to someone refused audit.view => true`.
  (Sourozenec `SettingsResource.php:86-89` to řeší a komentářem vysvětluje proč.)
- **media #5** — `SyncsMediaUsage` attachuje id vytažená regexem z HTML bez
  ověření existence, proti FK s `cascadeOnDelete`. Stará `data-media-id`
  v těle článku = neuložitelný záznam. SQLite to v testech skryje.
- **media #6** — `accepts` s více prefixy je neuzávorkovaný `orWhere`
  (`MediaManager.php:870-877`). **CONFIRMED**: ve složce se zobrazilo PDF z rootu.
- **media #8** — `editingId`/`editorIntent`/`editorUpload` jsou veřejné property
  bez `#[Locked]`; replace cesta nezopakuje guardy z `openEditor()`.
  **CONFIRMED**: PDF řádek dostal přepsané bajty PNG.
- **media #7/#10/#11/#12** — hardcodovaná jména tabulek v migracích proti
  configurovatelným v modelech; deduplikace podle checksumu nescopovaná na disk;
  `path` mass-assignable bez normalizace; `Content-Disposition` skládaný
  `addslashes` místo `HeaderUtils::makeDisposition()`.
- **auth F6** — reset obrazovky renderují `fortify.username`, kontrolery
  validují `fortify.email`. Na defaultu splývají; aplikace přihlašující na
  osobní číslo má reset hesla nepoužitelný.
- **auth F7/F8/F9/F10** — expirované kódy nikdo nesklízí (config tvrdí
  „swept by expiry"); `attempts` je `tinyint` proti neomezenému configu;
  plain kód je veřejná property bez `#[\SensitiveParameter]`;
  `ResetPassword::toMailUsing()` je globální static přes všechny brokery.

---

## 6. Co bylo čisté

Zaznamenáno, aby to příští audit neprocházel znovu:

- **Config-key drift** — nulový ve všech šesti modulech. Každý deklarovaný klíč
  se čte, každé čtení má klíč v souboru, inline defaulty souhlasí.
- **Translation-key drift** — EN a CS sady klíčů identické všude. Mrtvé klíče:
  `module-users` (`team`, `teams`), `module-notifications` (`title`, `unread`,
  `mark_read`).
- **Translator-cache past** — všech šest modulů ji dnes obchází správně
  (closure v `->label()`). Problém je, že to nic nedrží — viz §4.2.
- **Package graph** — přeodvozený ze všech čtrnácti `composer.json`: žádná hrana
  nahoru uvnitř stacku, žádný modul nevyžaduje jiný, jen `wire-suite` vyžaduje
  `wire-admin`, všechny sourozenecké constrainty `self.version`.
- **Hook surface** — všech 151 `@wireEl` jmen z `module-*`, `panels` a `admin` je
  v `scripts/hook-names.json`.
- **Coverage floors** — všech šest modulů zapsaných na 100.
- **Docs** — všech šest má `docs/modules/*.md` s CS párem a shodným počtem
  `##` nadpisů; boost mirror `docs/` je byte-identický.
- **`navigation()` vracející `null`** — přežijí to všichni konzumenti.
- **Duplicitní `getId()`** — odmítnuto hlasitě, ne last-wins.
- **settings** — casting round-trip (`false`, `null`, `''`, `0`, `[]`) drží;
  `updateOrCreate` má vždy neprázdnou match sadu; `SettingsRegistry` je
  idempotentní.
- **audit** — trail je přes dodané povrchy needitovatelný a nemazatelný; actor
  bez přihlášení řešený správně; morph mapa přes `MorphedModels::classFor()`;
  degradace bez tabulky přes `AuditLog::available()`.
- **auth** — entropie kódu (`random_int` po číslici), `Hash::check` místo `===`,
  TTL na čtecí cestě, dvouvrstvý throttle (route + počítadlo v DB přes
  `increment()`), `purpose` v unique indexu i ve `WHERE` (žádná záměna účelu),
  `session()->regenerate()` po obou code-login cestách, a „vypnuto" skutečně
  zavírá routu, ne jen UI.
- **media** — job hygiena (`SerializesModels` nad id), `chunkById` nad mutovaným
  sloupcem, sanitace jmen složek, registrace assetů podle
  `architecture/assets.md`.

---

## 7. Pořadí oprav

### Hotovo 2026-09-17

1. **auth F1** — token z payloadu pryč. `MailResetCode` ho zahazuje, čerstvý se
   razí z brokerova repository až v požadavku, který kód utrácí
   (`PasswordResetCodeController::mintToken()`). `createToken()` nahradí token,
   který účet měl, takže odkaz a kód nemůžou být živé zároveň.
2. **media #1** — `refuse()` bere subjekt, kolekce se filtrují po řádcích
   (`MediaManager::permitted()`). Konvenční `update(User, Media)` už neshodí
   mutaci a per-record policy se konečně ptá. Přibraly se `viewAny` na výpisu,
   `view` na detailu a autorizace na čtyřech metodách nad složkami, které
   neměly žádnou.
3. **media #9** — `picking` je `#[Locked]` a `mayMutate()` z něj odmítá
   `update`, `delete` i `replace`. `create` zůstává, aby picker mohl nahrát
   soubor, pro který si člověk přišel. Při té příležitosti i **#6** — ten
   `orWhere` je uzávorkovaný.
4. **notifications #1** — `ViewNotification` používá `ResolvesScopedRecord`,
   který se kvůli tomu přestěhoval z `module-users` do `wire-panels`: modul
   nesmí vyžadovat modul, a tuhle otázku má každá stránka se scopovaným
   výpisem. Cizí notifikace odpoví 404, ne 403.
5. **audit #10** — `AuditLogger::NEVER_LOGGED` je podlaha, ne default:
   `exclude_columns` se k ní přidává a vyprázdnění seznamu credential nevrátí.
   Podpora `*` v obou.

Mimo pořadí spravená vedlejší věc: `CreateFirstAdministrator::askPassword()`
měl konstantní větev ternáru, kterou coverage nevidí
([[coverage-dead-ternary-arm]]) — zvednutá na příkaz a pokrytá testem.

Každá oprava má regresní test, u kterého je ověřené, že proti starému kódu
padá. Brány po nich: `composer test` 8681 zelených, `coverage:verify` OK
(všechny podlahy drží), `analyse`, `pint`, `docs:check`, `docs:standard`,
`docs:api`, `docs:examples`, `hooks:verify` a `verify:drivers -- media`
(5 driverů, 98 kontrol).

### Zbývá

Pak §3 (ztráta dat) a §4 (seamy). §5 chce vlastní runtime ověření dřív, než se
na něj sáhne.

---

## 8. Co neproběhlo

- Nálezy §5 jsou jen ze čtení. Repro recepty k nim existují, ale neběžely.
- Blade a Alpine chování (media manager drag-and-drop, notifications dropdown)
  četl, nikdo ho neřídil — na to jsou jen CDP drivery
  ([[morph-markers-load-bearing]]).
- `npm run hooks:verify`, `docs:examples` ani `coverage:verify` v rámci auditu
  neběžely.
- Chování na MySQL/Postgres (collation na `identifier`, tinyint overflow u
  auth F8) je odvozené ze schématu; suite běží na SQLite.
