---
title: Icon & Color enum migration audit
date: 2026-05-27
scope: packages/core, packages/forms, packages/table
status: audit (no code changes)
---

# Audit: stringly-typed Icon / Color v public API

**Datum:** 2026-05-27
**Scope:** `packages/core`, `packages/forms`, `packages/table` (bez `tests/`, `docs/`, `build/`)
**Stav enumů:** `NyonCode\WireCore\Foundation\Icons\Icon` a `NyonCode\WireCore\Foundation\Colors\Color` plně existují, oba mají `resolve()`. Enum-aware už je jen velmi úzká část API.

---

## A. Veřejné API – Core (`packages/core`)

### A1. `Foundation/Contracts/HasIcon.php` (interface)
- **L11:** `public function icon(string|Closure|null $icon, ?string $position = null): static;`
- **L13:** `public function getIcon(): ?string;`
- **Proč string:** contract definuje signaturu pro celý ekosystém – nikde nezmiňuje `Icon`.
- **Public API.** Toto je kořen, dokud se to nepředělá, ostatní implementace mají vázané ruce.
- **Cíl:** `icon(string|Icon|Closure|null $icon, ?string $position = null): static` (getter `?string` může zatím zůstat – interní render očekává string).
- **BC:** přidání `Icon` do unionu je čisté rozšíření, žádné rozbití.

### A2. `Foundation/Concerns/HasIcon.php` (trait)
- **L14:** `protected string|Closure|null $icon = null;`
- **L18:** `public function icon(string|Closure|null $icon, ?string $position = null): static`
- **L29:** `public function getIcon(): ?string` (volá `evaluate()`, takže Closure → string)
- **Proč string:** trait nezná `Icon` enum vůbec.
- **Public API** (využíván napříč Forms i Core).
- **Cíl:** přijímat `string|Icon|Closure|null`, ihned v setteru normalizovat přes `Icon::resolve()`, ukládat `?string` (nebo property typu `string|Icon|Closure|null` a normalizovat v getteru).
- **BC:** kompatibilní rozšíření unionu.

### A3. `Foundation/Concerns/HasPrefixAndSuffix.php`
- **L18-20:** `protected string|Closure|null $prefixIcon = null; protected string|Closure|null $suffixIcon = null;`
- **L36:** `public function prefixIcon(string|Closure|null $icon): static`
- **L43:** `public function suffixIcon(string|Closure|null $icon): static`
- **L60/65:** gettery vrací `?string`
- **Public API** (TextInput, Select, …, formy).
- **Cíl:** `prefixIcon(string|Icon|Closure|null $icon)`, totéž suffix.
- **BC:** kompatibilní.

### A4. `Foundation/Concerns/HasHint.php`
- **L16-18:** `protected string|Closure|null $hintIcon = null; protected string|Closure|null $hintColor = null;`
- **L27:** `hintIcon(string|Closure|null $icon)`, **L34:** `hintColor(string|Closure|null $color)`
- **L46/51:** gettery `?string`.
- **Public API.**
- **Cíl:** `hintIcon(string|Icon|Closure|null)`, `hintColor(string|Color|Closure|null)`.
- **BC:** kompatibilní.

### A5. `Actions/Concerns/HasDynamicProperties.php` (sdílený trait BaseAction + sloupců)
- **L75:** `public function color(string|Closure|null $color): static`
- **L90:** `public function getColor(mixed $context = null): string` (default `'primary'`)
- **L131:** `public function icon(string|Closure|null $icon, ?string $position = 'before'): static`
- **L147:** `public function getIcon(mixed $context = null): ?string`
- **Proč string:** trait používaný v `BaseAction` (a všechny actions) – stěžejní místo pro buttony/akce.
- **Public API – kritické.**
- **Cíl:** `color(string|Color|Closure|null)`, `icon(string|Icon|Closure|null)`. Vnitřně normalizovat hned v setteru – uchovat raw value, pro `evaluate()` výsledek převést přes `Color::resolve()`/`Icon::resolve()`.
- **BC:** kompatibilní rozšíření.

