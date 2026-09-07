<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Workbench\App\Models\Document;
use Workbench\App\Models\GestureRow;
use Workbench\App\Models\Invoice;
use Workbench\App\Models\Task;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Two teams for the top-bar switcher: it only appears for somebody who
        // belongs to more than one, because a switcher with one option in it is
        // a control that cannot be used.
        $teams = collect(['Operations', 'Billing'])
            ->map(fn (string $name): Team => Team::query()->create(['name' => $name]));

        $amelia = User::query()->create([
            'name' => 'Amelia Stone',
            'email' => 'amelia@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'bio' => 'Owns product configuration, release notes, and customer rollouts.',
            'is_active' => true,
            'avatar_path' => $this->avatar('AS', '2563eb'),
        ]);

        $amelia->teams()->attach($teams->pluck('id'));

        $mason = User::query()->create([
            'name' => 'Mason Carter',
            'email' => 'mason@example.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'bio' => 'Coordinates approvals and internal QA for operations changes.',
            'is_active' => true,
            'avatar_path' => $this->avatar('MC', '059669'),
        ]);

        $sofia = User::query()->create([
            'name' => 'Sofia Bennett',
            'email' => 'sofia@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
            'bio' => 'Maintains customer-facing copy, presets, and workflows.',
            'is_active' => true,
            'avatar_path' => $this->avatar('SB', 'd97706'),
        ]);

        $ethan = User::query()->create([
            'name' => 'Ethan Brooks',
            'email' => 'ethan@example.com',
            'password' => Hash::make('password'),
            'role' => 'viewer',
            'bio' => 'Observes usage metrics and handles stakeholder reporting.',
            'is_active' => false,
        ]);

        $this->seedRoles([$amelia, $mason, $sofia, $ethan], (int) $teams->first()->getKey());

        $tasks = [
            ['title' => 'Draft onboarding checklist', 'status' => 'todo', 'priority' => 'high', 'owner_name' => 'Amelia Stone', 'sort_order' => 1, 'completed' => false, 'due_at' => now()->addDay()],
            ['title' => 'Review bulk action copy', 'status' => 'in_progress', 'priority' => 'medium', 'owner_name' => 'Sofia Bennett', 'sort_order' => 2, 'completed' => false, 'due_at' => now()->addDays(2)],
            ['title' => 'Verify audit event payloads', 'status' => 'blocked', 'priority' => 'high', 'owner_name' => 'Mason Carter', 'sort_order' => 3, 'completed' => false, 'due_at' => now()->addDays(4)],
            ['title' => 'Ship sortable migration notes', 'status' => 'review', 'priority' => 'medium', 'owner_name' => 'Amelia Stone', 'sort_order' => 4, 'completed' => false, 'due_at' => now()->addDays(5)],
            ['title' => 'Finalize filter defaults', 'status' => 'done', 'priority' => 'low', 'owner_name' => 'Ethan Brooks', 'sort_order' => 5, 'completed' => true, 'due_at' => now()->subDay()],
            ['title' => 'Publish docs landing page', 'status' => 'todo', 'priority' => 'high', 'owner_name' => 'Sofia Bennett', 'sort_order' => 6, 'completed' => false, 'due_at' => now()->addWeek()],
        ];

        foreach ($tasks as $task) {
            Task::query()->create($task);
        }

        $invoices = [
            [
                'number' => 'INV-1001',
                'customer' => 'Northwind Traders',
                'status' => 'paid',
                'issued_at' => now()->subDays(12),
                'items' => [
                    ['product' => 'Mechanical keyboard', 'quantity' => 2, 'unit_price' => 1200],
                    ['product' => 'Wireless mouse', 'quantity' => 3, 'unit_price' => 450],
                    ['product' => '27" monitor', 'quantity' => 1, 'unit_price' => 5600],
                ],
            ],
            [
                'number' => 'INV-1002',
                'customer' => 'Globex Corporation',
                'status' => 'pending',
                'issued_at' => now()->subDays(5),
                'items' => [
                    ['product' => 'Standing desk', 'quantity' => 1, 'unit_price' => 8900],
                    ['product' => 'Ergonomic chair', 'quantity' => 4, 'unit_price' => 2300],
                ],
            ],
            [
                'number' => 'INV-1003',
                'customer' => 'Acme Industries',
                'status' => 'overdue',
                'issued_at' => now()->subDays(33),
                'items' => [
                    ['product' => 'Software license', 'quantity' => 5, 'unit_price' => 990],
                    ['product' => 'Priority support', 'quantity' => 1, 'unit_price' => 3500],
                ],
            ],
        ];

        foreach ($invoices as $invoice) {
            $items = $invoice['items'];
            unset($invoice['items']);

            $model = Invoice::query()->create($invoice);

            foreach ($items as $item) {
                $model->items()->create([
                    ...$item,
                    'line_total' => $item['quantity'] * $item['unit_price'],
                ]);
            }
        }

        // Selection-gesture previews: enough rows that the document scrolls and
        // a 20-per-page variant spans two pages. Deterministic on purpose — the
        // CDP drivers address rows by name and key.
        $statuses = ['new', 'active', 'paused', 'archived'];

        foreach (range(1, 40) as $i) {
            GestureRow::query()->create([
                'name' => sprintf('Record %02d', $i),
                'status' => $statuses[($i - 1) % count($statuses)],
                'amount' => $i * 25,
            ]);
        }

        // Display/edit column surfaces: a stored CSS color, a score, a tag list,
        // an inline-editable boolean — plus two soft-deleted rows, which is what
        // gives TrashedFilter something to switch between. Deterministic: the
        // CDP drivers address these rows by title.
        $documents = [
            ['title' => 'Brand guidelines', 'brand_color' => '#6366f1', 'score' => 4.0, 'tags' => ['design', 'brand'], 'is_published' => true],
            ['title' => 'Release checklist', 'brand_color' => 'rebeccapurple', 'score' => 2.5, 'tags' => ['ops', 'release', 'internal', 'draft'], 'is_published' => false],
            ['title' => 'Pricing sheet', 'brand_color' => 'rgb(16 185 129)', 'score' => 5.0, 'tags' => ['sales'], 'is_published' => true],
            // A value that is not a CSS color: the swatch must refuse to draw it
            // rather than let it into a style attribute.
            ['title' => 'Support macros', 'brand_color' => 'red; background-image: url(https://evil.test/x)', 'score' => 3.0, 'tags' => [], 'is_published' => false],
        ];

        foreach ($documents as $document) {
            Document::query()->create($document);
        }

        $archived = [
            ['title' => 'Legacy onboarding', 'brand_color' => '#f59e0b', 'score' => 1.0, 'tags' => ['archive'], 'is_published' => false],
            ['title' => 'Old pricing sheet', 'brand_color' => '#ef4444', 'score' => 2.0, 'tags' => ['archive', 'sales'], 'is_published' => false],
        ];

        foreach ($archived as $document) {
            Document::query()->create($document)->delete();
        }

        // The module packages' own demo rows. Called from here rather than run
        // by hand, which is how it was: the browser drivers assert against this
        // data, so a seeder nothing invokes is a driver that passes on whatever
        // was left in the database from the last time somebody remembered.
        $this->call(ModuleDemoSeeder::class);
    }

    /**
     * Roles and permissions, so the module's access screens have something in
     * them.
     *
     * `permission.teams` is on in this workbench, which changes two things a
     * seeder has to know about:
     *
     *  - **the pivot's `team_id` is part of its primary key and is not
     *    nullable**, so a role cannot be assigned until the registrar has been
     *    told which team this is happening in. Web requests get that from the
     *    module's `SetCurrentTeam` middleware; a seeder has no request, so it
     *    says so itself;
     *  - **a role with a null `team_id` is global** — available in every team —
     *    which is what these four are, because a preview that hid half its roles
     *    behind the top-bar switcher would mostly demonstrate confusion.
     *
     * The permission names are wildcard-shaped on purpose: matching `invoices.*`
     * is what `nyoncode/laravel-permission-extended` adds over Spatie, and
     * `super-admin` is the role its gate looks for.
     *
     * @param  array<int, User>  $users
     */
    protected function seedRoles(array $users, int $teamId): void
    {
        // Who gets what is not decoration. `SignInDemoUser` signs in the *first*
        // seeded user, and one workbench route declares
        // `RoutePage::make(EditInvoice::class)->permission('invoices.update')` —
        // so the preview only demonstrates that a `can:` guard refuses if the
        // person browsing it does not hold that permission. A super-admin would
        // pass through `Gate::before` and a wildcard `invoices.*` would match it,
        // and either way the one screen proving route-level authorization would
        // quietly stop proving anything.
        //
        // So the demo user is a manager whose wildcard is over `tasks`, and
        // super-admin belongs to somebody else — which is also how an
        // application usually looks.
        $roles = [
            'manager' => ['tasks.*', 'invoices.view', 'users.view'],
            'super-admin' => [],                                    // the gate grants everything
            'editor' => ['invoices.view', 'invoices.update', 'tasks.view'],
            'viewer' => ['invoices.view', 'tasks.view'],
        ];

        $permissions = [
            'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete',
            'tasks.view', 'tasks.update',
            'users.view', 'users.update',
            'settings.update',
            ...array_merge(...array_values($roles)),
        ];

        foreach (array_unique($permissions) as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Every permission first, then one flush, then the roles. The registrar
        // caches the permission table on first read, and `syncPermissions()`
        // resolves names against that cache — so a permission created after the
        // cache was warmed is "no permission named `invoices.*`", which is a
        // confusing way to be told the order was wrong.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Created with no team on the registrar, so `team_id` stays null and the
        // role is global. Setting the team first would scope each of them to
        // Operations and leave Billing looking empty.
        foreach ($roles as $name => $granted) {
            Role::findOrCreate($name, 'web')->syncPermissions($granted);
        }

        // Now the assignment, which does need a team: the pivot row carries one,
        // in its primary key, not nullable.
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);

        foreach (array_combine(array_keys($roles), $users) as $role => $user) {
            $user->assignRole($role);
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    /**
     * A face, as a `data:` URI rather than a file.
     *
     * The avatar column holds whatever the application put there, and
     * `StoredFileUrlResolver` hands a complete source back untouched — a
     * Gravatar link, an identity provider's URL, or this. So the preview shows
     * real avatars in the list, in the top bar and on the profile without
     * shipping binary files that a fresh checkout would have to publish first.
     *
     * `%23` rather than `#`: this ends up inside an HTML attribute.
     */
    protected function avatar(string $initials, string $hex): string
    {
        return 'data:image/svg+xml;utf8,'
            ."<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'>"
            ."<rect width='64' height='64' rx='32' fill='%23{$hex}'/>"
            ."<text x='32' y='41' text-anchor='middle' font-family='ui-sans-serif,system-ui,sans-serif' "
            ."font-size='26' font-weight='600' fill='white'>{$initials}</text></svg>";
    }
}
