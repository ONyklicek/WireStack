Pokračuj podle `architecture/plans/v2-progress.md`.
Přečti **§2** (co měření změnilo na zadání) a **§4** (co zbývá otevřené).
Pak §5 — pravidla, která se za sedm běhů osvědčila.

## Stav k 2026-09-03 (druhý běh dne)

**Celá V2 je hotová (V2.0–V2.6)** a k tomu je hotový **průřezový sken duplicit**,
který §4 vedla jako otevřený od chvíle, co na něj kdysi padl session limit.
Nedělal se čtením: `composer duplicates` tokenizuje `src/` a seskupí identická
těla metod — celý repo za vteřiny, opakovatelně, skript je v repu.

Ten běh z deseti nalezených skupin **čtyři sloučil** (asset routa ve čtyřech
providerech → `Bundle::serve()`, kvalifikace sloupce ve třech clause objektech →
`ColumnReference`, tři search strategie → `LikePredicate`, a `danger()` proti
barvě, kde jeden slovník měl dvě pravidla), **pět zamítl s důvodem** a dvě nechal
otevřené pro vlastníka. Cestou našel dvě lživé aserce a nepokrytý kontrakt asset
routy ve třech balíčcích ze čtyř. Detaily z §2, tohle je rozcestník.

## Co zbývá otevřené (§4 progressu)

Nic z toho není fáze; každá položka čeká na rozhodnutí vlastníka repa nebo na
jmenovaného konzumenta. **U každé nejdřív změř, jestli je pořád pravda.**

| Věc | Poznámka |
|---|---|
| `ShellRenderPlan` / `InteractionRenderPlan` | host pořád `mixed`; polling, live kanál a readiness nemají pojmenovaný kontrakt |
| Opt-in discovery modulů | `config('wire-core.plugins')` stačí a skenování nemá konzumenta |
| Routované stránky v menu | `ResourceRoutes::urls()` existuje a je otestovaná, ale workbench shell mapuje klíč→URL sám |
| `Filter` má vlastní kopii viditelnosti z akční vrstvy | tři mechanismy na jednu otázku; sjednocení je návrhové rozhodnutí, ne přesun. §4 má změřené, proč přímé `use` traitu nejde |
| `relationship()` v forms a v table | stejné pravidlo, dvě signatury a dva názvy getteru; vlastník patří do core, ne do forms — mění veřejné API dvou balíčků |
| `ActionHalt` × `ConfirmationDialog` | není to pár metod, je to celá sdílená plocha „konfigurace potvrzovacího modálu". Sken je hlásí schválně |

## Postup (nezměněný, a je to jediné, co si odnes)

1. **ZMĚŘ, než cokoli napíšeš.** Zadání v plánech je starší než kód a za sedm
   běhů bylo špatně přes padesátkrát. Poslední případ: `basename()` v asset
   routě, který čtyři providery vedly jako ochranu před traversalem — a
   proběhnout nemohl ani jednou.
2. **Na duplicity nečti, skenuj.** `composer duplicates`, pak
   `composer duplicates -- --normalize`. Vidí ale jen *identická* těla; totéž
   pravidlo napsané jinak najde až čtení vedle.
3. **Když najdeš stejné pravidlo dvakrát, `git log -S` řekne, která kopie je
   novější.** Tenhle běh to otočilo verdikt: hlučnější kopie byla starší.
4. **Mutuj PROTI STÁVAJÍCÍ SADĚ, než napíšeš test.** Test, který projde
   i s vymazaným kódem, je lživý test — a lže i tehdy, když je aserce jen
   nepřesná: `toContain('LIKE')` projde pro `ILIKE`, `toContain('CONCAT')`
   projde pro obalený identifikátor. Obojí se stalo tenhle běh.
5. **A mutuj i to, co jsi právě napsal.** Dvě mutace tenhle běh přežily, protože
   se testovalo na chybějícím souboru — 404 vyjde stejně s pravidlem i bez něj.
   Aserci, která nerozlišuje, musíš nastražit vstup.
6. **U nové UI vrstvy prototyp na reálné entitě ve workbenchi a driver.**
7. **Brány podle `AI_CHANGE_PROTOCOL.md`.** Sáhl-li jsi na veřejné API: obě docs
   stránky (EN i CS) v jednom commitu, `composer boost:sync-docs`, a **projdi
   guidelines** — hook je hlídá jen zčásti.
8. **Coverage v obou režimech.** `COMPOSER_PROCESS_TIMEOUT=1800 composer coverage:verify`
   a `php scripts/verify-coverage.php build/clover.xml --diff=origin/1.x`.

## Provozní poznámky, které stály čas

- **`verify:drivers`: nepouštěj u toho nic jiného** a výstup si dej do souboru.
  Červený driver je napřed podezření na prostředí: bisekce je driver samotný →
  `git stash push -- <cesta>` → znovu → restart preview serveru → znovu.
- **Fixture jména v testech jsou globální.** Balíčkové sady projdou, celá
  `composer test` spadne na `Cannot redeclare class`.
- **Pint umí přepsat `{@see}` na `use`** (`fully_qualified_strict_types`) a tím
  vyrobit import přes vrstvu, který `ModuleLayersTest` shodí až v CI. V L0/L1
  souborech piš odkaz na vyšší vrstvu backtickem, ne `{@see}`.
- **Livewire 4 čte `livewire.component_layout`**, ne `livewire.layout`.
- **`RouteRegistrar::middleware()` nahrazuje, nesčítá.**
- **Přebíjíš-li metodu traitu, `parent::` není cesta zpět** — je potřeba alias
  při `use`. Projde to kompilací i PHPStanem a spadne až za běhu.
- **Trait nejde `use`, když hostitel deklaruje stejnou property s jinou
  viditelností** — fatal, ne varování. `Filter::$hidden` je `public`, trait ho má
  `protected`, a právě proto tam ta kopie je.
- **Parametr routy je v Symfony `[^/]++`, posesivně.** Segment s lomítkem *nebo
  s tečkou* nematchne routu, která za parametrem má literál. `basename()` za tím
  je mrtvá obrana; `->where()` mrtvá není (bez něj projdou `a~b`, `a b`, `á`).
- **`focus()` na skrytém elementu je no-op, který nic nehlásí.** Zaostřuješ-li po
  otevření dialogu, počkej na vykreslení (retry po `requestAnimationFrame`).
- Workbench databáze **driftuje**: driver, který data mění, musí být **vratný**.

Když měření řekne „nedělat", je to platný výsledek — napiš proč a dolož to.
Na konci aktualizuj `v2-progress.md` a commitni (bez AI atribuce).

Netrackované soubory, které nemají jít do commitu: `.mcp.json`, tenhle soubor.
/