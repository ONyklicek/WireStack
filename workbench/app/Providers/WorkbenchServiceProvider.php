<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Laravel\Fortify\Features;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroups;
use NyonCode\WireCore\Tours\Tour;
use NyonCode\WireCore\Tours\Tours;
use NyonCode\WireCore\Tours\TourStep;
use NyonCode\WireCore\Tours\TourWelcome;
use NyonCode\WireModuleSettings\Support\SettingsRegistry;
use Throwable;
use Workbench\App\Actions\Fortify\CreateNewUser;
use Workbench\App\Actions\Fortify\ResetUserPassword;
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

        // The demo is reachable through a Cloudflare tunnel, which ends HTTPS at
        // the edge and hands the request on to this server as plain HTTP. Laravel
        // builds every absolute URL — the stylesheet, Livewire's script, the
        // redirect `/previews/demo` answers with — from what it thinks the
        // request was, so without this the page arrives over https and asks for
        // its assets over http, which the browser blocks as mixed content: an
        // unstyled page with Livewire dead and nothing on the server side wrong.
        //
        // Loopback only, because that is where `cloudflared` connects from. A
        // phone on the LAN talks to this server directly and is not trusted, so
        // it cannot claim to be https or to be somebody else by sending the same
        // headers; a request with none of them — every CDP driver — is unchanged.
        TrustProxies::at(['127.0.0.1', '::1']);

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
        // Every screen Fortify can route, because this workbench is what the
        // docs are photographed from and a screen nobody can reach is a screen
        // the docs describe and nobody has seen.
        //
        // Registration included, which is a change of mind: it used to be off
        // "because that is what an admin panel looks like", and that is still
        // true of an admin panel — but it left `register.blade.php` as the one
        // shipped screen with no preview and no browser check. What the *links*
        // do when a feature is off is asserted from Pest instead
        // (`ScreensTest`), which is where a question about config belongs.
        config()->set('fortify.features', [
            Features::registration(),
            Features::resetPasswords(),
            // Verification, for the code flow below rather than for its own
            // sake: `codes.verify_email` routes nothing without it (ADR 0037),
            // and an unroutable screen is one no driver can drive.
            Features::emailVerification(),
            Features::twoFactorAuthentication(['confirm' => true]),
            // Passkeys, which Fortify routes through `laravel/passkeys`. On here
            // for the same reason as verification: the sign-in button and the
            // profile card are drawn from this switch, so with it off there is
            // nothing to preview and nothing for a driver to drive.
            Features::passkeys(),
        ]);

        // WebAuthn is bound to an origin, and the preview server is not the
        // `APP_URL` a testbench application defaults to — a ceremony started on
        // 127.0.0.1:8085 against a relying party of "localhost" is refused by the
        // browser before any of this repository's code runs. An application sets
        // these once, from its own domain; the workbench sets them from the host
        // it is actually served on.
        // `localhost`, not `127.0.0.1`: WebAuthn's secure-context exception is
        // written for the *name*, and Laravel's own client refuses the address
        // outright ("For local development, use localhost"). So the passkey
        // driver is the one that browses this workbench by name — everything
        // else can keep using the IP, and both origins are allowed so a session
        // started on either is accepted.
        config()->set('passkeys.relying_party_id', 'localhost');
        config()->set('passkeys.allowed_origins', ['http://localhost:8085', 'http://127.0.0.1:8085']);
        // The management routes sit behind `password.confirm` by default, which
        // is right for an application and would put a password screen in front of
        // every preview of the card.
        config()->set('fortify-options.passkeys.confirmPassword', false);
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

        // What an application's own `FortifyServiceProvider` binds — the two
        // actions behind screens this workbench routes. Without them the reset
        // and register screens render and every submit is a 500.
        $this->app->singleton(ResetsUserPasswords::class, ResetUserPassword::class);
        $this->app->singleton(CreatesNewUsers::class, CreateNewUser::class);

        // Where Fortify sends somebody who just signed in or registered. Its
        // default is `/home`, which nothing here routes, so a successful sign-in
        // landed on a 404. The workbench's own front door instead.
        config()->set('fortify.home', '/');

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

        $this->bootDatabaseSessions();
        $this->bootTours();

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

    /**
     * Two walkthroughs, so the CDP driver has something real to drive.
     *
     * The browser is the only place a tour can be checked at all: Pest sees the
     * markup and not whether the panel landed beside its element, whether a step
     * with no element was skipped, or whether anything ran on a phone.
     *
     * **`invoices-tour` has two steps that are deliberately unreachable** — one
     * whose element is not rendered, and one whose element is rendered and
     * hidden. The driver asserts both are skipped and neither is counted.
     *
     * The first of them:
     * `not-on-this-page` is a well-formed hook name that nothing renders, which
     * is what an application hiding a control looks like from a tour's side. The
     * driver asserts it is skipped *and* that the progress counter never counted
     * it.
     *
     * **`admin-zone-tour` runs in one zone and nowhere else.** Zone matching is
     * the part with the trap under it — `Zone::current()` answers
     * `livewire.update` on a round trip — so it is worth checking where a route
     * name is real, which is a browser.
     *
     * **`gated-tour` is the one nobody may see.** It sorts first, so it would
     * win every time if the permission were not consulted — which makes a
     * regression in tour authorization fail loudly here instead of leaking a
     * walkthrough of a screen somebody cannot reach.
     */
    /**
     * The tour a person meets on the demo dashboard.
     *
     * Everything the fixtures below are not: four steps that all exist, in the
     * order somebody new would want them, pointing only at controls that are on
     * screen before anything is clicked — a step whose element appears only in
     * edit mode would simply be skipped, and a tour that shrank as you watched
     * it would read as broken.
     *
     * One definition, used twice: registered while the demo cookie is set, and
     * handed to the ledger by `/previews/demo` so that opening the link always
     * starts it from the first step. The demo user is shared, so without that
     * the second person to open the link would see a dashboard and no tour.
     */
    public static function demoTour(): Tour
    {
        return Tour::make('dashboard-demo')
            ->zones('admin')
            ->resource('overview')
            ->page('index')
            ->steps([
                TourStep::make('admin-nav-item')
                    ->where('resource', 'overview')
                    ->heading('Your overview')
                    ->text('A dashboard is a page in the menu like any other. This one is the admin\'s landing page, so it sits first.')
                    ->placement('right-start'),

                TourStep::make('widget-grid')
                    ->heading('Real numbers')
                    ->text('Every card counts rows from the invoices and tasks in this demo. Change one there and the figure here follows.')
                    ->placement('top'),

                TourStep::make('widget-layout-edit')
                    ->heading('Make it yours')
                    ->text('Customise lets you drag cards into another order, make them wider or taller, and take some off — they wait in a tray until you want them back.')
                    ->placement('bottom-end'),

                TourStep::make('widget-layout-save-as')
                    ->heading('Keep more than one')
                    ->text('Save the arrangement under a name, and switch between your saved layouts from the menu that appears beside it.')
                    ->placement('bottom-end'),

                // On another page: "Next" goes to the invoices list and the tour
                // carries on there. The step a person reaches by leaving the
                // dashboard is the one that shows a tour is not a single screen.
                TourStep::make('table-search')
                    ->on('invoices')
                    ->heading('Every list works like this')
                    ->text('Search, filter and sort are the same on every table in the admin — this is the invoices list.')
                    ->placement('bottom-start'),
            ]);
    }

    protected function bootTours(): void
    {
        // The demo tour, behind a cookie of its own. Separate from the fixture
        // cookie below because the two audiences are: the fixtures exist for
        // `verify-tour` and are deliberately strange, and this one exists for a
        // person. Either would put a backdrop over every other driver that
        // visits the admin zone, so neither is registered unconditionally.
        if (isset($_COOKIE['wire-demo-tour'])) {
            $this->app->make(Tours::class)->register(self::demoTour());
        }

        // The welcome block, behind a cookie of its own for the reason the two
        // below have one, only more so: it puts a card and a backdrop over the
        // whole page *before* anything can be clicked, so a driver that did not
        // come for it would find the screen covered rather than merely dimmed.
        //
        // Two postponements rather than the configured three, so the driver can
        // spend the allowance and watch the tour give up without three round
        // trips of setup.
        if (isset($_COOKIE['wire-tour-welcome'])) {
            $this->app->make(Tours::class)->register(
                Tour::make('welcome-tour')
                    ->sort(-20)
                    ->resource('invoices')
                    ->page('index')
                    ->postpone(2)
                    ->welcome(
                        TourWelcome::make()
                            ->heading('Two minutes, and you will know your way around')
                            ->text('We will point at a couple of things and then leave you to it.')
                            ->later('Not just now'),
                    )
                    ->steps([
                        TourStep::make('admin-sidebar')
                            ->heading('Everything lives here')
                            ->text('The sidebar holds every area this application has.')
                            ->placement('right-start'),

                        TourStep::make('table-search')
                            ->heading('Find a row')
                            ->text('Search narrows the table as you type.'),
                    ]),
            );
        }

        // Behind a cookie the tour driver sets, and this is not fussiness. A tour
        // opens itself and draws a backdrop over the page, so registering one on
        // a screen unconditionally would break every other driver that visits it
        // — eleven of them use the invoices pages — and would break them by
        // covering the thing they came to click, which reads as the feature
        // under test being broken rather than the workbench.
        //
        // A cookie rather than a query parameter because it survives every
        // navigation the driver makes, `wire:navigate` included.
        //
        // `$_COOKIE` rather than `request()->cookie()`, and that was measured
        // rather than preferred: with the cookie demonstrably sent, the helper
        // answered null here and the tours never registered. A provider boots
        // early enough that the framework's cookie handling is not the thing to
        // ask; the superglobal is populated before PHP dispatches anything.
        if (! isset($_COOKIE['wire-tour-demo'])) {
            return;
        }

        Gate::define('tours.forbidden', static fn (): bool => false);

        $this->app->make(Tours::class)->register(
            Tour::make('gated-tour')
                ->permission('tours.forbidden')
                ->sort(-10)
                ->steps([
                    TourStep::make('admin-sidebar')
                        ->heading('You should never see this')
                        ->text('If this panel is on screen, tour authorization is broken.'),
                ]),

            Tour::make('admin-zone-tour')
                ->zones('admin')
                ->resource('invoices')
                ->sort(-5)
                ->steps([
                    TourStep::make('admin-sidebar')
                        ->heading('Admin zone only')
                        ->text('This tour runs in the admin zone and nowhere else.'),
                ]),

            Tour::make('invoices-tour')
                ->since('1')
                ->resource('invoices')
                ->page('index')
                ->steps([
                    TourStep::make('admin-sidebar')
                        ->heading('Everything lives here')
                        ->text('The sidebar holds every area this application has.')
                        ->placement('right-start'),

                    TourStep::make('not-on-this-page')
                        ->heading('Unreachable')
                        ->text('Nothing renders this hook, so this step must be skipped.'),

                    // In the document on every page and hidden on a desktop — the
                    // mobile sidebar's overlay, behind an `x-show`. Present is not
                    // showing: a tour that took `querySelector` at its word would
                    // pin the panel to a box of zero size.
                    TourStep::make('admin-sidebar-overlay')
                        ->heading('Hidden')
                        ->text('This element is rendered but hidden, so this step must be skipped.'),

                    TourStep::make('table-search')
                        ->heading('Find a row')
                        ->text('Type here to narrow the table down.')
                        ->placement('bottom-start'),
                ]),
        );
    }

    /**
     * Keep sessions in the database, the way Laravel has since 11 — and the way
     * an application has to for the profile's browser-sessions card to list
     * anything, since no other driver records whose a session is.
     *
     * Guarded by the table rather than set outright: a workbench database built
     * before this migration would otherwise fail on every request, and a preview
     * server that 500s wholesale is the hardest kind of stale skeleton to read.
     * Falling back means the card shows its own explanation instead.
     */
    protected function bootDatabaseSessions(): void
    {
        try {
            if (Schema::hasTable('sessions')) {
                config()->set('session.driver', 'database');
            }
        } catch (Throwable) {
            // No database yet (a build step, a fresh clone): leave the default.
        }
    }
}
