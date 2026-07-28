---
title: Kontrakt výběru a klávesových zkratek tabulky (myš + klávesnice + přístupnost)
date: 2026-07-25
scope: packages/table (selection runtime, record actions, table view), packages/core (nic — gesta jsou table-only)
status: draft — k editaci, neschváleno
parent: architecture/plans/v2-master-plan.md
related: docs/table/record-actions.md, packages/table/resources/js/record-actions.js
implementation: architecture/plans/table-selection-gestures-implementation.md
rollout: architecture/plans/table-selection-gestures-rollout.md
---

# Kontrakt výběru a klávesových zkratek tabulky

Návrh k editaci. Sloupec **Rozhodnutí** je to jediné závazné — sloupce s Excelem,
Explorerem/Finderem a APG jsou jen podklad, ať je vidět, kde se odchylujeme
vědomě a kde bychom si vymýšleli.

Legenda stavu: ✅ hotovo · ➕ doplnit · ✏️ změnit oproti dnešku · ❌ nedělat

---

## 1. Základní princip

**Fokus ≠ výběr.** Aktivní řádek (kam míří klávesnice) je něco jiného než
zaškrtnuté řádky (co půjde do hromadné akce).

Excel to má opačně — kurzor *je* výběr, takže tam šipka výběr přepíše. To u nás
nejde: máme checkboxy a bulk akce, a šipka, která smaže zaškrtané řádky, je
ztráta dat z pohledu uživatele. Naše tabulka se proto chová jako **Excel trvale
v režimu `Shift`+`F8` (add-mód)** — což je zároveň chování Exploreru s `Ctrl`.

Z toho plyne zbytek: `F8` i `Shift`+`F8` nemají co přidat a vypadávají.

| Pojem | Význam | Vizuál |
|---|---|---|
| Aktivní řádek | kam míří klávesnice; kotva rozsahu | `activeRowClass()`, výchozí `bg-primary-100` |
| Kotva (anchor) | odkud roste `Shift` rozsah | bez vizuálu — žije jen po dobu držení `Shift`u, viz §5 |
| Výběr | co půjde do bulk akce | checkbox + `bg-primary-50` |

---

## 2. Klávesnice

| Zkratka                       | Excel | Explorer / Finder | WAI-ARIA APG | **Rozhodnutí** | Stav |
|-------------------------------|---|---|---|-|-|
| `↑` / `↓`                     | posun kurzoru, výběr = ta buňka | posun **výběru** | posun fokusu | **posun aktivního řádku, výběr netknutý** | ✅ |
| `Shift` + `↑`/`↓`             | roztažení bloku od kotvy (i zpětné zmenšení) | rozsah od kotvy | rozšíření výběru o řádek | **blok od kotvy = `base ∪ rozsah`** | ✏️ |
| `mod` + `Shift` + `↑`/`↓`     | roztažení bloku na okraj | Win: aditivní rozsah | `mod`+`Shift`+`Home`/`End` | **rozsah od kotvy na první / poslední řádek stránky** | ➕ |
| `Space`                       | (píše mezeru) | toggle v checkbox listech | **toggle fokusované položky** | **toggle aktivního řádku** | ✅ |
| `Shift` + `Space`             | vybrat celý řádek | — | rozsah od poslední vybrané k fokusované | **nic** — viz §5, zamítnuto | ❌ |
| `mod` + `A`                   | vybrat vše | vybrat vše | vybrat vše | **vybrat celou stránku** | ✅ |
| `Home` / `End`                | A1 / konec dat | první / poslední | první / poslední | **aktivní řádek na první / poslední řádek stránky, výběr netknutý** | ➕ |
| `Shift` + `Home`/`End`        | rozsah k okraji | rozsah k okraji | rozsah k okraji | **rozsah od kotvy k prvnímu / poslednímu** (totéž co `mod`+`Shift`+šipka) | ➕ |
| `PageUp` / `PageDown`         | stránka | stránka | autorem definováno | **posun aktivního řádku o viewport řádků, výběr netknutý** | ➕ |
| `Shift` + `PageUp`/`PageDown` | rozsah o stránku | rozsah o stránku | — | **roztažení rozsahu od kotvy o tentýž skok** | ➕ |
| `Enter`                       | potvrdit / dolů | otevřít položku | — | **primární record action** | ✅ |
| `Delete`                      | smazat obsah | smazat | — | **`onKey('Delete')` akce** | ✅ |
| `Backspace`                   | smazat obsah | — | — | **alias `Delete` (na Macu je `⌫` jediný Delete)** | ➕ |
| Menu klávesa / `Shift` + `F10` | kontextové menu | kontextové menu | kontextové menu | **kontextové menu řádku** (`Shift`+`F10` je alias — Menu klávesu většina notebooků nemá) | ✅ / ➕ |
| `Escape`                      | zrušit režim | — | — | **zavřít modal** (dnešní chování) | ✅ |
| `?`                           | — | — | — | **otevřít nápovědu zkratek** (viz §4) | ➕ |