### A6. `Actions/Concerns/HasModal.php`
- **L34/36:** `protected ?string $modalIcon = null; protected ?string $modalIconColor = null;`
- **L111:** `public function modalIcon(?string $icon, ?string $color = null): static`
- **L287/292:** gettery `?string` / `string` (`'warning'` default)
- **Public API** (modal pro každou akci).
- **Cíl:** `modalIcon(string|Icon|null $icon, string|Color|null $color = null)`.
- **BC:** kompatibilní.

### A7. `Actions/Concerns/HasButtonStyles.php`
- **L15:** `protected ?string $color = 'primary';`
- **L21:** `public function color(?string $color): static`
- **L42:** `public function getColor(): string`
- **Public API** (alternativní trait, používá ho `HeaderAction`/`ModalFooterAction`).
- **Cíl:** `color(string|Color|null)`.
- **Poznámka:** v projektu existují **tři** `HasColor` lokace (`Foundation/Concerns/HasColor.php`, `Actions/Concerns/HasColor.php`, `Concerns/HasColor.php`) – při refaktoru zkontrolovat duplicity, jinak `Color::resolve()` může produkovat nekonzistentní defaulty.

### A8. `Actions/ActionGroup.php`
- **L43:** `public ?string $icon = 'dots-vertical';` (a navíc `public` property!)
- **L45/60:** `protected ?string $color = 'gray'; protected ?string $badgeColor = null;`
- **L87:** `public function icon(?string $icon): static`
- **L94:** `public function color(?string $color): static`
- **L154:** `public function badgeColor(?string $color): static`
- **L168/173/222:** gettery `?string` / `string`.
- **Public API.** Note: `$icon` jako `public` mutable property je leak – ideálně sjednotit přes setter.
- **Cíl:** `icon(string|Icon|null)`, `color(string|Color|null)`, `badgeColor(string|Color|null)`. Default `'dots-vertical'` má smysl nahradit `Icon::dotsVertical->value()` (lazy initialize v konstruktoru).
- **BC:** rozšíření unionů kompatibilní.

### A9. `Actions/HeaderAction.php`
- **L26:** `protected ?string $badgeColor = null;`
- **L46:** `public function badgeColor(?string $color): static`
- **L75:** `public function getBadgeColor(): string`
- **Public API.**
- **Cíl:** `badgeColor(string|Color|null)`.

### A10. `Actions/ModalFooterAction.php`
- **L29/31:** `protected ?string $icon = null; protected ?string $color = 'gray';`
- **L61:** `public function icon(?string $icon): static`
- **L68:** `public function color(?string $color): static`
- **L121/126:** gettery `?string` / `string`.
- **Public API.**
- **Cíl:** `icon(string|Icon|null)`, `color(string|Color|null)`.

### A11. `Actions/ModalStep.php`
- **L34:** `protected ?string $icon = null;`
- **L71:** `public function icon(?string $icon): static`
- **L149:** `public function getIcon(): ?string`
- **Public API** (wizard step).
- **Cíl:** `icon(string|Icon|null)`.

### A12. `Actions/ActionHalt.php` (sdílí `HasModal`, ale i vlastní settery)
- **L60/62/70:** `protected ?string $modalIcon, $modalIconColor, $color`
- **L190:** `public function icon(?string $icon, ?string $color = null): static` (mapuje na modalIcon/modalIconColor)
- **L219:** `public function color(?string $color): static`
- **L243:** `public function modalIcon(?string $icon, ?string $color = null): static` (deprecated alias)
- **L388/393/413:** gettery `?string`
- **Public API.**
- **Cíl:** `icon(string|Icon|null, string|Color|null)`, `color(string|Color|null)`, deprecated `modalIcon(...)` stejně.

