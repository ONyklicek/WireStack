---
order: 20
summary: "Kde se název ikony přeloží, jak přidat vlastní sadu a co se stane, když si dvě sady nárokují stejný název."
---

# Ikony

Každá ikona ve frameworku je **název**, ne markup: `'outline:user'` projde
sloupcem, akcí i notifikací beze změny a na SVG se převede jednou, při
vykreslení. Právě tahle nepřímost dovoluje aplikaci vyměnit celou sadu, aniž by
sáhla na jedinou komponentu.

## Ikony

Kompletní kolekce [Heroicons](https://heroicons.com) **solid** (324 ikon,
`20x20` viewBox) je přibalena inline — bez externích závislostí, bez extra balíčku.
Je to **výchozí sada**, adresovaná holými názvy (`pencil`, `user`). Můžete
zaregistrovat libovolný počet dalších sad (Lucide, Feather, vlastní brand ikony)
vedle ní — viz [Použití více sad ikon](#pouziti-vice-sad-ikon).

Každá ikona nese svůj vlastní `viewBox` a fill/stroke stylování, takže 20×20 fill-based
Heroicons a 24×24 stroke-based sady se vykreslí správně vedle sebe.

### Použití v Blade

```blade
<x-wire::icon name="check" class="w-5 h-5" />
<x-wire::icon name="trash" class="w-4 h-4 text-red-500" />

{{-- Prefixovaná ikona z jiné registrované sady --}}
<x-wire::icon name="lucide:home" class="w-5 h-5" />

{{-- Vystavit asistivní technologii (jinak je ikona aria-hidden) --}}
<x-wire::icon name="trash" label="Delete" />
```

### Použití v PHP

```php
use NyonCode\WireCore\Foundation\Icons\IconManager;

$manager = app(IconManager::class);

$manager->render('check');                 // plný <svg> řetězec
$manager->render('trash', 'w-5 h-5', 'text-red-500', label: 'Delete');
$manager->has('lucide:home');              // bool
$manager->resolve('check');                // ?ResolvedIcon (body + viewBox + attrs)
$manager->allNames();                      // každý dostupný název (prefixovaný pro ne-výchozí sady)
```

`render()` je kanonický vstupní bod — aplikuje vlastní `viewBox` a stylování každé
ikony. `getPath()` vrací jen vnitřní markup a je zachován jen pro volající,
kteří obalují svůj vlastní `<svg>` (správné jen pro `0 0 20 20` fill ikony).

### Dostupné ikony

Každá výchozí ikona používá svůj **kanonický Heroicons název** — název souboru z
[heroicons.com](https://heroicons.com) (solid varianta). Procházejte celou sadu
tam; pár příkladů:

`academic-cap`, `arrow-down-tray`, `bars-3`, `chevron-up`, `cog-6-tooth`,
`document-text`, `envelope`, `funnel`, `magnifying-glass`, `pencil`, `qr-code`,
`trash`, `user`, `wrench-screwdriver`, `x-mark`.

Pro IDE autocompletion můžete na ikony odkazovat přes enum `Icon` místo
surových řetězců:

```php
use NyonCode\WireCore\Foundation\Icons\Icon;

Action::make('edit')->icon(Icon::pencilSquare);
```

### Wire-friendly aliasy

Malá sada krátkých aliasů mapuje na kanonické ikony pro pohodlí:

| Alias | Resolvuje na | Alias | Resolvuje na |
|-------|-------------|-------|-------------|
| `pen`, `edit` | `pencil` | `delete` | `trash` |
| `view` | `eye` | `add` | `plus` |
| `download`, `export` | `arrow-down-tray` | `upload`, `import` | `arrow-up-tray` |
| `duplicate`, `copy` | `document-duplicate` | `x`, `close` | `x-mark` |
| `settings` | `cog` | `mail`, `email` | `envelope` |
| `exclamation`, `warning` | `exclamation-triangle` | `information`, `info` | `information-circle` |
| `question` | `question-mark-circle` | `archive` | `archive-box` |
| `refresh` | `arrow-path` | `shield` | `shield-check` |
| `lock` | `lock-closed` | `filter` | `funnel` |
| `more`, `dots-vertical` | `ellipsis-vertical` | `dots-horizontal` | `ellipsis-horizontal` |
| `external-link` | `arrow-top-right-on-square` | | |

### Přístupnost

Ikony se ve výchozím stavu vykreslují jako dekorativní (`aria-hidden="true"`). Předejte `label`, když
ikona nese význam sama o sobě — pak je vystavena jako obrázek s tím
labelem (`role="img"` + `aria-label`):

```blade
<x-wire::icon name="check-circle" label="Verified" />
```

## Přidávání vlastních ikon

Nemusíte se spokojit s přibalenou sadou. Vyberte přístup, který vám sedí. Vlastní
ikony (složky a inline) jsou **holo-pojmenované** a mají prioritu před výchozí
sadou, takže se vlastní ikona použije všude, kde je název přijímán
(`->icon('logo')`, `<x-wire::icon name="logo" />`, …).

Když vložíte kompletní `<svg>…</svg>`, jeho `viewBox` a stylovací atributy
(`fill`, `stroke`, `stroke-width`, …) jsou **zachovány** — takže můžete vhodit ikony
z jakéhokoli zdroje a formátu. Holý `<path>` fragment spadne na Heroicons
solid formát (`0 0 20 20`, `fill="currentColor"`).

### 1. Ze složky SVG souborů (nejjednodušší)

Vhoďte `.svg` soubory do adresáře a zaregistrujte cestu — název souboru se stane
názvem ikony (`logo.svg` → `logo`). Žádná třída, žádný boilerplate.

Přes config (`config/wire-core.php`), skvělé pro ikony pro celou aplikaci. Řetězcový klíč přidá
pomlčkou spojený prefix názvu a předchází kolizím názvů souborů mezi složkami:

```php
'icons' => [
    'paths' => [
        resource_path('icons'),                 // resources/icons/logo.svg => "logo"
        'brand' => resource_path('icons/brand'), // icons/brand/mark.svg   => "brand-mark"
    ],
],
```

Nebo za běhu:

```php
use NyonCode\WireCore\Foundation\Icons\IconManager;

app(IconManager::class)->registerIconsFromDirectory(
    resource_path('icons/brand'),
    prefix: 'brand',                   // brand/logo.svg => "brand-logo"
);
```

> `prefix` složky produkuje **plochý název** (`brand-logo`) — není to totéž
> jako `prefix:name` namespace sady popsaný níže.

### 2. Inline, podle názvu

Zaregistrujte jednotlivé ikony — vložte plný `<svg>…</svg>` (wrapper je odstraněn,
jeho viewBox/stylování zachováno) nebo jen vnitřní `<path>`:

```php
app(IconManager::class)->registerIcons([
    'logo'  => '<svg viewBox="0 0 20 20"><path d="M10 2 …"/></svg>',
    'spark' => '<path d="M10 1 12 8 …"/>',
]);
```

Znovupoužijte stejný název jako přibalená ikona pro její **přepis**. Vložte volání do
`boot()` service provideru, aby byly ikony dostupné všude:

```php
public function boot(): void
{
    app(IconManager::class)->registerIconsFromDirectory(resource_path('icons'));
}
```

### 3. Znovupoužitelná sada ikon (pokročilé)

Pro kompletní, vyměnitelný styl implementujte `IconSet`. Implementujte i volitelnou
schopnost `ProvidesIconMetadata`, pokud jsou vaše ikony stroke-based nebo používají
ne-`20x20` viewBox (Lucide, Feather, Heroicons outline) — to nechá každou ikonu
nést svůj vlastní `ResolvedIcon` (body + viewBox + atributy):

```php
use NyonCode\WireCore\Foundation\Icons\{IconSet, ProvidesIconMetadata, ResolvedIcon};

final class LucideIconSet implements IconSet, ProvidesIconMetadata
{
    private string $dir = '/abs/path/to/node_modules/lucide-static/icons';

    public function getIcon(string $name): ?ResolvedIcon
    {
        $file = "{$this->dir}/{$name}.svg";

        // fromSvg() zachová Lucide viewBox="0 0 24 24" + fill=none stroke=currentColor.
        return is_file($file) ? ResolvedIcon::fromSvg(file_get_contents($file)) : null;
    }

    public function getPath(string $name): ?string { return $this->getIcon($name)?->body; }
    public function has(string $name): bool        { return is_file("{$this->dir}/{$name}.svg"); }
    public function names(): array                 { /* basenames of *.svg */ return []; }
}
```

Sady, které implementují jen `IconSet`, stále fungují — jejich `getPath()` výstup se obalí
do výchozího `0 0 20 20` fill formátu.

<a id="using-multiple-icon-sets"></a>

## Použití více sad ikon

Resolvování je **deterministické a namespaced**:

- **Výchozí sada je neprefixovaná** — `pencil`, `user`, `lucide` aliasy, vlastní
  ikony — a je vždy Heroicons, dokud ji nevyměníte (níže).
- **Každá další sada vyžaduje unikátní prefix** a adresuje se jako `prefix:name`.

Zaregistrujte další sady v configu pod jejich prefix klíčem:

```php
// config/wire-core.php
'icons' => [
    'default_set' => 'default',
    'sets' => [
        'default' => DefaultIconSet::class,   // → "pencil"      (Heroicons, 20×20 fill)
        'lucide'  => LucideIconSet::class,    // → "lucide:home" (24×24 stroke)
        'custom'  => App\Wire\Icons\MyIconSet::class,
    ],
],
```

```blade
<x-wire::icon name="pencil" />        {{-- Heroicons --}}
<x-wire::icon name="lucide:home" />   {{-- Lucide --}}
```

To zaručuje, že sady nikdy nekolidují: holý název je vždy výchozí sada, 
prefixovaný název je vždy přesně ta sada. Kvůli tomu **registrace ne-výchozí
sady bez prefixu vyhodí** `InvalidArgumentException`:

```php
app(IconManager::class)->registerIconSet(new LucideIconSet, 'lucide'); // ok
app(IconManager::class)->registerIconSet(new LucideIconSet);           // vyhodí
```

> Oddělovač je dvojtečka (`:`). Názvy ikon samy používají pomlčky
> (`arrow-down-tray`), takže není žádná nejednoznačnost. Použijte `default:name` k adresování
> základní sady explicitně.

### Výměna výchozí (neprefixované) sady

Aby se jiná sada stala neprefixovaným základem — např. dodat Lucide jako váš primární
styl — nasměrujte `default_set` na její klíč:

```php
'icons' => [
    'default_set' => 'lucide',            // holé názvy teď resolvují vůči Lucide
    'sets' => [
        'lucide'  => LucideIconSet::class,
        'default' => DefaultIconSet::class, // stále dostupné jako "default:pencil"
    ],
],
```

Za běhu: `app(IconManager::class)->setDefaultIconSet(new LucideIconSet)`.

### Odchytávání překlepů

Nastavte `icons.warn_missing` (nebo `WIRE_ICONS_WARN_MISSING=true`) pro logování varování,
kdykoli se vykreslí neznámý název ikony — stále vykreslí fallback
placeholder, ale log pomáhá odhalit překlepy ve vývoji.

### Regenerace přibalených Heroicons

Přibalené cesty žijí v generovaném PHP data souboru
`packages/core/resources/icons/heroicons-solid.php`, produkovaném z oficiálního
npm balíčku `heroicons` (`20/solid` SVG, klíčované názvem souboru). Regenerujte ten
soubor místo ruční editace cest ikon.

<a id="colors"></a>

## Související

- [Barvy](colors.md) — stejný tvar resolveru pro druhou polovinu povrchu
- [Enumy](enums.md) — enum, který si pojmenuje vlastní ikonu
- [Motivy a přizpůsobení](../../start/theming.md) — výměna sady, kterou aplikace používá
- [Foundation](index.md) — concerny, kterými se k tomuhle dostaneš
