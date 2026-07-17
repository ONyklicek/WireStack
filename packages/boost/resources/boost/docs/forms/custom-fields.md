---
order: 40
---

# Extending Forms

Wire Forms is built to be extended. Most apps never need to — the built-in
[field set](fields/index.md) covers the common cases — but when you need a field
that does not ship with the package, you write a small PHP class plus a Blade
view and use it exactly like a first-party field.

This page covers, from least to most involved:

| You need… | Use |
|-----------|-----|
| A one-off custom view inside a form | [`ViewField`](#quick-custom-view-viewfield) |
| A static display block (no input) | [Display component](#display-only-components) |
| A reusable input with its own API | [Custom field](#building-a-custom-field) |
| The same preset applied across many forms | [Form macros](#reusable-presets) |
| A field that stores something other than what it shows | [State transforms](#shaping-the-value-a-field-stores) |
| Logic around every save | [Save hooks](#hooking-into-the-save-lifecycle) |
| A field shipped in a package | [Packaging fields into a plugin](#packaging-fields-into-a-plugin) |

> Prefer the fluent API first. Reach for a custom field only when the same
> behaviour should be reused, or when no built-in field can be configured to do
> the job.

---

## Quick Custom View (ViewField)

When you only need to drop custom markup into a form — without a reusable API —
use [`ViewField`](fields/view-field.md). It renders any Blade view and passes
through the form state path, so you avoid writing a class at all.

```php
use NyonCode\WireForms\Components\Display\ViewField;

ViewField::make('avatar_preview')
    ->view('forms.partials.avatar-preview')
    ->viewData(fn () => ['url' => $this->user->avatar_url]);
```

```blade
{{-- resources/views/forms/partials/avatar-preview.blade.php --}}
<img src="{{ $url }}" class="h-16 w-16 rounded-full" alt="">
```

`viewData()` accepts an array or a closure (evaluated at render time). Use
`ViewField` for previews, callouts, or bespoke widgets that do not need to be a
shared, named component.

---

## How a Field Is Put Together

Every form component extends
`NyonCode\WireCore\Foundation\Components\Component`. That base class gives you,
for free:

- `make(string $name)` factory and a `$name`-only constructor
- label, hint, helper text, id, size, column span, visibility
- `extraAttributes()`, `default()`, and closure evaluation via `evaluate()`
- `render()`, which calls your `viewName()` with the component available in the
  view as `$field`

Input fields extend `NyonCode\WireForms\Components\Field`, which adds the parts
that make a field interactive:

| Concern | What it adds |
|---------|--------------|
| `HasState` | `getStatePath()`, `getWireModelAttribute()` |
| `CanBeLive` | `->live()`, `getWireModelModifier()` |
| `HasDebounce` | `->debounce()`, `getDebounceModifier()` |
| `CanBeReadOnly` | `->disabled()`, `isReadOnly()` |
| `HasFormValidation` | `->rules()`, `->required()`, rule collection |
| `HasPlaceholder`, `HasPrefixAndSuffix`, `HasTooltip`, `CanBeAutofocused` | optional affordances |

A field also declares its **state type** with `getStateType()` (default
`'string'`). The state hydrator uses it to cast raw request values before they
reach the form state — return `'int'`, `'float'`, `'bool'`, or `'array'` when
your value is not a string.

`getStateType()` shapes the value on the way *in*. When your field also needs to
shape it on the way *out* — to store something other than what the widget holds —
implement [`DehydratesState`](#shaping-the-value-a-field-stores) rather than
pushing the job onto every form that uses the field.

The only abstract method you must implement is `viewName()`.

---

## Building a Custom Field

We will build a `MoneyInput` field that stores an integer number of cents and
renders a currency-aware text input.

### 1. The PHP class

```php
<?php

namespace App\Forms\Components;

use NyonCode\WireForms\Components\Field;

class MoneyInput extends Field
{
    protected string $currency = 'USD';

    protected int $decimals = 2;

    public function currency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function decimals(int $decimals): static
    {
        $this->decimals = $decimals;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getDecimals(): int
    {
        return $this->decimals;
    }

    // Value is stored as an integer (cents). [tl! focus:start]
    public function getStateType(): string
    {
        return 'int';
    } // [tl! focus:end]

    protected function viewName(): string // [tl! focus:start]
    {
        return 'forms.components.money-input';
    } // [tl! focus:end]
}
```

Conventions worth following, because the built-in fields all do:

- **Fluent setters return `static`** so calls chain.
- **State is set through protected properties** with matching getters; the Blade
  view reads getters, never properties.
- **Setters accept `Closure` where it makes sense**, and getters resolve them
  with `$this->evaluate(...)`. That is what makes `->label(fn () => ...)` work.

### 2. The Blade view

Wrap the input in the shared field-wrapper partials. They render the label,
hint, required marker, helper text, and validation error for you — so a custom
field looks identical to a built-in one and needs no extra markup for those.

```blade
{{-- resources/views/forms/components/money-input.blade.php --}}
@php
    use App\Forms\Components\MoneyInput;

    assert($field instanceof MoneyInput);

    $wireModifier   = $field->getWireModelModifier();
    $debounceModifier = $field->getDebounceModifier();
    $wireAttr = 'wire:model' . ($wireModifier ? ".{$wireModifier}" : '') . $debounceModifier;
@endphp

@include('wire-forms::partials.field-wrapper-start')

<div class="flex rounded-md shadow-sm">
    <span class="inline-flex items-center rounded-l-md border border-r-0 border-gray-300 bg-gray-50 px-3 text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-400">
        {{ $field->getCurrency() }}
    </span>

    <input
        type="number"
        step="{{ 1 / (10 ** $field->getDecimals()) }}"
        id="{{ $field->getId() }}"
        {{ $wireAttr }}="{{ $field->getWireModelAttribute() }}"
        @if($field->isReadOnly()) readonly @endif
        @if($field->isRequired()) required @endif
        class="block w-full rounded-r-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
    />
</div>

@include('wire-forms::partials.field-wrapper-end')
```

The key wiring is the `wire:model` attribute. `getWireModelAttribute()` returns
the correct dotted state path (for example `data.price`), and the modifier
helpers add `.live` / `.debounce.Nms` when the field opts into them. This is the
same pattern every built-in field uses — see
`packages/forms/resources/views/components/text-input.blade.php` for the full
reference.

### 3. Use it

A custom field is a normal component. Add it to any schema:

```php
use App\Forms\Components\MoneyInput;

$form->schema([
    MoneyInput::make('price')
        ->currency('EUR')
        ->decimals(2)
        ->required()
        ->helperText('Stored in cents.'),
]);
```

No registration step is required for use in your own app: the field resolves its
own view, so listing it in a schema is enough.

---

## Display-Only Components

For output that has no input value — callouts, summaries, computed previews —
extend `NyonCode\WireCore\Foundation\Components\ViewComponent` instead of
`Field`. It is the same base, minus the input/validation concerns, and is what
`Placeholder`, `Alert`, and `Html` use.

```php
<?php

namespace App\Forms\Components;

use Closure;
use NyonCode\WireCore\Foundation\Components\ViewComponent;

class StatBlock extends ViewComponent
{
    protected string|Closure $value = '';

    public function value(string|Closure $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getValue(): string
    {
        return (string) $this->evaluate($this->value);
    }

    protected function viewName(): string
    {
        return 'forms.components.stat-block';
    }
}
```

---

## Reusable Presets

When you do not need a new component, only a **preset** of existing fluent calls,
you have two accurate options. (Form fields are not `Macroable` — unlike `Table`
and `Action`, which support `::macro()`; see
[Core Plugins → Adding Buttons And Actions](../core/plugins.md#adding-buttons-and-actions)
for table/action macros.)

**A static factory** keeps the preset in one place and reads cleanly at the call
site:

```php
namespace App\Forms;

use NyonCode\WireForms\Components\TextInput;

class Fields
{
    public static function slug(string $name): TextInput
    {
        return TextInput::make($name)
            ->helperText('Lowercase, dash-separated.')
            ->rules(['regex:/^[a-z0-9-]+$/'])
            ->live();
    }
}
```

```php
use App\Forms\Fields;

$form->schema([
    Fields::slug('slug'),
]);
```

**A subclass that pre-configures `make()`** is the right choice when you also
want a distinct type you can reference elsewhere:

```php
use NyonCode\WireForms\Components\TextInput;

class SlugInput extends TextInput
{
    public static function make(string $name): static
    {
        return parent::make($name)
            ->rules(['regex:/^[a-z0-9-]+$/'])
            ->live();
    }
}
```

Use a **factory or subclass** when you are composing existing field methods, and
a full [custom field](#building-a-custom-field) when you need new state, a new
view, or new markup.

---

## Shaping the Value a Field Stores

A field's state is whatever its widget produced. Usually that is exactly what
should be persisted — but not always. A date picker's input parses only its own
format while the column wants another; an upload holds a temporary path while the
column wants the stored one; a money field shows `1 234,50` and stores cents.

Two contracts in `NyonCode\WireCore\Foundation\Contracts` cover the two
directions. They are independent — implement only the one you need:

| Contract | Method | Runs |
|---|---|---|
| `HydratesState` | `hydrateState($value, ?Model $record)` | model value → state, after the `getStateType()` cast |
| `DehydratesState` | `dehydrateState($state, ?Model $record)` | state → stored value, during save |

Note that the [`MoneyInput`](#building-a-custom-field) above needs *neither*: its
state is already the integer it stores, which `getStateType(): 'int'` is enough to
express. The contracts earn their keep only when state and stored value genuinely
differ — as here, where the input shows plaintext and the column holds ciphertext:

```php
<?php

namespace App\Forms\Components;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Contracts\DehydratesState;
use NyonCode\WireCore\Foundation\Contracts\HydratesState;
use NyonCode\WireForms\Components\Field;

class EncryptedInput extends Field implements DehydratesState, HydratesState
{
    // Stored → shown. [tl! focus:start]
    public function hydrateState(mixed $value, ?Model $record = null): mixed
    {
        return $value === null || $value === '' ? $value : decrypt($value);
    }

    // Shown → stored.
    public function dehydrateState(mixed $state, ?Model $record = null): mixed
    {
        return $state === null || $state === '' ? $state : encrypt($state);
    } // [tl! focus:end]

    protected function viewName(): string
    {
        return 'forms.components.encrypted-input';
    }
}
```

The same two contracts drive [editable table columns](../table/columns/editing.md) —
`TextInputColumn` uses them for its trim/case/number pipeline — so a component
that implements them behaves the same in a form and in an inline-edited cell.

> **Both directions, or neither.** If a transform moves the value (a timezone
> conversion, a unit change), implementing only `hydrateState()` means the shifted
> state gets written straight back on save, moving the value a little further on
> every round trip. A one-sided transform is worse than none.

**`dehydrateState()` must be a pure function of its arguments.** A host may call
it more than once per save — the table dehydrates once without a record to
validate before opening its transaction, then again with the locked record. Hosts
always pass the original state, never the result of an earlier call, so a
transform that would break if applied twice is still safe. The `$record` is
`null` when the host has none (a create form); a table cell always has one.

---

## Hooking Into the Save Lifecycle

The contracts above belong to a *field* — they travel with it into every form.
The two layers here belong to a **form** or to the **app**. Reach for them when
the knowledge is not the field's: use `DehydratesState` for "this field always
stores cents", a hook for "every form stamps a tenant".

Two layers exist, and they compose:

**Per-form callbacks** — for logic local to one form. Defined on the `Form`
instance and documented in [Save Lifecycle](save-lifecycle.md):

```php
$form
    ->mutateDataBeforeSave(fn (array $data) => [...$data, 'updated_by' => auth()->id()])
    ->beforeSave(fn (array $data) => /* … */)
    ->afterSave(fn ($record) => $record->notifySubscribers());
```

**Plugin hooks** — for logic that should run for *every* form across the app,
without touching each component. The runtime emits `form.saving` (before
persistence, can modify the data) and `form.saved` (after persistence,
observational):

```php
app(PluginManager::class)->hook('form.saving', function (array $payload): array {
    $payload['data']['tenant_id'] ??= auth()->user()->tenant_id;

    return $payload;
}, priority: -100);
```

See [Core Plugins → Hook System](../core/plugins.md#hook-system) for priorities,
typed hooks, and the full payload shape. Use a per-form callback for one form;
use a hook for a cross-cutting rule.

---

## Packaging Fields Into a Plugin

The sections above build a field inside an app, where a class plus a Blade view
is enough — the field resolves its own view, so no registration step is needed.
This section covers the next step: shipping custom fields as a **reusable,
installable unit** — a companion package others can `composer require`, or a
shared module inside a larger app.

### What "registering a field" really means

There is **no field type registry**. Unlike table columns, filters, and actions
— which have `addColumnType()` / `addFilterType()` / `addActionType()` metadata
registries — form fields are never looked up by name. A field is just a class,
and `render()` calls its `viewName()` directly through Laravel's `view()`
helper. So making a packaged field usable comes down to exactly two things:

1. **The field's class is autoloadable** — handled by your package's Composer
   `autoload` block, like any PHP class.
2. **The field's view resolves** — its `viewName()` must point at a view Laravel
   can find. In a package that means registering a **view namespace**.

The [core plugin](../core/plugins.md) is the layer on top: it is where you
install the cross-cutting extras — **presets (macros), save hooks, and default
configuration** — so consumers get them by registering one class. The plugin is
optional for a plain field, and required only once you ship macros or hooks.

> Do **not** register a field with `addColumnType()` (or the other type
> registries). Those are metadata registries for *table* columns/filters/actions
> and are never consulted to render a form field. Registering a field there does
> nothing useful and is misleading.

### 1. Package layout

A minimal field package looks like this:

```text
acme/wire-money-fields/
├── composer.json
├── src/
│   ├── AcmeMoneyServiceProvider.php
│   ├── AcmeMoneyPlugin.php
│   └── Components/
│       └── MoneyInput.php
└── resources/
    └── views/
        └── components/
            └── money-input.blade.php
```

`composer.json` autoloads the namespace and registers the service provider so
the package boots automatically:

```json
{
    "name": "acme/wire-money-fields",
    "require": {
        "nyoncode/wire-forms": "^0.1"
    },
    "autoload": {
        "psr-4": {
            "Acme\\WireMoney\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Acme\\WireMoney\\AcmeMoneyServiceProvider"
            ]
        }
    }
}
```

### 2. The field, pointed at a namespaced view

The field class is identical to the app version, except `viewName()` returns a
**namespaced** view (`namespace::path`) so it resolves no matter where the
package is installed:

```php
<?php

namespace Acme\WireMoney\Components;

use NyonCode\WireForms\Components\Field;

class MoneyInput extends Field
{
    protected string $currency = 'USD';

    public function currency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getStateType(): string
    {
        return 'int';
    }

    protected function viewName(): string
    {
        // "acme-money" is the namespace registered by the service provider.
        return 'acme-money::components.money-input';
    }
}
```

### 3. Register the view namespace in the service provider

The service provider's one required job is to make `acme-money::` resolvable.
You can register it manually with `loadViewsFrom()`, and `publishes()` the views
so consumers can override your markup:

```php
<?php

namespace Acme\WireMoney;

use Illuminate\Support\ServiceProvider;
use NyonCode\WireCore\Core\Plugin\PluginManager;

final class AcmeMoneyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register the plugin (macros, hooks, config) once the manager resolves.
        $this->app->resolving(PluginManager::class, function (PluginManager $manager) {
            if (! $manager->has('acme-money')) {
                $manager->register($this->app->make(AcmeMoneyPlugin::class));
            }
        });
    }

    public function boot(): void
    {
        // Makes acme-money::components.money-input resolvable.
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'acme-money');

        // Let consumers override the markup with vendor:publish.
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/acme-money'),
        ], 'acme-money::views');
    }
}
```

> If your package is built on `spatie/laravel-package-toolkit` (the same toolkit
> Wire packages use), calling `->hasViews()` in your package configuration
> registers the namespace and the publish tag for you, derived from the package
> short name — exactly how `wire-forms::partials.field-wrapper-start` is exposed.

Once the namespace is registered, the field is fully usable — `MoneyInput::make('price')`
works in any schema with no further setup.

### 4. The plugin: presets, hooks, and config

Add a plugin when you want to ship more than the field class — for example a
reusable **preset macro**, a **save hook**, or **default configuration**. Form
fields are not `Macroable`, so field presets ship as a static factory or a
subclass (see [Reusable Presets](#reusable-presets)); the plugin is where you
register `Table`/`Action` macros, hooks, and config.

```php
<?php

namespace Acme\WireMoney;

use NyonCode\WireCore\Core\Plugin\Contracts\HasConfiguration;
use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\PluginManager;

final class AcmeMoneyPlugin implements HasConfiguration, Plugin
{
    public function getId(): string
    {
        return 'acme-money';
    }

    public function defaultConfig(): array
    {
        return ['default_currency' => 'USD'];
    }

    public function register(PluginManager $manager): void
    {
        // A cross-cutting save hook: round any money field to whole cents.
        $manager->hook('form.saving', function (array $payload): array {
            foreach ($payload['data'] as $key => $value) {
                if (str_ends_with($key, '_cents') && is_numeric($value)) {
                    $payload['data'][$key] = (int) round($value);
                }
            }

            return $payload;
        });
    }

    public function boot(PluginManager $manager): void
    {
        // Config-driven defaults are available here if you need them:
        // $manager->getPluginConfig($this->getId())['default_currency']
    }
}
```

The plugin is wired up automatically by the service provider's `resolving()`
callback in step 3, so consumers get the field, its views, and its hooks just by
installing the package. See
[Core Plugins → Register Plugins From A Package](../core/plugins.md#register-plugins-from-a-package)
for the registration pattern and the `has()` guard.

### 5. Consumers install it

From the consuming app, installation is just Composer plus optional view
overrides:

```bash
composer require acme/wire-money-fields

# Optional: override the field markup
php artisan vendor:publish --tag=acme-money::views
```

```php
use Acme\WireMoney\Components\MoneyInput;

$form->schema([
    MoneyInput::make('price_cents')->currency('EUR'),
]);
```

> **Plugin config tags use `::`** — `acme-money::views`, `acme-money::config` —
> not a dash. A dashed tag like `acme-money-views` resolves to nothing.

### Checklist

| Step | Required for | Mechanism |
|------|-------------|-----------|
| Autoload field class | Always | Composer `psr-4` |
| Register view namespace | Always (in a package) | `loadViewsFrom()` / `->hasViews()` |
| `viewName()` returns `namespace::path` | Always (in a package) | Field class |
| Publish views | Optional (override support) | `publishes(..., 'tag::views')` |
| Register the plugin | Only for macros/hooks/config | `resolving(PluginManager::class)` |
| Default config | Only for configurable plugins | `HasConfiguration` |

---

## JS-Backed Fields

There are two levels of client-side behaviour in the built-in fields:

- **Inline Alpine.** Lightweight interactivity needs no separate bundle. `Slider`
  and `Rating`, for example, drive everything from an `x-data` block and
  `@entangle` the field's state path, with any CSS inlined once via `@once`. For
  most custom fields this is all you need — see
  `packages/forms/resources/views/components/slider.blade.php`.

- **Pre-bundled script via `@assets`.** Heavier fields (like `TiptapEditor`) ship
  a pre-built JS bundle that the provider serves from a route
  (`/wire-forms/assets/{asset}.js`). The field view injects it with Livewire's
  `@assets` directive so the script runs even when the field is opened inside a
  modal — where a plain `<script>` tag injected through DOM morphing would never
  execute. See `packages/forms/resources/views/components/tiptap-editor.blade.php`:

  ```blade
  @assets
  <script src="{{ route('wire-forms.asset', ['asset' => 'tiptap']) }}"></script>
  @endassets
  ```

If you build a heavier JS-backed field in your own package, follow the same
pattern: bundle the script, expose it on a route, and inject it with `@assets`
from the field view so it is present whenever the field renders.

---

## Testing Custom Fields

Fields render to HTML, so the fastest tests assert on output and configuration.

```php
use App\Forms\Components\MoneyInput;

it('renders the currency symbol', function () {
    $field = MoneyInput::make('price')->currency('EUR');

    expect($field->getCurrency())->toBe('EUR')
        ->and($field->getStateType())->toBe('int')
        ->and((string) $field->toHtml())->toContain('EUR');
});
```

For end-to-end behaviour (state binding, validation, save), exercise the field
inside a Livewire form component with Livewire's testing helpers, the same way the
package tests the built-in fields. Run them with `composer test:forms`.

---

## See Also

- [Form Fields reference](fields/index.md) — every built-in field
- [Save Lifecycle](save-lifecycle.md) — per-form save callbacks
- [Validation](validation.md) — rule collection and messages
- [Core Plugins](../core/plugins.md) — hooks, macros, type registries, packaging
