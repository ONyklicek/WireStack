---
summary: A dropdown over static or queried options, with server-side search, multi-select and create-on-the-fly.
---

# Select

Dropdown select with static or dynamic options, search, and multi-select.

```php
use NyonCode\WireForms\Components\Select;
```

> **Mobile.** The dropdown/search panel opens as a bottom sheet below the
> configured breakpoint (searchable selects stay a floating panel by default so
> the search box stays usable). Override per field with `->sheetOnMobile()` /
> `->mobileBreakpoint('md')` — see [mobile presentation](../../start/configuration.md#mobile).

## Basic Usage

```php
Select::make('role')
    ->options([
        'admin' => 'Administrator',
        'editor' => 'Editor',
        'user' => 'User',
    ])
```

## Dynamic Options

```php
Select::make('category_id')
    ->options(fn () => Category::pluck('name', 'id')->toArray())
    ->placeholder('Choose category')
```

## Enum Options

Pass a PHP enum class directly instead of an array — the cases are expanded to a
`value => label` map. The key is the backing value (or the case name for unit enums),
and the label comes from the enum's `getLabel()` when it implements the
`Foundation\Contracts\Enum\HasLabel` contract, falling back to a headline of the case name.

```php
use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;

enum Status: string implements HasLabel
{
    case Draft = 'draft';
    case Published = 'published';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
        };
    }
}

Select::make('status')->options(Status::class)
// → ['draft' => 'Draft', 'published' => 'Published']
```

An enum without `HasLabel` still works — the case name is headlined for the label
(`LowPriority` → `Low Priority`). A closure returning an enum class is expanded too.

**Automatic validation.** A single-value `Select` (or [`Radio`](radio.md)) whose options come
from an enum is automatically constrained to those values with an `in:` rule — a submission
outside the enum is rejected without you restating it. It is skipped for `multiple()` selects
(array state) and when you declare your own `in:` / `Rule::in()` / `Rule::enum()` rule.

> The same `->options(Enum::class)` shorthand works on [`Radio`](radio.md),
> [`CheckboxList`](checkbox-list.md), table `SelectColumn`, and the table
> [`SelectFilter`](../../table/filters/index.md).

## Clearing a Selection

Picking the empty (placeholder) choice stores **null**, not an empty string —
which matters most against an enum-cast column, where `''` is not a valid backing
value and the cast would throw on save:

```php
Select::make('status')
    ->options(Status::class)      // enum-cast column
    ->placeholder('No status')    // choosing this stores null
```

Multi-selects are unaffected: their empty state is `[]`, which an array cast
stores as it is.

## Searchable

```php
Select::make('user_id')
    ->options(fn () => User::pluck('name', 'id')->toArray())
    ->searchable()
    ->noSearchResultsMessage('No users found')
    ->searchPrompt('Type to search...')
    ->loadingMessage('Loading...')
```

## Remote Search

Instead of filtering a preloaded list in the browser, resolve matches on the
server as the user types:

```php
Select::make('author_id')
    ->getSearchResultsUsing(fn (string $search) =>
        User::where('name', 'like', "%{$search}%")->limit(50)->pluck('name', 'id')->all()
    )
    ->getOptionLabelUsing(fn ($value) => User::find($value)?->name)
```

- `getSearchResultsUsing()` implies `searchable()` and returns a `value => label` map.
- `getOptionLabelUsing()` (single) / `getOptionLabelsUsing()` (multiple) resolve the
  label(s) for the current selection, so the trigger stays readable even when the
  chosen option was never preloaded.
- `preload()` eagerly seeds the remote list on render (runs the search callback with
  an empty term) instead of waiting for the first keystroke.

The host must expose the search endpoint — any `WithForms` component or a table
action modal does. A [`BelongsToSelect`](belongs-to-select.md) gets relationship-driven
remote search automatically.

## Create & Edit Options

Let the user create a new option — or edit the selected one — from a modal without
leaving the form:

```php
Select::make('category_id')
    ->options(fn () => Category::pluck('name', 'id')->all())
    ->createOptionForm([
        TextInput::make('name')->required(),
    ])
    ->createOptionUsing(fn (array $data) => Category::create($data)->getKey())
    ->editOptionForm([
        TextInput::make('name')->required(),
    ])
    ->fillEditOptionUsing(fn ($value) => Category::find($value)->only('name'))
    ->updateOptionUsing(fn ($value, array $data) => Category::find($value)->update($data))
```

A "+ Create" (and, for a selected value, "Edit") affordance appears in the combobox
panel footer and opens an isolated modal. Validation keeps the modal open with
errors; on success the new value is selected (appended for a multi-select).

- `createOptionUsing()` returns the new option's value — a scalar key, or a model
  whose key is used.
- Editing targets the single selected option, so it is unavailable on `multiple()`.
- Works in standalone `WithForms` components **and** inside table action modals.
- So the newly created value renders a label, pair with `getOptionLabelUsing()`
  or a preloaded option list.
- The created/edited option is merged into the open combobox immediately (the host
  dispatches `select-option-created` / `select-option-updated` browser events) — no
  page refresh needed.

### A Full Form, Not A Field List

The option schema is an ordinary form schema and the mounted option form is a
first-class form on the host, so the pieces that need the host to find a field by
state path work inside it like anywhere else:

```php
Select::make('category_id')
    ->createOptionForm([
        Wizard::make('category')->schema([                       // [tl! focus:start]
            Step::make('Basics')->schema([
                TextInput::make('name')->required(),
            ]),
            Step::make('Placement')->schema([
                Select::make('parent_id')
                    ->getSearchResultsUsing(fn (string $search) =>
                        Category::where('name', 'like', "%{$search}%")->pluck('name', 'id')->all()
                    ),
            ]),
        ]),                                                      // [tl! focus:end]
    ])
    ->createOptionUsing(fn (array $data) => Category::create($data)->getKey())
```

- A [`Wizard`](../../core/schema/layout/wizard.md) gates each step: "Next" validates only that step's
  fields and stays put on failure, with the errors landing on the option bag
  (`createOptionFormData.*`) where the modal already shows them.
- A nested `Select` reaches the remote-search endpoint, and field actions
  (`suffixAction()`, `hintAction()`, `Button`) resolve and run.
- Opening a *second* option modal from inside an option form is refused, not
  nested: there is one mounted path and one data bag per kind, so honouring it
  would discard the form being filled in.

Add [`navigation(false)`](../../core/schema/layout/wizard.md#handing-the-navigation-elsewhere)
to the wizard and the **modal footer takes over its navigation** — Back and Next
next to Cancel, with the submit button appearing only on the last step, instead of
a second navigation row inside the panel. Name the wizard when both option modals
can be open at once: the footer and the wizard find each other by that name.

### Configuring The Option Modal

Neither option modal is a special case: both are configured through the same
`Modal` object the action modals use, so heading, description, icon, width, close
behaviour, sticky chrome and the two button labels all live in one place.

```php
use NyonCode\WireCore\Modals\Modal;

Select::make('category_id')
    ->options(fn () => Category::pluck('name', 'id')->all())
    ->createOptionForm([TextInput::make('name')->required()])
    ->createOptionUsing(fn (array $data) => Category::create($data)->getKey())
    ->createOptionModal(fn (Modal $modal) => $modal   // [tl! focus:start]
        ->heading('New category')
        ->description('It becomes selectable as soon as you save it.')
        ->icon('outline:folder-plus')
        ->width('2xl')
        ->closeOnClickAway(false)
        ->stickyFooter()
        ->submitLabel('Create category')
        ->cancelLabel('Discard'))                     // [tl! focus:end]
    ->editOptionModal(fn (Modal $modal) => $modal->width('xl'))
```

The callback configures the modal in place; returning a `Modal` replaces it
wholesale. It runs when the schema is defined, not once per render, so text that
depends on state goes through the config's own closure support —
`$modal->heading(fn (Select $field) => …)`, evaluated with the field as context.

`createOptionModalHeading()` / `createOptionModalWidth()` and their `editOption…`
twins remain as shorthands and write into that same object, so the two ways of
setting a heading cannot drift apart. Width takes a `ModalWidth` case or its token
(`sm`…`7xl`, `full`); an unknown token falls back to `md`, and an unconfigured
modal follows `wire-core.modals.default_width` like every other modal.

The modal's `id`, `wire:model` and close action are deliberately **not**
configurable. They key the teleport Livewire morphs by, and both option modals can
be mounted at once — a caller-set `id` would let their contents swap.

## Reactivity

The combobox binds deferred by default. Add `live()` when other fields react to the
selection — `afterStateUpdated()`, a sibling's `visibleWhen()`, or `Form::live()` —
so picking an option syncs to the server on click instead of waiting for the next
roundtrip:

```php
Select::make('type')
    ->options([...])
    ->live()
    ->afterStateUpdated(fn ($state, $set) => $set('label', ucfirst((string) $state)))
```

## Multi-Select

```php
Select::make('tags')
    ->multiple()
    ->maxItems(5)
    ->minItems(1)
    ->options([...])
```

## Relationship

```php
Select::make('author_id')
    ->relationship('author', 'name')
    ->searchable()
```

## Native vs Custom

Every `Select` renders through the custom combobox by default, so searchable and
non-searchable selects share one design — [`searchable()`](#searchable) simply adds
the in-panel search input. Use `native()` to opt into the browser-native
`<select>` element instead.

```php
Select::make('country')
    ->searchable()      // combobox with a search input
    ->native()          // force the browser-native <select> instead
```

### Native on phones only

Some selects are fine as a combobox on a desktop and hard work on a phone — a
long list in a 360px-wide sheet, a multi-select you have to scroll through.
`nativeOnMobile()` keeps the combobox from the field's
[mobile breakpoint](../../start/configuration.md#mobile) up and hands the screen
below it to the browser's `<select>`, which a phone opens as its own wheel or
full-screen list.

```php
Select::make('country')
    ->options(Country::pluck('name', 'code')->toArray())
    ->searchable()
    ->nativeOnMobile()          // phone: native <select>; tablet/desktop: combobox
    ->mobileBreakpoint('md')    // the split follows the sheet breakpoint
```

How it resolves:

- Both controls are in the markup, bound to the same state, and CSS at the
  breakpoint shows one of them. Nothing is decided in the browser, so there is
  no flash and no difference between the first paint and a Livewire update.
- The `<label>` points at the combobox; the native twin gets its own id
  (`{id}-native`) and names itself with `aria-label`. `required()` becomes
  `aria-required` on both halves, because a required element the stylesheet
  hides would stop the form submitting on the other screen size.
- The bottom sheet this combobox would become on a phone is not rendered — the
  native element owns that screen.
- `native()` wins over `nativeOnMobile()`. A select that relies on the combobox
  keeps it on every screen: remote search (`getSearchResultsUsing()` or a
  non-preloaded relationship), and `createOptionForm()` / `editOptionForm()`,
  whose buttons live in the combobox panel. A `multiple()` select goes native
  too — the phone's own checklist.
- An empty single select starts on a blank row, so the element never shows the
  first option while the value is null. On an optional field the row is a real
  choice — how a phone clears the value — labelled by the placeholder or `—`; on
  a required one it is disabled and hidden. Below `sm` the element is 16px,
  because iOS Safari zooms the page into any smaller control the moment it is
  tapped.
- The app-wide default is `wire-core.mobile.native` (`WIRE_MOBILE_NATIVE`).
  An explicit `native(false)` opts a field out of it; `nativeOnMobile(false)`
  does the same without touching `native()`.

Disabled options (`disabledOptions()`) and the option order are the same in
both controls.

### Touch list on phones

`touchOnMobile()` keeps the combobox from the field's mobile breakpoint up and
opens a list made for a thumb below it: a bottom sheet with 48px rows, 16px text
(iOS does not zoom), the search pinned at the top and a grabber to swipe it
away.

```php
Select::make('customer_id')
    ->relationship('customer', 'name')
    ->searchable()
    ->touchOnMobile()           // phone: touch list; desktop: combobox
```

- It is the same combobox, drawn for a phone: remote search,
  `createOptionForm()` / `editOptionForm()`, disabled options and the option
  order all work — everything the browser's `<select>` drops.
- A searchable list takes the full height, so it does not jump as the search
  narrows it; a short list sits at the height of its rows.
- A single pick closes the sheet. A `multiple()` select ticks checkboxes, counts
  them in the header, and closes on **Done**; **Clear all** empties it.
- The search is not focused on open, so no keyboard slides up over a list you
  only meant to scroll.
- `native()` wins over it; it wins over `nativeOnMobile()`. The app-wide default
  is `wire-core.mobile.touch`.

## Boolean Select

```php
Select::make('active')
    ->boolean()         // Yes/No options
```

## Disabled Options

Render specific options as non-selectable:

```php
Select::make('status')
    ->options([
        'draft'     => 'Draft',
        'review'    => 'In Review',
        'published' => 'Published',
        'archived'  => 'Archived',
    ])
    ->disabledOptions(['archived'])
```

Dynamic disabled options:

```php
Select::make('tier')
    ->options(Plan::pluck('name', 'id')->toArray())
    ->disabledOptions(fn () => Plan::unavailable()->pluck('id')->toArray())
```

## Methods

| Method | Type | Description |
|--------|------|-------------|
| `options(array\|string\|Closure)` | array | Static, dynamic, or enum-class options (`value => label`) |
| `searchable()` | bool | Enable option search |
| `multiple()` | bool | Allow multiple selections |
| `native(bool $native = true)` | bool | Use the browser-native `<select>` instead of the combobox (default: `false`) |
| `touchOnMobile(bool $condition = true)` | bool | Full-height touch list below the mobile breakpoint, the combobox above it (default: `wire-core.mobile.touch`, `false`) |
| `nativeOnMobile(bool $condition = true)` | bool | Browser-native `<select>` below the mobile breakpoint only, the combobox above it (default: `wire-core.mobile.native`, `false`) |
| `maxItems(int\|null)` | int | Maximum selected items (multi-select) |
| `minItems(int\|null)` | int | Minimum selected items (multi-select) |
| `disabledOptions(array\|Closure)` | array | Option keys that are rendered as disabled |
| `noSearchResultsMessage(string\|null)` | string | Message when search finds nothing |
| `loadingMessage(string\|null)` | string | Message while options are loading |
| `searchPrompt(string\|null)` | string | Prompt shown in the search box |
| `boolean()` | — | Shorthand for Yes/No options |
| `relationship(?string, ?string)` | — | Load options from a relationship |
| `getSearchResultsUsing(Closure)` | — | Remote search: resolve matches on the server (implies `searchable()`) |
| `getOptionLabelUsing(Closure)` / `getOptionLabelsUsing(Closure)` | — | Resolve label(s) for the current selection |
| `preload()` | bool | Eagerly seed the remote option list on render |
| `createOptionForm(array\|Closure)` / `createOptionUsing(Closure)` | — | Create a new option from a modal |
| `editOptionForm(array\|Closure)` / `fillEditOptionUsing(Closure)` / `updateOptionUsing(Closure)` | — | Edit the selected option from a modal |
| `createOptionModal(Closure)` / `editOptionModal(Closure)` | — | Configure the option modal through the canonical `Modal` object |
| `createOptionModalHeading(string)` / `editOptionModalHeading(string)` | string | Modal headings (shorthand) |
| `createOptionModalWidth(string\|ModalWidth\|null)` / `editOptionModalWidth(string\|ModalWidth\|null)` | string | Modal widths (`sm`…`7xl`, `full`; default `md`) (shorthand) |
| `placeholder(string\|Closure)` | string | Empty/blank option label |
| `disabled(bool\|Closure)` | bool | Disable the select |
| `required()` | — | Mark as required |
| `live()` | — | Trigger Livewire update on change |

See [Common Field API](index.md#common-field-api) for label, hint, tooltip, and other shared methods.
