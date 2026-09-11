---
title: Instalace
order: 20
summary: Co balíček sortable potřebuje, co přidá tabulce a jaký asset položí na stránku.
---

# Instalace

## Požadavky

| Závislost | Verze |
|---|---|
| PHP | ^8.2 |
| Laravel | 12.61+ nebo 13.12+ |
| Livewire | ^4.0 |
| wire-core | ^2.0 |
| wire-table | ^2.0 |
| Tailwind CSS | ^3.0 / ^4.0 |

## Instalace přes Composer

```bash
composer require nyoncode/wire-sortable
```

Balíček automaticky registruje svůj service provider přes Laravel package discovery.

## Install příkaz

Spusťte install příkaz pro publikování configu a migrace v jednom kroku:

```bash
php artisan wire-sortable:install
```

To provede:

1. Publikuje config soubor do `config/wire-sortable.php`
2. Publikuje migraci pro tabulku `reorderable_column_orders`

## Spuštění migrací

```bash
php artisan migrate
```

To vytvoří tabulku `reorderable_column_orders` použitou pro ukládání uživatelských preferencí pořadí sloupců. Tabulka má následující strukturu:

| Sloupec | Typ | Popis |
|---|---|---|
| `id` | bigint | Primární klíč |
| `user_id` | bigint / uuid / ulid | Indexovaný klíč uživatele. Typ následuje `wire-sortable.user_key_type` (`id` ve výchozím stavu; nastavte `uuid`/`ulid` pro neceločíselné auth klíče) |
| `model_type` | string | Plně kvalifikovaný název třídy Eloquent modelu |
| `table_identifier` | string | Název třídy Livewire komponenty (rozlišuje více tabulek nad stejným modelem) |
| `column_order` | json | Pole názvů sloupců v uživatelem preferovaném pořadí |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

Unikátní omezení na `(user_id, model_type, table_identifier)` zajišťuje jedno pořadí sloupců pro každou kombinaci uživatele, modelu a komponenty tabulky.

## SortableJS

Nic není potřeba. [SortableJS](https://sortablejs.github.io/Sortable/) je zkompilovaný
přímo do bundlu balíčku (`dist/wire-sortable.js`), který se servíruje z asset routy
balíčku. Žádný npm install, žádný `vendor:publish`, žádný CDN request — řazení tedy
funguje offline i pod přísnou Content Security Policy.

### Doručení JavaScriptu

Jako u každého wireStack balíčku se sortable bundle doručuje dvěma cestami a stačí
kterákoli z nich:

- pohled sortable tabulky ho vypíše sám, když se vykreslí, a
- `@wireStackScripts` v `<head>` vašeho layoutu ho vypíše na každé stránce.

Direktivu přidejte, pokud vaše aplikace naviguje přes `wire:navigate` — proč je právě
umístění v layoutu to, které přežije cestu cachovaného Zpět/Vpřed, vysvětluje
[Začínáme → JavaScriptové assety](../start/getting-started.md#javascriptove-assety).

### `sortablejs_cdn`

`config('wire-sortable.sortablejs_cdn')` je ve výchozím stavu `null` a na řazení už
nemá vliv: drag controller pracuje se zabundlovaným importem a `window.Sortable`
nikdy nečte.

Nastavte ho jen tehdy, když **váš vlastní** kód potřebuje globální `window.Sortable`;
CDN skript se pak načte *navíc* k bundlu:

```php
// config/wire-sortable.php
'sortablejs_cdn' => 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js',
```

> **Upgrade:** dřívější verze ten CDN skript načítaly ve výchozím stavu, takže aplikace
> mohla sáhnout po globálu `window.Sortable`, který po sobě nechal. S výchozí hodnotou
> `null` tenhle globál zmizí, pokud si o něj neřeknete. Pokud vlastní JavaScript vaší
> aplikace `window.Sortable` používá, buď nastavte klíč výše, nebo si SortableJS
> zabundlujte sami:
>
> ```bash
> npm install sortablejs
> ```
>
> ```js
> // resources/js/app.js
> import Sortable from 'sortablejs';
> window.Sortable = Sortable;
> ```
>
> Vlastní řazení Wire to neovlivní ani v jednom případě.

## Manuální publikování

Pokud dáváte přednost publikování assetů jednotlivě:

```bash
# Jen config
php artisan vendor:publish --tag=wire-sortable::config

# Jen migrace
php artisan vendor:publish --tag=wire-sortable::migrations

# Pohledy (pro přizpůsobení)
php artisan vendor:publish --tag=wire-sortable::views

# Překlady
php artisan vendor:publish --tag=wire-sortable::translations
```

## Tailwind CSS

Přidejte pohledy balíčku do svých `content` cest, aby Tailwind mohl skenovat třídy:

**Tailwind v3** (`tailwind.config.js`):

```js
module.exports = {
    content: [
        // ...
        './vendor/nyoncode/wire-sortable/resources/views/**/*.blade.php',
    ],
};
```

**Tailwind v4** (`resources/css/app.css`):

```css
@source '../../vendor/nyoncode/wire-sortable/resources/views';
```

## Databázová migrace pro řazení řádků

Pokud plánujete používat řazení řádků, přidejte sort sloupec do tabulky svého modelu:

```bash
php artisan make:migration add_sort_order_to_tasks_table
```

```php
Schema::table('tasks', function (Blueprint $table) {
    $table->unsignedInteger('sort_order')->default(0)->after('id');
});
```

Název sloupce musí odpovídat hodnotě předané do `reorderable()` (výchozí `sort_order`).

> **Tip:** Můžete použít jakýkoli název sloupce. Jen ho předejte do `reorderable('position')` a ujistěte se, že migrace odpovídá.
