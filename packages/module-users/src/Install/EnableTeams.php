<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Install;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Foundation\Setup\ConfigFile;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupStep;
use NyonCode\WireCore\Foundation\Setup\EnvFile;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupState;
use NyonCode\WireModuleUsers\Support\Roles;
use NyonCode\WireModuleUsers\Support\Teams;
use Throwable;

/**
 * Teams, which is a switch in the permission layer rather than a feature here.
 *
 * A role scoped to a team is `spatie/laravel-permission`'s `teams` option: it
 * puts the team column on both pivot tables and into the permission cache key,
 * and this module follows it rather than keeping a switch of its own. That is
 * what makes the panel and the authorization it draws agree about whether teams
 * exist.
 *
 * ## Why it has to be asked before the tables are made
 *
 * The team column is added by Spatie's own migration, which reads
 * `config('permission.teams')` **as it runs**. Answer this after `migrate` and
 * the config says teams while the tables say otherwise — roles save against a
 * column that is not there, and the error names a pivot table nobody has heard
 * of. So this sits before the migrations, and an application whose permission
 * tables already exist is told to roll them back rather than quietly switched.
 *
 * **Before the roles step too**, which is the order that is not obvious.
 * `permission-extended:install` runs `migrate` itself as its last act, so a
 * teams question asked after it always found the tables made and could only
 * refuse. This step therefore publishes Spatie's config on its own when it is
 * not there yet — the same single `vendor:publish` that installer starts with,
 * which then finds the file and leaves it — and switches teams on in the
 * running process as well as in the file: the migration reads the config this
 * process loaded at boot, not the file on disk.
 *
 * ## "No" is an answer
 *
 * Tables made without a team column are an application that decided against
 * teams, and that is Done rather than Pending — otherwise every later run asks
 * again. Changing it afterwards is a migration somebody writes, and the summary
 * says so.
 *
 * ## It asks, and it does not guess
 *
 * Nothing here ships a teams table. An application with teams already has the
 * model and the relation; one without is not using this, and the honest default
 * for "does this application have teams" is no. Unattended, that is the answer.
 */
final readonly class EnableTeams implements SetupStep
{
    public function __construct(private EnvFile $env, private Kernel $artisan) {}

    public function label(): string
    {
        return 'Teams';
    }

    public function state(): SetupState
    {
        if (! $this->installed()) {
            return SetupState::Blocked;
        }

        if (Teams::available()) {
            return SetupState::Done;
        }

        return $this->migrated() ? SetupState::Done : SetupState::Pending;
    }

    public function summary(): string
    {
        if (! $this->installed()) {
            return 'run `composer require nyoncode/laravel-permission-extended` for roles scoped to teams';
        }

        if (Teams::available()) {
            return 'roles are scoped to `'.Teams::relation().'`, and the switcher is in the chrome';
        }

        if (! $this->migrated()) {
            return 'scope roles to teams, if this application has them';
        }

        // Made, and the switch is on: the column is there and only the model
        // the switcher lists is missing, which is the application's to write.
        return config('permission.teams')
            ? 'teams are on, and `'.(string) config('wire-module-users.teams.model').'` is not a class yet'
            : 'roles are not scoped to teams — the permission tables were made without the column';
    }

    public function apply(SetupConsole $console): SetupOutcome
    {
        if (! $console->isInteractive()) {
            return SetupOutcome::Skipped;
        }

        if (! $console->confirm('Does this application scope roles to teams?', false)) {
            return SetupOutcome::Skipped;
        }

        if ($this->migrated()) {
            $console->warn('The permission tables already exist without a team column. Roll them back, then run this again.');

            return SetupOutcome::Failed;
        }

        if (! $this->permission()->exists()) {
            $this->artisan->call('vendor:publish', [
                '--provider' => 'Spatie\\Permission\\PermissionServiceProvider',
                '--tag' => 'permission-config',
            ]);
        }

        if (! $this->permission()->set('teams', 'true')) {
            $console->warn('Could not switch `teams` on in '.$this->permission()->path().' — set it to true there by hand.');

            return SetupOutcome::Failed;
        }

        // The file is for the next process. This one — and the `migrate` the
        // roles step and the migrations step are about to run inside it — read
        // the config at boot, and would make the tables without the column.
        config()->set('permission.teams', true);

        $this->nameTheModel($console);

        $console->note('Teams are on. The team column goes onto the permission tables when they are migrated.');

        return SetupOutcome::Applied;
    }

    public function package(): string
    {
        return 'nyoncode/wire-module-users';
    }

    public function sort(): int
    {
        // Before the roles step, whose installer runs `migrate` itself, and so
        // before every migration that reads the switch.
        return 45;
    }

    /**
     * Ask which model a team is, and which relation reaches it.
     *
     * The model goes to `.env`, which is where the config already looks for it.
     * The relation has no env of its own, so it is written into the published
     * config where there is one — and left alone, with a line saying so, where
     * the module's config was never published.
     */
    private function nameTheModel(SetupConsole $console): void
    {
        $model = $console->ask('Which class is a team?', (string) config('wire-module-users.teams.model', 'App\\Models\\Team'));

        if (! class_exists($model)) {
            $console->warn($model.' does not exist yet — the switcher stays empty until it does.');
        }

        config()->set('wire-module-users.teams.model', $model);

        if (! $this->env->set('WIRE_USERS_TEAM_MODEL', $model)) {
            $console->warn('Could not write to .env — set WIRE_USERS_TEAM_MODEL='.$model.' there yourself.');
        }

        $relation = $console->ask('Which relation on the user reaches their teams?', Teams::relation());

        if ($relation === Teams::relation()) {
            return;
        }

        $users = ConfigFile::forApplication('wire-module-users');

        if (! $users->exists() || ! $users->set('relation', "'".$relation."'")) {
            $console->warn("Set `teams.relation` to '".$relation."' in config/wire-module-users.php yourself.");
        }
    }

    /**
     * Whether the permission layer is here to switch.
     *
     * Its installer being registered, as the roles step asks it: a package in
     * `vendor` whose provider is excluded from discovery has an autoloadable
     * trait and no config to publish.
     */
    private function installed(): bool
    {
        return Roles::installable($this->artisan);
    }

    private function permission(): ConfigFile
    {
        return ConfigFile::forApplication('permission');
    }

    /**
     * Whether the permission tables have already been made.
     *
     * Asked of the schema, and wrapped, because this runs before `migrate` on a
     * fresh application where there may be no connection to ask at all. No
     * connection is not a table, so the unreachable case answers no — and the
     * step then does the thing that is safe either way: it writes the switch
     * before anything has read it.
     */
    private function migrated(): bool
    {
        return class_exists('Spatie\\Permission\\Models\\Role')
            && $this->tableExists();
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable(
                (string) config('permission.table_names.roles', 'roles')
            );
        } catch (Throwable) {
            // No connection, no tables. A step that cannot reach the database
            // is not a step that has found one already migrated.
            return false;
        }
    }
}
