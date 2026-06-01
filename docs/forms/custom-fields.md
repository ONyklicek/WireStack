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
| The same preset applied across many forms | [Form macros](#reusable-presets-with-macros) |
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

    // Value is stored as an integer (cents).
    public function getStateType(): string
    {
        return 'int';
    }

    protected function viewName(): string
    {
        return 'forms.components.money-input';
    }
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

## Hooking Into the Save Lifecycle

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

To ship custom fields (and their presets) as a reusable unit — in your app or a
companion package — group them behind a [core plugin](../core/plugins.md). The
plugin's `boot()` is the right place to register macros and the field views.

```php
<?php

namespace App\Wire\Plugins;

use Illuminate\Support\Facades\View;
use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\PluginManager;

final class MoneyFieldsPlugin implements Plugin
{
    public function getId(): string
    {
        return 'money-fields';
    }

    public function register(PluginManager $manager): void
    {
        // Optional: expose the field class by alias for plugin-aware tooling.
        $manager->addColumnType('money', \App\Forms\Components\MoneyInput::class);
    }

    public function boot(PluginManager $manager): void
    {
        // Make the package's views resolvable under a namespace, so the
        // field's viewName() can return 'money-fields::components.money-input'.
        View::addNamespace('money-fields', __DIR__ . '/../../resources/views');
    }
}
```

Register the plugin in `config/wire-core.php` (app) or from your package service
provider (see [Register Plugins From A Package](../core/plugins.md#register-plugins-from-a-package)).
When shipping from a package, point your fields' `viewName()` at the namespaced
views (`money-fields::components.money-input`).

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
