---
order: 10
summary: "Sdílené traity, které skládá každá komponenta, základní třídy pod nimi a support utility, po kterých sahá obojí."
---

# Foundation

Foundation je trvalé jádro `wire-core`: vrstva, kterou nic nad ní nesmí měnit a
ze které je všechno nad ní postavené. Pole, sloupec, akce a widget jsou různé
objekty, které odpovídají na tytéž otázky — jaký má popisek, ikonu, barvu,
velikost, jestli je vidět — a odpovídají na ně **stejnými traity**. Proto se ten
slovník vyplatí naučit jednou.

## Concerny (traity)

### Konfigurace komponenty

| Trait | Metody | Popis |
|-------|---------|-------------|
| `HasLabel` | `label($label)`, `translateLabel()`, `getLabel()` | Zobrazovací popisek |
| `HasDescription` | `description($text)`, `getDescription()` | Popisný text |
| `HasHelperText` | `helperText($text)`, `getHelperText()` | Helper text pod polem |
| `HasHint` | `hint($text)`, `hintIcon($icon)`, `getHint()` | Hint text/ikona |
| `HasName` | `name($name)`, `getName()` | Identifikátor |
| `HasDefault` | `default($value)`, `getDefault()` | Výchozí hodnota |
| `HasIcon` | `icon($name, $position)`, `getIcon()` | Ikona podle názvu (`pencil` nebo `prefix:name`) |
| `HasColor` | `color($color)`, `getColor()` | Název Tailwind barvy |
| `HasSize` | `size($size)`, `getSize()` | Varianta velikosti (sm/md/lg/xl) |
| `HasColumns` | `columnSpan($span)`, `columnStart($start)` | Layout sloupců gridu |
| `HasExtraAttributes` | `extraAttributes(array $attrs)` | Libovolné HTML atributy |
| `HasSortOrder` | `sort($position)`, `getSort()` | Pozice ve vykreslovaném seznamu — položka menu, skupina menu. Ne řazení dotazu (`Column::sortable()`) |

### Stav a chování

| Trait | Metody | Popis |
|-------|---------|-------------|
| `HasState` | `state($value)`, `getState()`, `live()`, `debounce($ms)` | Livewire vazba stavu |
| `HasVisibility` | `hidden($condition)`, `visible($condition)`, `isHidden()` | Podmíněná viditelnost |
| `HasDisabled` | `disabled($condition)`, `isDisabled()` | Disabled stav |
| `HasValidation` | `required()`, `rules($rules)`, `validationMessages($msgs)` | Validační pravidla |

### Infrastruktura

| Trait | Metody | Popis |
|-------|---------|-------------|
| `HasMake` | `static make(...$args)` | Statická factory |
| `HasEvaluate` | `evaluate($value, $params)` | Vyhodnocení closura-nebo-hodnota s DI |
| `HasSchema` | `schema(array $components)`, `getSchema()` | Pole dětských komponent |
| `HasHtmlAttributes` | `htmlAttributes()`, `getHtmlAttributes()` | Sloučené HTML atributy |
| `EvaluatesClosures` | `evaluate($value, $record, ...)` | Per-záznam resolvování closur |

### Specifické pro akce

| Trait | Metody | Popis |
|-------|---------|-------------|
| `HasDynamicProperties` | `resolve($record)` | Per-záznam resolvování vlastností |
| `HasKeyboardShortcut` | `keyboardShortcut($keys)` | Alpine.js klávesová vazba |
| `HasLifecycle` | `before($fn)`, `after($fn)`, `halt()` | Before/after hooky s halt |
| `HasLoadingState` | `loadingIndicator()`, `debounce($ms)` | UI stav načítání |
| `HasModal` | `requiresConfirmation()`, `modalHeading()`, `slideOver()`, ... | Konfigurace modalu |