### A13. `Modals/Modal.php`, `SlideOver.php`, `Wizard.php`, `ConfirmationDialog.php`
Identický pattern, jen kopie napříč soubory:
- `Modal.php` L32-36 (props), **L56** `icon(?string $icon, ?string $color = null)`, **L64** `color(?string $color)`, L85/90/95 gettery.
- `SlideOver.php` L32-36, **L56**, **L64**, L91/96/101.
- `Wizard.php` L37-41, **L62** `icon(?string $icon, ?string $color = null)`, **L70** `color(?string $color)`, L99/104/109.
- `ConfirmationDialog.php` L36-40, **L128** `icon(?string $icon, ?string $color = null)`, **L138** `color(?string $color)`, L167/172/177.
- **Public API – 4× identická signatura.** Default barvy `'warning'` (ConfirmationDialog L38) klidně `Color::Warning->value`.
- **Cíl:** všechny 4 → `icon(string|Icon|null $icon, string|Color|null $color = null)`, `color(string|Color|null $color)`.
- **BC:** kompatibilní.

### A14. `Modals/View/ModalComponent.php`, `ConfirmationComponent.php`, `Foundation/View/Button.php`, `Foundation/View/Badge.php`
- `Button.php` L19-20: `public ?string $icon = null, public ?string $iconPosition = 'before'`
- `Badge.php` L22: `public ?string $icon = null` a L28 `getColor(): string`
- `ModalComponent.php` L31: `public ?string $icon = null`
- `ConfirmationComponent.php` L34/38: `public ?string $icon, public ?string $color`
- **Interní detail** – view components, prijímají už **resolved** string z config layeru. **Zde string ponechat.**

### A15. `Widgets/Stat.php`
- **L24/26/31:** props `?string`
- **L56:** `descriptionIcon(?string $icon)`, **L68:** `color(?string $color)`, **L103:** `icon(?string $icon)`
- **L63/75/110:** gettery `?string`
- **Public API.**
- **Cíl:** všechny tři settery přidat `Icon`/`Color` do unionu.

### A16. `Notifications/Notification.php`
- **L26:** `public readonly ?string $icon = null,`
- **L76:** `public function icon(?string $icon): self`
- **Public API** (immutable VO). Také obsahuje `'success'/'error'/'warning'/'info'` jako stringly-typed `type` – mimo scope tohoto auditu, ale stojí za úvahu (samostatný enum `NotificationType`).
- **Cíl:** `icon(string|Icon|null $icon): self`, vnitřně normalize do stringu (kvůli immutable readonly), property může zůstat `?string`.
- **BC:** kompatibilní.

---

## B. Veřejné API – Forms (`packages/forms`)

### B1. `Components/Layout/Section.php`
- **L17:** `protected ?string $icon = null;`
- **L36:** `public function icon(?string $icon): static`
- **L87:** `public function getIcon(): ?string`
- **Public API.**
- **Cíl:** `icon(string|Icon|null)`.

### B2. `Components/Display/Alert.php`
- **L17/19:** `protected string $color = 'info'; protected ?string $icon = null;`
- **L44:** `public function color(string $color): static`
- **L71:** `public function icon(?string $icon): static`
- **L95/100:** gettery `string` / `?string`.
- **Public API.**
- **Cíl:** `color(string|Color)`, `icon(string|Icon|null)`. Defaultní `'info'` přes `Color::Info->value`.
- **Poznámka:** shortcut metody `info()/success()/warning()/danger()` (L51-69) jsou OK, ale teď posílají hard-coded stringy – uvnitř volat `Color::Info`.

### B3. `Components/Toggle.php`
- **L22/24:** `protected ?string $onIcon = null; protected ?string $offIcon = null;`
- **L42/49:** `onColor(string $color)`, `offColor(string $color)` (defaulty `'primary'`/`'gray'` jako property literály v souboru? – zkontrolovat init values)
- **L56/63:** `onIcon(?string $icon)`, `offIcon(?string $icon)`
- **L87-102:** gettery
- **Public API.**
- **Cíl:** `onColor(string|Color)`, `offColor(string|Color)`, `onIcon(string|Icon|null)`, `offIcon(string|Icon|null)`.

### B4. `Forms/Form.php` – zkontrolovat na ikonu?
Neobjevuje se v greppu – Form sám icon/color setter nemá, dědí přes Field. **OK.**

---

## C. Veřejné API – Table (`packages/table`)

