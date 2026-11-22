---
order: 10
summary: "The shared traits every component composes, the base classes under them, and the support utilities both reach for."
---

# Foundation

Foundation is the permanent core of `wire-core`: the layer nothing above it may
change and everything above it is built from. A field, a column, an action and a
widget are different objects that answer the same questions — what is your label,
your icon, your colour, your size, are you visible — and they answer them with
**the same traits**, which is why the vocabulary is worth learning once.

## Concerns (Traits)

### Component Configuration

| Trait | Methods | Description |
|-------|---------|-------------|
| `HasLabel` | `label($label)`, `translateLabel()`, `getLabel()` | Display label |
| `HasDescription` | `description($text)`, `getDescription()` | Description text |
| `HasHelperText` | `helperText($text)`, `getHelperText()` | Helper text below field |
| `HasHint` | `hint($text)`, `hintIcon($icon)`, `getHint()` | Hint text/icon |
| `HasName` | `name($name)`, `getName()` | Identifier name |
| `HasDefault` | `default($value)`, `getDefault()` | Default value |
| `HasIcon` | `icon($name, $position)`, `getIcon()` | Icon by name (`pencil`, or `prefix:name`) |
| `HasColor` | `color($color)`, `getColor()` | Tailwind color name |
| `HasSize` | `size($size)`, `getSize()` | Size variant (sm/md/lg/xl) |
| `HasColumns` | `columnSpan($span)`, `columnStart($start)` | Grid column layout |
| `HasExtraAttributes` | `extraAttributes(array $attrs)` | Arbitrary HTML attributes |
| `HasSortOrder` | `sort($position)`, `getSort()` | Position in a rendered list — a menu entry, a menu group. Not query sorting (`Column::sortable()`) |

### State & Behavior

| Trait | Methods | Description |
|-------|---------|-------------|
| `HasState` | `state($value)`, `getState()`, `live()`, `debounce($ms)` | Livewire state binding |
| `HasVisibility` | `hidden($condition)`, `visible($condition)`, `isHidden()` | Conditional visibility |
| `HasDisabled` | `disabled($condition)`, `isDisabled()` | Disabled state |
| `HasValidation` | `required()`, `rules($rules)`, `validationMessages($msgs)` | Validation rules |

### Infrastructure

| Trait | Methods | Description |
|-------|---------|-------------|
| `HasMake` | `static make(...$args)` | Static factory |
| `HasEvaluate` | `evaluate($value, $params)` | Closure-or-value evaluation with DI |
| `HasSchema` | `schema(array $components)`, `getSchema()` | Child component array |
| `HasHtmlAttributes` | `htmlAttributes()`, `getHtmlAttributes()` | Merged HTML attrs |
| `EvaluatesClosures` | `evaluate($value, $record, ...)` | Per-record Closure resolution |

### Action-specific

| Trait | Methods | Description |
|-------|---------|-------------|
| `HasDynamicProperties` | `resolve($record)` | Per-record property resolution |
| `HasKeyboardShortcut` | `keyboardShortcut($keys)` | Alpine.js keyboard binding |
| `HasLifecycle` | `before($fn)`, `after($fn)`, `halt()` | Before/after hooks with halt |
| `HasLoadingState` | `loadingIndicator()`, `debounce($ms)` | Loading UI state |
| `HasModal` | `requiresConfirmation()`, `modalHeading()`, `slideOver()`, ... | Modal config |

> Button/badge CSS classes come from the canonical `HasColor` resolvers (see
> [Canonical color resolvers](colors.md#canonical-color-resolvers-hascolor)), not from a
> per-component map. `HasButtonStyles` remains only as a deprecated alias.

### Labels From Names

A component given no `->label()` is named after its `make()` name, and one owner
decides how: `Foundation\Support\NameLabel`. Fields, sections, fieldsets, tabs,
actions, columns, filters and import columns all ask it, so a project changes the
rule once.

**A name that is already text stays text.** A name with a space in it was written
for people, so it is kept as written and only its first letter is raised —
`Section::make('Pro montážníky')` reads "Pro montážníky", not "Pro Montážníky".
**An identifier is humanised by the configured style**, `wire-core.labels.from_name`
(`WIRE_LABELS_FROM_NAME`):

| Style | `plan_period` | `withTransport` |
|---|---|---|
| `headline` (default) | Plan Period | With Transport |
| `sentence` | Plan period | With transport |
| `name` | plan_period | withTransport |

```php
// config/wire-core.php
'labels' => [
    'from_name' => 'sentence',                     // or LabelStyle::Sentence
    // 'from_name' => App\Support\OurLabels::class, // invokable (string $name): string
],

// or at runtime, from a service provider's boot()
NameLabel::using(fn (string $name): string => __("labels.{$name}"));
```

An invokable class or a `using()` closure decides **every** label, the text rule
included. An unknown style throws `LabelStyleException` rather than falling back,
because a typo would otherwise leave every screen almost right. An explicit
`->label()` always wins, and `->label('')` still means "no label at all".

### Closure Evaluation

All configuration methods accept both scalar values and Closures:

```php
// Scalar
TextColumn::make('name')->label('Full Name');

// Closure — evaluated per record at render time
TextColumn::make('name')->label(fn (User $record) => "Name: {$record->name}");

// Closure with dependency injection
Action::make('edit')->hidden(fn (User $record, Table $table) => ! $table->isEditable());
```

## Base Classes

| Class | Namespace | Description |
|-------|-----------|-------------|
| `Component` | `Foundation\Components` | Abstract base — `make()`, `name`, `key` |
| `ViewComponent` | `Foundation\Components` | Component that renders a Blade view |
| `LayoutComponent` | `Foundation\Components` | Component with child `schema()` |

```php
// All components use the static factory pattern
$field = TextInput::make('email');
$column = TextColumn::make('name');
$action = Action::make('delete');
```

## Support Utilities

| Class | Description |
|-------|-------------|
| `EvaluatesClosures` | Trait — evaluates Closure-or-value with parameter injection |
| `ArrayDotHelper` | Dot-notation access: `get('user.name', $array)`, `set()`, `has()`, `forget()` |
| `EnumResolver` | Static — canonical enum/array normalizer (`scalar`, `label`, `display`, `color`, `icon`, `options`) |
| `NameLabel` | Static — the label a component carries when it was given none ([Labels From Names](#labels-from-names)) |

## In This Section

| Page | What it covers |
| --- | --- |
| [Icons](icons.md) | The icon vocabulary, custom icons, and using several sets at once |
| [Colors](colors.md) | The canonical resolver every coloured surface goes through |
| [Enums](enums.md) | The contracts that let an enum name its own label, colour and icon |
| [Blade Components](blade-components.md) | The standalone `<x-wire::*>` components and the layout ones beside them |

## Related

- [Wire Core](../overview.md) — how these modules are layered
- [Theming](../../start/theming.md) — customizing what is defined here
- [Schema](../schema/overview.md) — the layout vocabulary built on these concerns
- [Plugins](../plugins/index.md) — registering your own into the same registries
