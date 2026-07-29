---
title: Přizpůsobení
order: 50
---

# Přizpůsobení

## CSS třídy

Veškeré stylování používá CSS třídy, které můžete přepsat ve svém stylesheetu.

### Řazení řádků

| Třída | Aplikováno na | Účel |
|---|---|---|
| `wire-sortable-handle` | Drag handle `<div>` | Kurzor, barva, hover stav |
| `wire-sortable-ghost` | Placeholder řádku během tažení | Průhlednost a pozadí |
| `wire-sortable-chosen` | Vybraný řádek | Zvýraznění pozadí |
| `wire-sortable-drag` | Plovoucí drag klon | Pozadí, stín, radius |
| `wire-sortable-th` | Hlavičková buňka sloupce handle | Identifikuje přidané `<th>` |

### Řazení sloupců

| Třída | Aplikováno na | Účel |
|---|---|---|
| `wire-sortable-column-ghost` | Placeholder hlavičky sloupce | Průhlednost a pozadí |
| `wire-sortable-column-chosen` | Vybraná hlavička | Zvýraznění pozadí |
| `wire-sortable-column-drag` | Plovoucí klon hlavičky | Pozadí, stín, radius |

### Výchozí styly

Balíček obsahuje tyto výchozí styly:

```css
/* Row ghost */
.wire-sortable-ghost {
    opacity: 0.4;
    background-color: rgb(59 130 246 / 0.1);
}

/* Row chosen */
.wire-sortable-chosen {
    background-color: rgb(59 130 246 / 0.05);
}

/* Row drag clone */
.wire-sortable-drag {
    background-color: white;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    border-radius: 0.5rem;
}

/* Column ghost */
.wire-sortable-column-ghost {
    opacity: 0.4;
    background-color: rgb(59 130 246 / 0.1);
}

/* Column drag clone */
.wire-sortable-column-drag {
    background-color: rgb(249 250 251);
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    border-radius: 0.375rem;
}
```

## Dark mode

Dark mode varianty jsou zahrnuty automaticky:

```css
.dark .wire-sortable-drag {
    background-color: rgb(31 41 55);
}

.dark .wire-sortable-column-drag {
    background-color: rgb(31 41 55);
}
```

Ikona drag handle používá `text-gray-400 hover:text-gray-600 dark:hover:text-gray-300` pro správný kontrast v obou motivech.

Toggle tlačítko používá podmíněné Tailwind třídy:

- **Aktivní (reordering):** `bg-primary-100 text-primary-600` (světlý) / `bg-primary-900/30 text-primary-400` (tmavý)
- **Neaktivní:** `text-gray-400 hover:text-gray-600 hover:bg-gray-100` (světlý) / `text-gray-500 hover:text-gray-300 hover:bg-gray-700` (tmavý)

## Přepis stylů

Přidejte vlastní pravidla za styly balíčku pro přepsání výchozích:

```css
.wire-sortable-ghost {
    opacity: 0.6;
    background-color: rgb(16 185 129 / 0.1); /* zelená místo modré */
}

.wire-sortable-drag {
    border: 2px solid rgb(16 185 129);
}
```

## Animace

Upravte rychlost drag animace v `config/wire-sortable.php`:

```php
'animation' => 300, // pomalejší, plynulejší
```

Nastavte na `0` pro vypnutí animace.

## Toggle tlačítko

Toggle tlačítko se vykreslí automaticky v toolbaru tabulky. Ukazuje:

- „Reorder" (s grip ikonou), když není v reorder režimu
- „Done reordering" (s check ikonou), když je v reorder režimu

Tlačítko je skryté, když tabulka používá `alwaysReorderable()` nebo když je řazení řádků vypnuté.

### Překlady

Labely tlačítka jsou přeložitelné. Publikujte překlady:

```bash
php artisan vendor:publish --tag=wire-sortable::translations
```

Upravte `lang/vendor/wire-sortable/{locale}/messages.php`:

```php
return [
    'reorder' => 'Reorder',
    'done_reordering' => 'Done reordering',
];
```

Zahrnuté locale: `en`, `cs`.

## Publikování pohledů

Pro plné přizpůsobení HTML a JavaScriptu:

```bash
php artisan vendor:publish --tag=wire-sortable::views
```

Publikované soubory:

| Soubor | Popis |
|---|---|
| `tables/index.blade.php` | Alpine wrapper, zahrnuje wire-table pohled, toolbar widgety |
| `partials/scripts.blade.php` | Tenký `@assets` wrapper: vypíše `<script>` tag bundlu balíčku (plus volitelný tag `sortablejs_cdn`) a drag CSS `.wire-sortable-*` |

Alpine komponenta `wireSortable` už v partialu nebydlí — je zkompilovaná, spolu se
SortableJS, do `dist/wire-sortable.js`. Publikování pohledů vám tedy umožní přestylovat
drag třídy a markup wrapperu, ale **ne** přepsat chování dragu; publikovaná kopie
pořád načítá bundle balíčku. Chování změníte tak, že zaregistrujete vlastní Alpine
komponentu a nasměrujete na ni `x-data` wrapperu.

Po publikování upravte soubory v `resources/views/vendor/wire-sortable/`.

## Vlastní model uživatele

Pokud vaše aplikace používá vlastní model uživatele, aktualizujte `config/wire-sortable.php`:

```php
'user_model' => 'App\\Models\\Admin',
```

Toto používá model `ReorderableColumnOrder` pro relaci `user()`.
