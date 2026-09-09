---
order: 5
summary: "Co žije ve wire-core, jak jsou jeho moduly navrstvené a která stránka odpovídá na kterou otázku."
---

# Wire Core

`wire-core` je to, z čeho je postavené všechno ostatní. Neveze žádnou tabulku ani
formulář: veze slovník, kterým obojí mluví — akce, modály, notifikace, ikony,
barvy, schéma — plus povrchy, které vlastní seznam ani formulář nepotřebují.

```bash
composer require nyoncode/wire-core
```

Každý další balíček ho vyžaduje a on nevyžaduje žádný z nich. Proto sem patří
concern, který je opravdu sdílený — a ne do balíčku, který ho náhodou potřeboval
první.

## Jak to funguje

Moduly jsou **navrstvené** a to vrstvení hlídá test, ne code review:

| Vrstva | Co v ní je | Smí vidět |
| --- | --- | --- |
| **L0** | `Foundation/`, `Exceptions/` — traity, kontrakty, enumy, hodnotové objekty | nic nad sebou |
| **L1** | `Core/` — bezhlavý engine: pluginy, identita resourců, registry | L0 |
| **L2** | `Actions/`, `Modals/`, `Notifications/`, `Widgets/`, `Infolists/`, `Panels/`, `Audit/` | L0 a L1 — nikdy sebe navzájem |

Dva povrchy z L2, které si potřebují říct, to udělají přes kontrakt ve
`Foundation/Contracts/`, ne vzájemným importem. Právě díky tomu aplikace, která
používá infolisty a žádné notifikace, nenačte notifikační manager — a právě proto
by se kterýkoli z nich dal vyčlenit do vlastního balíčku bez přepisování.

Praktický důsledek pro čtenáře: **schopnost, která se objevuje na dvou místech, tu
má jednoho vlastníka.** Barva je `HasColor`, ikona `HasIcon`, velikost `HasSize` —
a badge sloupec, badge entry i notifikace si tu svou řeší stejným kódem, takže se
ten slovník vyplatí naučit jednou.

## V této sekci

| Stránka | Na co odpovídá |
| --- | --- |
| [Foundation](foundation/index.md) | Sdílené traity, základní třídy, ikony, barvy, enumy a Blade komponenty |
| [Akce](actions/index.md) | Co se stane po kliknutí — řádkové, hromadné a hlavičkové akce, skupiny, potvrzení, fronty |
| [Modály](modals.md) | Dialogy, které akce otevírá: potvrzení, slide-over, vícekrokový wizard |
| [Notifikace](notifications/index.md) | Toasty a uložené notifikace, jejich drivery a kam který doručuje |
| [Widgety](widgets/index.md) | Statistiky, grafy, vnořené tabulky, vlastní pohledy a dashboardy, které je drží |
| [Infolisty](infolists/index.md) | Jeden záznam vykreslený read-only ze schématu |
| [Editovatelné panely](record-panels.md) | Stejný tvar, ale s entries, které zapisují rovnou do záznamu |
| [Schéma](schema/overview.md) | Layoutový slovník, který konzumují formuláře, infolisty i modály |
| [Globální vyhledávání](global-search.md) | ⌘K palette nad vším zaregistrovaným |
| [Auditní log](audit.md) | Stopa změn modelů, zapisovaná za tebe |
| [Pluginy](plugins/index.md) | Rozšiřovací bod pro aplikace i doprovodné balíčky |

## Kam dál

Core je málokdy celá odpověď. Co je nad ním:

- [Formuláře](../forms/overview.md) a [Tabulka](../table/overview.md) — dva povrchy
- [Panely](../panels/overview.md) — jedna entita deklarovaná jednou, se stránkami a routami
- [Admin shell](../admin/overview.md) — volitelný rám kolem toho všeho

## Související

- [Konfigurace](../start/configuration.md) — celý `config/wire-core.php`
- [Motivy a přizpůsobení](../start/theming.md) — barevný a ikonový slovník a jak ho rozšířit
- [Autorizace](../start/authorization.md) — gate, na kterou se ptá každý zdejší povrch