`mod` = `Ctrl` na Windows / `⌘` na macOS. **Doslovný `Ctrl` v téhle sadě nepoužíváme
nikde**, a to ze dvou nezávislých důvodů: `Ctrl`+klik je na Macu pravý klik, a
`Ctrl`+`↑`/`↓` je tam Mission Control / App Exposé — systémová zkratka, kterou
stránka vůbec nedostane, takže se nedá ani `preventDefault`nout.

Rozsah roste vždy od kotvy a vždy se **drženým** `Shift`em — u šipek, u
`Home`/`End`, u `PageUp`/`PageDown` i u kliku. `mod` sám o sobě znamená
„jednotlivec / bez rozsahu", `mod`+`Shift` znamená „až na okraj". Že se `Shift`
drží po celou dobu, není detail: díky tomu kotva nikdy nemusí přežít víc než
jeden stisk (§5).

**Rozsah gest je vždy jedna stránka.** „První" a „poslední" řádek znamená první a
poslední řádek aktuální stránky, ne datové sady — stejně jako `mod`+`A` vybírá
stránku a ne všechno, co filtr matchne. Přes hranici stránky vede jediná cesta,
a tou je „Vybrat všech N odpovídajících" v bulk baru (`mode: 'all'`).

Skok `PageUp`/`PageDown` je počet celých viditelných řádků mínus jeden (obvyklý
překryv o řádek), minimálně 1 — ne pevná konstanta, aby skok odpovídal tomu, co
uživatel právě vidí.

### Nesouvislý výběr (1–4 a 8–15) čistě klávesnicí

| Krok | Zkratky |
|---|---|
| vybrat 1–4 | `Space` na řádku 1, pak `Shift`+`↓` ×3 |
| přejít na 8 bez ztráty výběru | `↓` ×4 (u nás plná šipka; v Exceli/Exploreru by to chtělo add-mód) |
| přidat 8–15 | `Space` na řádku 8, pak `Shift`+`↓` ×7 |

U delších bloků se nemusí krokovat: `Shift`+`PageDown` skočí o viewport a dá se
přestřelit — `Shift`+`↑` blok zase zmenší, protože kotva se roztahováním nehýbe.

---

## 3. Myš

| Gesto | Excel | Explorer / Finder | **Rozhodnutí** | Stav |
|---|---|---|---|---|
| klik na checkbox | — | — | **toggle řádku + kotva** | ✅ |
| `Shift` + klik | blok od kotvy | blok od kotvy | **blok od kotvy (`base ∪ rozsah`)** | ➕ |
| `mod` + klik | přidat/odebrat jednotlivce | přidat/odebrat jednotlivce | **toggle řádku + kotva, i mimo checkbox** | ➕ |
| `mod` + `Shift` + klik | přidat další blok | přidat další blok | **blok čistě přidaný k výběru** | ➕ |
| tažení | výběr rozsahu tahem | rámeček | **sweep jen přes checkboxový sloupec, jen přidává** | ➕ |
| `mod` + tažení | přidat další rozsah | — | **sweep aditivně** | ➕ |
| tažení mimo checkboxový sloupec | — | rámeček | **nic** — patří označování textu v buňkách | ❌ |
| klik na řádek | — | vybere řádek | **jen označí (aktivní řádek), nevybírá** | ✅ |
| dvojklik na řádek | — | otevře | **primární record action** | ✅ |
| pravý klik / `Ctrl`+klik (Mac) | kontextové menu | kontextové menu | **kontextové menu řádku** | ✅ |

---

## 4. Přístupnost

Tahle sekce je záměrně samostatná — zkratky bez ní nepomůžou nikomu, kdo tabulku
neovládá myší a očima.

