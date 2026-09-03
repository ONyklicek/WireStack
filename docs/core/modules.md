---
order: 96
summary: The domain axis — one business area declared in one place, registered as a plugin, spread into the registries that own resources, dashboards and the menu.
---

# Domain Modules

Packages are the technical axis of this framework: core, forms, table, sortable.
A **domain module** is the other one — `billing` beside `operations` beside
`crm` — and it exists so a business area is declared in a single place instead
of being spread across an application's provider as three unrelated lists.

A module owns no primitives and forks none. It names what an area consists of;
the layers that already own those things keep owning them.

## How It Works

A module is a **plugin**, not a parallel registration system. That is the whole
design decision, and it is what keeps the lifecycle honest:

1. It registers from `config('wire-core.plugins')` like any other plugin, so a
   module is installed the way everything else is.
2. `PluginManager` gives it the guarantees a module needs and already had:
   one id per module, every module registered before any is booted, and a
   dependency that must be registered first or registration is refused.
3. `WireCoreServiceProvider` then reads what each module declares and fills the
   [resource registry](resources.md), the [dashboard registry](widgets.md) and
   the [navigation groups](resources.md#navigation-and-workspace).

Step 3 is the provider's rather than the module's on purpose. A dashboard lives
in the widgets layer and a module contract reaching for `DashboardRegistry`
would be an import the architecture test refuses; naming a class costs no
import, so a module stays a declaration and the provider — which already holds
every registry — does the wiring.

There is deliberately **no module registry**: `PluginManager` already holds the
list, and a second registry over one list is the duplication this codebase keeps
removing.

## Declaring One

```php
use NyonCode\WireCore\Core\Modules\DomainModule;   // [tl! focus:start]
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;

final class BillingModule extends DomainModule
{
    public function getId(): string
    {
        return 'billing';
    }

    public function resources(): array
    {
        return [InvoiceResource::class, CreditNoteResource::class];
    }

    public function navigation(): ?NavigationGroup
    {
        return NavigationGroup::make('billing')
            ->label(__('nav.billing'))
            ->icon('outline:banknotes')
            ->sort(20);
    }
}   // [tl! focus:end]
```

```php
// config/wire-core.php
'plugins' => [
    App\Modules\BillingModule::class,
    App\Modules\OperationsModule::class,
],
```

Everything is optional but the id. A module that declares only resources is
ordinary; so is one that declares only a dashboard.

## Depending On Another Module

`dependencies()` comes from the plugin system unchanged — list the ids that must
be registered first:

```php
use NyonCode\WireCore\Core\Plugin\Contracts\HasDependencies;

final class OperationsModule extends DomainModule implements HasDependencies
{
    public function getId(): string
    {
        return 'operations';
    }

    public function dependencies(): array   // [tl! focus]
    {
        return ['billing'];
    }

    public function dashboards(): array
    {
        return [OverviewDashboard::class];
    }
}
```

Registering `operations` before `billing` throws rather than booting into a
half-built application — the ordering is checked, not hoped for.

## What A Module Does Not Do

| Not this | Because |
| --- | --- |
| Register workflows | A workflow has one group of consumers, and the resource that owns the entity carries it. See [Workflow And Transitions](actions.md#workflow-and-transitions) |
| Register policies | Laravel's `Gate` owns those |
| Enumerate workspaces | `Workspace` is a service over the registries, not a class to list |
| Fork a primitive | A module composes `Table`, `Form`, `Widget` and `Resource` unchanged; it is the domain axis, not a second implementation |

## Introspection

`describe-module` reports what an application's modules declare — the one thing
`describe-resource` cannot show, because a resource does not know which business
area it belongs to:

```text
describe-module              # every registered module
describe-module billing      # one, by id
```

## DomainModule API

| Method | Returns | Purpose |
| --- | --- | --- |
| `getId(): string` | `string` | The module's id, unique among all plugins. Required |
| `resources(): array` | `array<int, class-string>` | Resource classes this area consists of |
| `dashboards(): array` | `array<int, class-string>` | Dashboard classes it brings |
| `navigation(): ?NavigationGroup` | `NavigationGroup\|null` | The menu group its entries sit under |
| `dependencies(): array` | `array<int, string>` | Module ids that must register first (via `HasDependencies`) |
| `register()` / `boot()` | `void` | The plugin lifecycle; empty by default, override to add hooks or bindings |
