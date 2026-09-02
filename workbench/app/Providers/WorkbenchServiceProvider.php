<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroups;
use Workbench\App\Dashboards\OverviewDashboard;
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
use Workbench\App\Resources\DocumentResource;
use Workbench\App\Resources\InvoiceResource;
use Workbench\App\Resources\TaskResource;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // The owner layer, exercised on a real entity — V2.3's own gate before
        // the API counted as finished. Declared in config the way an application
        // does, not registered by hand, so the config path is what the preview
        // proves.
        // Three of them, in an order the menu deliberately does not keep:
        // Documents is registered last and sorts first inside its group, so the
        // sidebar and this array disagree — which is what makes a rendered menu
        // provable rather than merely present.
        config()->set('wire-core.resources', [
            InvoiceResource::class,
            TaskResource::class,
            DocumentResource::class,
        ]);

        // Dashboards are their own registry and their own config key: they are a
        // different kind of thing that a menu happens to list beside resources.
        config()->set('wire-core.dashboards', [OverviewDashboard::class]);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // The menu's headings, declared where an application declares them —
        // no resource owns the group it sits in. Operations sorts first while
        // the Billing resource is registered first, so the sidebar and the
        // registration order disagree on purpose: a menu that ignored the
        // declared sort would render visibly wrong instead of identical.
        $this->app->make(NavigationGroups::class)->registerMany([
            // The dashboard's group, above both resource groups — a heading with
            // nothing in it but an entry that is not a resource.
            NavigationGroup::make('insights')
                ->label('Insights')
                ->icon('outline:chart-bar')
                ->sort(5),
            NavigationGroup::make('operations')
                ->label('Operations')
                ->icon('outline:wrench-screwdriver')
                ->sort(10),
            // The heading is not the key: the slug stays 'billing' whatever the
            // heading says, which is what keeps a translated menu keyed the same
            // way in every locale.
            NavigationGroup::make('billing')
                ->label('Billing & invoicing')
                ->icon('outline:banknotes')
                ->sort(20),
        ]);

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