| Téma | Stav dnes | **Rozhodnutí** |
|---|---|---|
| `role="grid"` + `role="row"` | ✅ (jen když jsou record actions) | ponechat |
| `aria-multiselectable="true"` | ❌ chybí | **doplnit na tabulku, když je `selectable()`** |
| `aria-selected` na řádku | ❌ chybí — výběr je čitelný jen z checkboxu | **doplnit** |
| `aria-rowcount` / `aria-rowindex` | ❌ chybí — čtečka hlásí „3 z 10" u stránkované tabulky | **doplnit u paginace** |
| Oznámení změny výběru | ❌ chybí | **`aria-live="polite"` region: „vybráno 5 z 240"** |
| Viditelný fokus | ✅ `focus-visible` ring | ponechat |
| Označení aktivního řádku | ⚠️ jen barva pozadí | **přidat nebarevný signál** (levý pruh / ring) — barva sama nesmí nést význam (WCAG 1.4.1) |
| Kontrast označení | ⚠️ neověřeno | **ověřit ≥ 3:1 vůči sousednímu řádku** (WCAG 1.4.11) |
| Nápověda zkratek | ⚠️ jen v preview legendě | **klávesa `?` otevře přehled zkratek** (objevitelnost) |
| Dotyk / mobilní karty | sweep i klávesnice mimo | **ponechat mimo, ale checkboxy a bulk bar musí stačit samy** |
| `prefers-reduced-motion` | ⚠️ neověřeno u sweepu | **žádná animace při sweepu** |
| Cíl kliknutí u checkboxu | 16×16 px | **zvětšit klikatelnou plochu na celou buňku (≥ 24×24)** — WCAG 2.5.8 |

---

## 5. Rozhodnuté otázky

Žádná otevřená nezůstala.

**Kotva je jednorázová a bez vizuálu.** Plná šipka ji zahodí, jak to kód dělá dnes
(`record-actions.js:206`). Všechna rozsahová gesta drží `Shift` po celou dobu, takže
kotva nikdy nežije déle než jeden stisk a není co zobrazovat. Kdyby někdy přibylo
gesto, které kotvu potřebuje napříč navigací, vrací se tím i povinnost dát jí
nebarevný marker a sladit ho se signálem aktivního řádku z §4 — je to jeden balík,
ne dvě nezávislá rozhodnutí.

**Rozsahová gesta se zapisují jako sjednocení do `selected`, ne jako „vyber rozsah".**
V `keys` módu tím rozsah přidává, v `all` módu (kde `selected` drží výjimky) tím
vyřazuje. Stejný kód, žádný `if` na mód, a symetrie s `toggle()`, který se takhle
chová už dneska. Varianta „přepnout na `keys`" je vyloučená: uživateli s vybranými
240 záznamy napříč stránkami by `Shift`+klik na 15 řádků tiše shodil výběr na 15.

**Sada zkratek nebude mít vypínač.** Bez `selectable()` se výběrová gesta nenaváží
a klávesy chytá jen řádek s fokusem (`record-actions.js:141`), takže inputy, inline
edit ani filtry v tabulce nekolidují. Na přemapování konkrétní klávesy je `onKey()`.
Cíleně se dá vypínač doplnit později; odebrat ho kvůli BC už ne.

**Sweep** jen přes checkboxový sloupec. Přes celý řádek rozbije označování textu
v buňkách a koliduje se sortable handlem.

**`PageUp`/`PageDown`** = počet celých viditelných řádků − 1, min. 1. Pevná
konstanta by na krátké i na dlouhé tabulce byla jinak špatně.

**`Home`/`End` a „okraj"** = hranice aktuální stránky, ne datové sady.

### Zvážené a zamítnuté