> CSS třídy tlačítek/badge pocházejí z kanonických `HasColor` resolverů (viz
> [Kanonické color resolvery](colors.md#kanonicke-color-resolvery-hascolor)), ne z
> mapy pro jednotlivé komponenty. `HasButtonStyles` zůstává jen jako deprecated alias.

### Popisky ze jmen

Komponenta bez `->label()` se jmenuje podle svého jména z `make()` a o tom, jak,
rozhoduje jeden vlastník: `Foundation\Support\NameLabel`. Ptají se ho pole, sekce,
fieldsety, záložky, akce, sloupce, filtry i sloupce importu, takže projekt mění
pravidlo na jednom místě.

**Jméno, které už je text, zůstane textem.** Jméno s mezerou bylo napsané pro lidi,
takže zůstane, jak je, jen první písmeno se zvětší — `Section::make('Pro montážníky')`
čte „Pro montážníky“, ne „Pro Montážníky“. **Identifikátor se polidští podle
nastaveného stylu**, `wire-core.labels.from_name` (`WIRE_LABELS_FROM_NAME`):

| Styl | `plan_period` | `withTransport` |
|---|---|---|
| `headline` (výchozí) | Plan Period | With Transport |
| `sentence` | Plan period | With transport |
| `name` | plan_period | withTransport |

```php
// config/wire-core.php
'labels' => [
    'from_name' => 'sentence',                     // nebo LabelStyle::Sentence
    // 'from_name' => App\Support\OurLabels::class, // invokable (string $name): string
],

// nebo za běhu, z boot() service provideru
NameLabel::using(fn (string $name): string => __("labels.{$name}"));
```

Invokable třída nebo closura z `using()` rozhoduje o **každém** popisku, včetně
pravidla o textu. Neznámý styl vyhodí `LabelStyleException` místo tichého návratu
k výchozímu, protože překlep by jinak nechal každou obrazovku skoro správně.
Explicitní `->label()` vyhrává vždy a `->label('')` dál znamená „žádný popisek“.

### Vyhodnocování closur

Všechny konfigurační metody přijímají skalární hodnoty i closury:

```php
// Skalár
TextColumn::make('name')->label('Full Name');

// Closura — vyhodnocená pro každý záznam v době renderu
TextColumn::make('name')->label(fn (User $record) => "Name: {$record->name}");

// Closura s dependency injection
Action::make('edit')->hidden(fn (User $record, Table $table) => ! $table->isEditable());
```

## Základní třídy

| Třída | Namespace | Popis |
|-------|-----------|-------------|
| `Component` | `Foundation\Components` | Abstraktní základ — `make()`, `name`, `key` |
| `ViewComponent` | `Foundation\Components` | Komponenta vykreslující Blade pohled |
| `LayoutComponent` | `Foundation\Components` | Komponenta s dětskou `schema()` |

```php
// Všechny komponenty používají vzor statické factory
$field = TextInput::make('email');
$column = TextColumn::make('name');
$action = Action::make('delete');
```

<a id="icons"></a>

## Support utility

| Třída | Popis |
|-------|-------------|
| `EvaluatesClosures` | Trait — vyhodnocuje closura-nebo-hodnota s injekcí parametrů |
| `ArrayDotHelper` | Přístup tečkovou notací: `get('user.name', $array)`, `set()`, `has()`, `forget()` |
| `NameLabel` | Statická — popisek komponenty, která žádný nedostala ([Popisky ze jmen](#popisky-ze-jmen)) |
| `EnumResolver` | Statický — kanonický normalizér enum/pole (`scalar`, `label`, `display`, `color`, `icon`, `options`) |

## V této sekci

| Stránka | Co pokrývá |
| --- | --- |
| [Ikony](icons.md) | Ikonový slovník, vlastní ikony a použití několika sad naráz |
| [Barvy](colors.md) | Kanonický resolver, kterým prochází každý barevný povrch |
| [Enumy](enums.md) | Kontrakty, díky kterým si enum pojmenuje vlastní popisek, barvu a ikonu |
| [Blade komponenty](blade-components.md) | Samostatné `<x-wire::*>` komponenty a layoutové vedle nich |

## Související

- [Wire Core](../overview.md) — jak jsou tyhle moduly navrstvené
- [Motivy a přizpůsobení](../../start/theming.md) — jak přizpůsobit to, co je definované tady
- [Schéma](../schema/overview.md) — layoutový slovník postavený na těchhle concernech
- [Pluginy](../plugins/index.md) — registrace vlastních věcí do stejných registrů