### C1. ✅ `Columns/Column.php` – **už enum-aware**
- **L1330:** `public function color(string|Color|null $color): static` (normalizuje na string property)
- **L1342:** `public function icon(string|Icon|null $icon, ?string $position = 'before'): static`
- **L1337/1350:** gettery `?string` (string after normalize).
- **Toto je referenční vzor pro zbytek refaktoru.**

### C2. `Columns/Column.php` – `textColor`
- **L215:** `protected ?string $textColor = null;`
- **L1509:** `public function textColor(string $color): static`
- **L1516:** `public function getTextColor(): ?string`
- **Public API.** Stejný styl jako ostatní, ale enum chybí.
- **Cíl:** `textColor(string|Color $color)` (umožnit i `null`?  – v jiných setterech je nullable).
- **BC:** kompatibilní.

### C3. ✅ `Columns/IconColumn.php` – **enum-aware**, L101 trueIcon, L108 falseIcon, L115/122 colors, L40 icons array, L60 colors array. **OK.**

### C4. ✅ `Columns/BooleanColumn.php` – **enum-aware** (L26/33/40/47). **OK.**

### C5. ✅ `Columns/BadgeColumn.php` – **enum-aware** (L30/50 arrays, L40/60 callbacks vrací enum nebo string). **OK.**

### C6. `Columns/ToggleColumn.php`
- **L12/14:** `protected ?string $onColor = 'primary'; protected ?string $offColor = 'gray';`
- **L16/18:** `protected ?string $onIcon, $offIcon = null;`
- **L31/38/45/52:** všechny 4 settery jen `?string`.
- **Public API.** Toto je inkonzistence – `BooleanColumn` ano, `ToggleColumn` ne.
- **Cíl:** `onColor(string|Color|null)`, `offColor(string|Color|null)`, `onIcon(string|Icon|null)`, `offIcon(string|Icon|null)`.

### C7. `Columns/ButtonColumn.php`
- **L86:** `protected ?string $buttonIconPosition = 'before';`
- **L111:** `public function buttonIcon(string|Closure $icon, ?string $position = 'before'): static`
- **L252:** `public function buttonColor(string|Closure $color): static`
- **L244:** `danger()` shortcut → `buttonColor('danger')` hardcoded.
- **Public API.**
- **Cíl:** `buttonIcon(string|Icon|Closure $icon)`, `buttonColor(string|Color|Closure $color)`. V `danger()` použít `Color::Danger`.

### C8. `Columns/PollColumn.php`
- **L608:** `protected function renderStateIcon(Model $record, ?string $state): string`
- **L630:** `protected function getStateIcon(Model $record, ?string $state): ?string`
- **L640:** `protected function getStateColor(Model $record, ?string $state): ?string`
- **Interní helper** (`protected`), dostává **state**, ne ikonu/barvu na vstupu – stringy jsou v pořádku.
- **Pokud má veřejné settery (icons/colors arrays/callbacks)** – grep nenašel; pokud jsou někde, doplnit do auditu (soubor je velký).

### C9. `Table.php`
- **L89:** `protected ?string $emptyStateIcon = null;`
- **L850:** `public function getEmptyStateIcon(): ?string`
- **Public API** – chybí pohled na setter (`emptyStateIcon(...)`), je v jiné metodě. **Doporučuji setter sjednotit:** `emptyStateIcon(string|Icon|null)`.

### C10. Filtry (`packages/table/src/Filters/`)
Grep neukázal žádné icon/color settery v `Filters/` – pokud filter komponenty v Blade používají badge barvy, jsou děděné přes `Column`/`HasColor` skrz view. **Pravděpodobně OK, není to API surface filtrů.**

---

## D. Shrnutí hlavních oblastí, kde enum migrace neproběhla

1. **Foundation contracts/traits** (`Contracts/HasIcon`, `Concerns/HasIcon`, `HasPrefixAndSuffix`, `HasHint`) – kořenové API, propaguje string downstream.
2. **Actions stack** (`HasDynamicProperties`, `HasModal`, `HasButtonStyles`, `BaseAction`, `ActionGroup`, `HeaderAction`, `ModalFooterAction`, `ModalStep`, `ActionHalt`) – kompletně string-based, přitom právě tady `color`/`icon` přechází přes čtyři vrstvy.
3. **Modaly** (`Modal`, `SlideOver`, `Wizard`, `ConfirmationDialog`) – 4× duplicitní signatura.
4. **Forms display komponenty** (`Section`, `Alert`, `Toggle`).
5. **Widgets** (`Stat`).
6. **Notifications** (`Notification` – immutable VO).
7. **Table inkonzistence** (`ToggleColumn`, `ButtonColumn`, `Column::textColor`, `Table::emptyStateIcon`) – zbytek tabulky už enum-aware je, je to viditelná nesymetrie.

