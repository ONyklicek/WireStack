<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroups;
use Workbench\App\Livewire\Dashboards\ShowOverview;
use Workbench\App\Livewire\Previews\CorePreview;
use Workbench\App\Livewire\Previews\FieldPreview;
use Workbench\App\Livewire\Previews\FormPreview;
use Workbench\App\Livewire\Previews\GestureLabPreview;
use Workbench\App\Livewire\Previews\InfolistPreview;
use Workbench\App\Livewire\Previews\ModalStackingPreview;
use Workbench\App\Livewire\Previews\PanelPreview;
use Workbench\App\Livewire\Previews\SortablePreview;
use Workbench\App\Livewire\Previews\SpaPlainPreview;
use Workbench\App\Livewire\Previews\SpaTablePreview;
use Workbench\App\Livewire\Previews\TablePreview;
use Workbench\App\Livewire\Previews\WidgetPreview;
use Workbench\App\Livewire\Resources\CreateInvoice;
use Workbench\App\Livewire\Resources\EditInvoice;
use Workbench\App\Livewire\Resources\InvoiceItemsRelationManager;
use Workbench\App\Livewire\Resources\ListDocuments;
use Workbench\App\Livewire\Resources\ListInvoices;
use Workbench\App\Livewire\Resources\ListTasks;
use Workbench\App\Livewire\Resources\ViewInvoice;
use Workbench\App\Modules\BillingModule;
use Workbench\App\Modules\OperationsModule;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Two domain modules, and nothing else. V2.6 step 5: what used to be
        // three arrays here — resources, dashboards, navigation groups, each
        // listing things this provider had to know about individually — is now
        // two areas that each name their own, arriving by the two routes a
        // module has.
        // Billing arrives the way a *package* ships a module — the workbench
        // standing in for `nyoncode/wire-module-billing`, which no application
        // could add to its own config file. `resolving` rather than `boot()`:
        // the callback runs while the container builds the manager, so the
        // module is in the list before `PluginManager::boot()` and before the
        // provider spreads declarations into the registries. Registering any
        // later is refused outright, because it would look installed and do
        // nothing. The `has()` guard is what makes a provider that boots twice
        // (tests do) idempotent.
        $this->app->resolving(PluginManager::class, function (PluginManager $manager): void {
            if (! $manager->has('billing')) {
                $manager->register(new BillingModule);
            }
        });

        // Operations arrives the way an *application* installs one. Both paths
        // are exercised on purpose: this is the only place in the repository
        // where a module reaches the registries through a provider, and a path
        // with no consumer is the kind that breaks unnoticed.
        //
        // Ordering still holds: operations declares a dependency on billing, and
        // `resolving` callbacks run before `afterResolving` ones — which is where
        // config is read — so billing is registered first without anyone
        // sequencing it by hand.
        //
        // Note what stayed true: operations sorts its group above billing's, so
        // the sidebar still disagrees with this order on purpose.
        config()->set('wire-core.plugins', [
            OperationsModule::class,
        ]);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // What a full-page Livewire component is wrapped in. The framework does
        // not supply this on purpose: the shell around a routed page belongs to
        // the application, exactly as the routes do.
        //
        // The key is `component_layout`, not `layout`: Livewire 4 reads the
        // former (PageComponentConfig), and the latter is the Livewire 2/3 name
        // that silently does nothing here. Setting the wrong one fails as
        // "No hint path defined for [layouts]", which reads like a missing view
        // rather than a wrong key — worth the note, since every guide still
        // shows the old one.
        config()->set('livewire.component_layout', 'components.layouts.wire');
        // What is left for the application to declare: the one group no single
        // module owns. The dashboard lives in operations, but "Insights" is the
        // application's own heading above everything, which is exactly the split
        // a module axis is supposed to leave behind.
        $this->app->make(NavigationGroups::class)->register(
            NavigationGroup::make('insights')
                ->label('Insights')
                ->icon('outline:chart-bar')
                ->sort(5),
        );

        // Workbench components live outside the default App\Livewire namespace,
        // so Livewire cannot resolve their auto-generated names on the update
        // roundtrip ("Unable to find component"). Register them explicitly under
        // those auto names so interactive previews (clicking actions, wizard
        // steps, dropdowns) actually work, not just the initial render.
        foreach ([
            CorePreview::class,
            FieldPreview::class,
            FormPreview::class,
            GestureLabPreview::class,
            InfolistPreview::class,
            ModalStackingPreview::class,
            PanelPreview::class,
            SortablePreview::class,
            SpaPlainPreview::class,
            SpaTablePreview::class,
            TablePreview::class,
            WidgetPreview::class,
            ListInvoices::class,
            ListTasks::class,
            ListDocuments::class,
            ShowOverview::class,
            CreateInvoice::class,
            EditInvoice::class,
            ViewInvoice::class,
            InvoiceItemsRelationManager::class,
        ] as $component) {
            $name = collect(explode('\\', $component))
                ->map(fn (string $part): string => Str::kebab($part))
                ->implode('.');

            Livewire::component($name, $component);
        }
    }
}
