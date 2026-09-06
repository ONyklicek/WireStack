<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Modules;

use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;

/**
 * One business area's declarations, in one place.
 *
 * The second axis beside packages: packages are the technical one — core,
 * forms, table, sortable — and a module is `billing` beside `operations` beside
 * `crm`. It names the things an area consists of and lets the layers that
 * already own them do the owning.
 *
 * **It is a manifest, not a domain layer.** The name was `DomainModule` and that
 * oversold it: this holds three lists of class names and a menu heading, and it
 * is not a bounded context, an aggregate boundary or a place to model anything.
 * A module has no behaviour of its own — the resources it names carry it, the
 * models carry the data, and Laravel's Gate carries the policies. Nothing here
 * isolates one area's code from another's; if that is what an application needs,
 * it needs it in its own namespaces and its own tests, and this list will not
 * provide it.
 *
 *   final class BillingModule extends Module
 *   {
 *       public function getId(): string { return 'billing'; }
 *
 *       public function resources(): array { return [InvoiceResource::class]; }
 *
 *       public function navigation(): ?NavigationGroup
 *       {
 *           return NavigationGroup::make('billing')->icon('outline:banknotes')->sort(20);
 *       }
 *   }
 *
 * **It is a {@see Plugin}, not a parallel registration system.** The lifecycle
 * guarantee a module needs — every module registered before any is booted, a
 * dependency that must exist first — is exactly what `PluginManager` already
 * gives, so a module registers through `config('wire-core.plugins')` like
 * anything else and `dependencies()` comes from `HasDependencies`. V2.6 §2
 * planned a `ModuleRegistry` beside it; measuring found nothing for it to own
 * that `PluginManager` does not already hold, and a second registry over one
 * list is the shape this codebase keeps deleting.
 *
 * **It declares; it does not distribute.** `WireCoreServiceProvider` reads these
 * methods and fills the resource registry, the dashboard registry and the
 * navigation groups. That is not indirection for its own sake: a dashboard lives
 * in `Widgets/` (L2) and a module contract that reached for `DashboardRegistry`
 * would be an L2→L2 import `ModuleLayersTest` fails on. Naming classes costs no
 * import, so a module stays a declaration and the provider — which is outside
 * the layer map and already holds both registries — does the wiring.
 *
 * What a module deliberately does **not** do:
 *
 *  - **workflows.** Measured in V2.6 step 4: a workflow has one group of
 *    consumers, and the resource that owns the entity already carries it.
 *  - **policies.** Laravel's `Gate` owns those.
 *  - **workspaces.** `Workspace` is a service over the registries, not a class
 *    a module enumerates.
 */
abstract class Module implements Plugin
{
    /**
     * The resource classes this business area consists of.
     *
     * Class names, not instances: the registry answers identity and routing from
     * the static half of the contract, so listing fifty resources composes no
     * table and no form.
     *
     * @return array<int, class-string>
     */
    public function resources(): array
    {
        return [];
    }

    /**
     * The dashboard classes this business area brings with it.
     *
     * @return array<int, class-string>
     */
    public function dashboards(): array
    {
        return [];
    }

    /**
     * The menu group this area's entries sit under, if it wants one of its own.
     *
     * Returning a group is how a module says "these belong together" in the one
     * place a user sees it. The resources still name the group by key; this
     * declares what that key looks like, so a module ships its heading, icon and
     * order with the things it groups rather than leaving them to an application
     * that installed it.
     */
    public function navigation(): ?NavigationGroup
    {
        return null;
    }

    /**
     * Plugin lifecycle. Empty by default because a module that only declares
     * needs neither — override to add hooks, bindings or anything a plugin does.
     */
    public function register(PluginManager $manager): void {}

    public function boot(PluginManager $manager): void {}
}