---

## E. Doporučené pořadí refaktoru (od nejnižšího k nejvyššímu riziku)

1. **`Notifications/Notification.php`** – izolované, immutable, nikoho jiného neovlivní. *Risk: trivial.*
2. **`Widgets/Stat.php`** – samostatný widget, žádné dědictví. *Risk: trivial.*
3. **`Forms/Components/Display/Alert.php`, `Components/Layout/Section.php`, `Components/Toggle.php`** – display-only formové komponenty. *Risk: low.*
4. **Table dorovnání: `ToggleColumn`, `ButtonColumn`, `Column::textColor`, `Table::emptyStateIcon`** – stejný vzor jako už hotový `Column`. *Risk: low.*
5. **Modaly (`Modal`, `SlideOver`, `Wizard`, `ConfirmationDialog`)** – mechanická 4× změna jedné dvojice signatur, žádný překryv s actions. *Risk: low-medium.*
6. **`Foundation/Concerns/HasPrefixAndSuffix`, `HasHint`, `Concerns/HasIcon` (trait)** – propagují se do mnoha Form fieldů. Po nich aktualizovat **`Foundation/Contracts/HasIcon`** (interface). *Risk: medium* (interface change → kontrolovat implementace).
7. **Actions: `HasDynamicProperties`, `HasModal`, `HasButtonStyles`, `BaseAction`, `ActionGroup`, `HeaderAction`, `ModalFooterAction`, `ModalStep`, `ActionHalt`** – core API, hodně call-sites, deprecation aliasy. *Risk: medium-high.* Doporučuji rozdělit do dvou PR: nejprve trait `HasDynamicProperties` (a `HasModal`), pak akce.
8. **Konsolidace tří `HasColor` traitů** (`Foundation/Concerns/HasColor`, `Actions/Concerns/HasColor`, `Concerns/HasColor`) – nesouvisí přímo s enum migrací, ale stejná duplikace stringových klíčů. Až bude všechno enum-aware, zvážit centralizaci přes `Color::resolve()`. *Risk: high (cross-cutting).*

---

## F. Místa, která už enumy konzumují správně (no action)

- `packages/table/src/Columns/Column.php:1330,1342` – `color(string|Color|null)`, `icon(string|Icon|null)` ✅
- `packages/table/src/Columns/BooleanColumn.php:26,33,40,47` – `trueIcon/falseIcon/trueColor/falseColor` ✅
- `packages/table/src/Columns/IconColumn.php:40,60,84,93,101,108,115,122` – kompletní pokrytí (arrays, booleans, true/false settery) ✅
- `packages/table/src/Columns/BadgeColumn.php:30,50,21,23,116,127` – arrays + callbacky vrací enum nebo string ✅
- `Foundation/Icons/Icon.php`, `Foundation/Colors/Color.php` – enumy včetně `resolve()` ✅

---

## G. Doporučený jednotný recept pro refaktor (BC-safe)

Pro každý setter typu `xxx(string|... $value)`:

```php
// předtím
public function color(?string $color): static { $this->color = $color; return $this; }

// potom
public function color(string|Color|null $color): static
{
    $this->color = $color instanceof Color ? $color->value : $color;
    return $this;
}
```

Pro Closure variantu uložit raw a normalizovat v getteru (Closure resolve → string → `Color::resolve()->value`). Property type a return type getteru lze beze změny nechat na `?string`, dokud nepadne rozhodnutí migrovat i storage layer (tj. property typu `?Color`) – to už by ale **nebylo BC-safe** pro kód, který property čte přes reflection nebo přímý přístup, takže ne v této fázi.
