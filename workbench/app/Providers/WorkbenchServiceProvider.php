<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroups;
use NyonCode\WireModuleSettings\Support\SettingsRegistry;
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
use Workbench\App\Models\Team;
use Workbench\App\Models\User;
use Workbench\App\Modules\BillingModule;
use Workbench\App\Modules\OperationsModule;
use Workbench\App\Settings\BrandingSettings;
use Workbench\App\Settings\MailSettings;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // A cache store that survives a request, which the halt modal needs: it
        // parks its form there between the render that raised it and the one
        // that confirms it. Testbench defaults to the `database` store, whose
        // table the workbench has no reason to migrate — so a halt form would
        // come back without its fields on the preview and nowhere else.
        config()->set('cache.default', 'file');

        // The customisable-dashboard preview has to survive a reload, and the
        // shipped default is `null` — nothing persisted, which is the right
        // default for a framework and the wrong one for showing what saving a
        // layout does. Session rather than database: no migration to run before
        // a preview works.
        config()->set('wire-core.preferences.default', 'session');
        config()->set('wire-core.preferences.guest', 'session');

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

        // The optional halves of the users module, all switched on, because the
        // workbench stands in for the application that has them: an avatar
        // column (see the migration), Fortify with its two-factor feature
        // enabled, and teams over the permission package. Each is `auto` in the
        // package and would be silently absent here otherwise, which is exactly
        // the preview nobody can check.
        //
        // In `register()`, not `boot()`, and that is load-bearing for three of
        // these four. The users module decides **at boot** whether roles exist
        // (which is what registers `RoleResource` and puts it in the menu) and
        // whether teams do (which pushes its middleware onto the `web` group and
        // its switcher into the chrome) — and a discovered package boots before
        // this provider does. Set in `boot()`, the answers were read against a
        // `App\Models\User` that does not exist here, and the screens were
        // silently absent.
        //
        // An application reads all of this from config files, which are in place
        // before any provider runs at all. This is the workbench paying for not
        // having one.
        config()->set('wire-module-users.model', User::class);
        config()->set('wire-module-users.profile.delete_account', true);
        // Two-factor for the users module's profile card, password resets for the
        // auth module's screens. Registration stays off, which is what an admin
        // panel looks like — and is also what `verify-auth-screens.mjs` asserts
        // the login screen does about a link it must not draw.
        config()->set('fortify.features', [
            Features::resetPasswords(),
            // Verification, for the code flow below rather than for its own
            // sake: `codes.verify_email` routes nothing without it (ADR 0037),
            // and an unroutable screen is one no driver can drive.
            Features::emailVerification(),
            Features::twoFactorAuthentication(['confirm' => true]),
        ]);
        // Every code flow on, because the workbench stands in for the
        // application that turned them on: four screens that are otherwise
        // unroutable — and therefore unpreviewable, and unverifiable in a
        // browser. Registration stays off above; these are not registration.
        //
        // The second factor needs Fortify's two-factor feature, which is already
        // in the list above (ADR 0037 §5). Note what this makes the workbench's
        // sign-in: password, then a mailed code, for a demo user with no
        // authenticator app.
        config()->set('wire-module-auth.codes.login', true);
        config()->set('wire-module-auth.codes.second_factor', true);
        config()->set('wire-module-auth.codes.verify_email', true);
        config()->set('wire-module-auth.codes.reset_password', true);

        config()->set('permission.teams', true);
        config()->set('wire-module-users.teams.model', Team::class);
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

        // Read at render, so `boot()` is early enough for this one.
        config()->set('wire-module-settings.groups', [BrandingSettings::class]);

        // The other half of the same screen, arriving the way a package ships
        // one: registered from a provider rather than listed in the
        // application's config. The workbench is the application here, so this
        // stands in for a package — what it exercises is that both sources reach
        // one switcher, which is the part only a rendered screen can show.
        SettingsRegistry::instance()->register(MailSettings::class);

        // Stored notifications, so the bell and the notification list have
        // something to show rather than being permanently empty demos.
        // Root-relative, because the testbench skeleton's own .env pins
        // APP_URL to `http://localhost` and Laravel builds a stored file's URL
        // from it — so on the preview server every <img> pointed at a host
        // nothing is listening on. The markup was right and the images were
        // blank, which is the kind of failure only a browser notices.
        //
        // A real application sets APP_URL and needs none of this.
        config()->set('filesystems.disks.public.url', '/storage');

        // A logo, inlined rather than published into the skeleton's public
        // directory: the point of the workbench is to show the shell with a real
        // brand in it, and a data URI is the shortest path to one that survives
        // a fresh checkout with no build step.
        config()->set('wire-admin.brand.name', 'Wire Workbench');
        config()->set('wire-admin.brand.logo', 'data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 132 28\'><rect width=\'28\' height=\'28\' rx=\'8\' fill=\'%232563eb\'/><path d=\'M8 9h3l2 7 2-7h3l2 7 2-7h3l-3.5 11h-3l-2-6.5-2 6.5h-3z\' fill=\'white\'/><text x=\'36\' y=\'20\' font-family=\'ui-sans-serif,system-ui,sans-serif\' font-size=\'15\' font-weight=\'650\' fill=\'%230f172a\'>Workbench</text></svg>');
        config()->set('wire-admin.brand.logo_dark', 'data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 132 28\'><rect width=\'28\' height=\'28\' rx=\'8\' fill=\'%232563eb\'/><path d=\'M8 9h3l2 7 2-7h3l2 7 2-7h3l-3.5 11h-3l-2-6.5-2 6.5h-3z\' fill=\'white\'/><text x=\'36\' y=\'20\' font-family=\'ui-sans-serif,system-ui,sans-serif\' font-size=\'15\' font-weight=\'650\' fill=\'%23f8fafc\'>Workbench</text></svg>');
        config()->set('wire-admin.brand.mark', 'data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 28 28\'><rect width=\'28\' height=\'28\' rx=\'8\' fill=\'%232563eb\'/><path d=\'M8 9h3l2 7 2-7h3l2 7 2-7h3l-3.5 11h-3l-2-6.5-2 6.5h-3z\' fill=\'white\'/></svg>');

        // `broadcast` alongside them so the bell carries its live bridge in the
        // previews: the toast for the tab that asked, the row for later, and the
        // nudge for every other tab. Without a broadcaster configured the event
        // goes to Laravel's default connection and costs nothing — which is also
        // what an application that has not set one up gets.
        config()->set('wire-core.notifications.default', ['session', 'database', 'broadcast']);
        config()->set('wire-core.audit.enabled', true);
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