**`Shift`+`Space`** (rozsah od kotvy k aktivnímu řádku, dle APG listboxu) —
zamítnuto. Uzavřel by blok bez držení `Shift`u během navigace, ale totéž svede
`Shift`+`End`, `Shift`+`PageDown` i `Shift`+šipka: rozsah je nedestruktivní, dá se
přestřelit a vrátit se, kotva se nehne. Jako jediné gesto by přitom vyžadoval
kotvu přeživší běžnou navigaci, a tím i její vizuál — jedna zkratka pro pohodlí za
tři položky práce. Excelí význam téže klávesy („vybrat celý řádek") je pro nás
prázdný, protože řádek je u nás nejmenší jednotka.

---

## 5b. Nápověda zkratek (`?`)

| Otázka | **Rozhodnutí** |
|---|---|
| Modal, nebo popover? | **Modal** — obsah je tabulka o dvou sloupcích a musí jít projít klávesnicí; popover se zavírá na blur, což je u nápovědy ke klávesnici sebevražda. Přes `Modals\*` jako Htmlable objekt, dle konvence repa. |
| Kdo klávesu vlastní? | **`wireRecordSelection`**, ne `wireRecordActions`. Dnešní `onKeydown` visí na `<tbody>` record actions, takže na tabulce bez record actions by `?` nefungovalo. Selection root existuje vždy, když je `selectable()`. |
| Odkud obsah? | **Generovaný z PHP** (`getSelectionConfig()` + zkratky record actions), ne natvrdo napsaný seznam. Jinak nebude obsahovat `onKey()` akce, které si definuje aplikace, a rozejde se s realitou při první změně. |
| Tabulka bez výběru i bez record actions | žádné zkratky → **žádná nápověda**, klávesa se neváže |

---

## 6. Dopad na implementaci

| Soubor | Co |
|---|---|
| `packages/table/resources/js/selection.js` (nový) | `wireRecordSelection` — jediný vlastník výběru a všech gest (dnes ~55 řádků inline `x-data` v Blade, což porušuje Rendering pravidlo 1 a 4) |
| `packages/table/resources/js/record-actions.js` | navigace a akce zůstávají, výběr deleguje; nové klávesy |
| `views/tables/partials/selection-assets.blade.php` (nový) | `@assets` bundle, zrcadlí `record-actions-assets` |
| `views/tables/index.blade.php` | `x-data="wireRecordSelection({…})"`, `data-select-cell`, sweep listenery na rootu, ARIA atributy, `aria-live` region |
| `packages/table/src/Table.php` | `getSelectionConfig()` (PHP vlastní sémantiku, JS ji konzumuje) + fluent vypínače |
| `packages/table/src/Support/ShortcutLegend.php` (nový) | složí seznam zkratek pro nápovědu z `getSelectionConfig()` + zkratek record actions — jeden zdroj pravdy pro `?` modal i pro docs |
| `views/tables/partials/shortcut-help.blade.php` (nový) | obsah `?` modalu, konzumuje `ShortcutLegend` |
| `package.json` | `build:table-assets` bundluje oba moduly |
| `architecture/decisions/0024-table-selection-gestures.md` | ADR: `mod` a nikdy doslovný `Ctrl`, pravidlo `base ∪ rozsah` zapsané jako sjednocení do `selected` (a tím `all` mód zdarma), kotva jednorázová a bez vizuálu, rozsah gest = stránka, sweep jen aditivní a jen v checkboxovém sloupci |

### Etapy (jde zastavit po každé)

1. **Extrakce** `wireRecordSelection` beze změny chování, API 1:1 (konzumují ho i mobilní karty a bulk bar).
2. **Kotva a rozsahy** — `base ∪ rozsah`, `Shift`/`mod`/`mod`+`Shift` klik. Součástí je oprava `selectRange()` (`record-actions.js:261`) a `selectPage()` (`:270`), které dnes natvrdo nastavují `mode = 'keys'` a tím v `all` módu shodí výběr z celé filtrované sady na stránku.
3. **Klávesnice** — `Shift`+šipky na `base ∪ rozsah`, `mod`+`Shift`+šipky, `Home`/`End` + `Shift` varianta, `PageUp`/`PageDown` + `Shift` varianta, `Backspace`, `Shift`+`F10`.
4. **Sweep** přes checkboxový sloupec.
5. **Nápověda `?`** — `ShortcutLegend`, modal, klávesa na selection rootu.
6. **Přístupnost** — ARIA, `aria-live`, nebarevné označení, kontrast, klikatelná plocha checkboxu.
7. **Docs** EN+CZ + boost guidelines + `boost:sync-docs`.

### Rizika

- `index.blade.php` je hot file; selection komponentu konzumují **tři** místa (desktop řádky, mobilní karty, bulk bar) → extrakce musí být API-kompatibilní.
- Kdo má publikovaný `wire-table::tables.index`, nová gesta nedostane, dokud view nepřepublikuje → do docs i CHANGELOGu.
- `wire-sortable` přeřazuje řádky SortableJS instancí na `<tbody>`; má `handle: '.wire-sortable-handle'`, takže sweep z checkboxové buňky nekoliduje — **ověřit CDP testem nad sortable preview**.
- `$wire.entangle` musí přežít přesun do modulu (deferred commit, `queueCommit`).
- Sweep koliduje s označováním textu a s `click` po tažení → `preventDefault` + zahození následného kliku.

### Verifikace

- Pest: markup (komponenta, `data-select-cell`, ARIA), žádná regrese stávajících selection testů.
- CDP (`workbench/scripts/verify-selection-gestures.mjs`): `Shift`/`mod`/`mod`+`Shift` klik, sweep, 1–4 + 8–15 klávesnicí, zmenšení bloku po `mod`+`A`, `Home`/`End`/`PageUp`/`PageDown`, mobilní karty, bulk bar, sortable koexistence.
- `composer test:table` → integrační sada → `composer analyse` → `composer lint` → coverage gate.
