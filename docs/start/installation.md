---
order: 10
summary: The whole stack in one require, and one interactive command that turns a clean Laravel into a working admin.
---

# Installing Wire

```bash
composer require nyoncode/wire-suite
php artisan wire:install
```

The first line brings core, forms, tables, sortable, resources and the admin
shell. The second sets them up — a wizard in numbered steps that asks what you
want, runs each chosen package's own installer rather than a copy of it, and then
sets up the application those packages need.

## What The Installer Does

```text
   ╭───────●
   ╰─────╮      WireStack
 ●───────╯      the whole stack, set up in one pass

 INFO  Step 1 — The framework.

 ┌ Which parts of the stack? ─────────────────────────────────────────────┐
 │ › ◼ Tables — Tables, columns, filters, exports, gestures.              │
 │   ◼ Admin shell — The layout and the sidebar your pages render inside. │
 └────────────────────────────────────────────────────────────────────────┘
  Space unticks one, enter confirms.

 INFO  Step 2 — Ready-made areas.

 ┌ Which of these should the panel have? ────────────────────────────────────────────────────────┐
 │ › ◼ Sign in — Login, password reset, verification and the two-factor challenge, over Fortify. │
 │   ◼ Users — User administration, with roles where the application has them.                   │
 │   ◻ Media library — Uploads, a browsable list and previews.                                   │
 └───────────────────────────────────────────────────────────────────────────────────────────────┘
  Space unticks one, enter confirms.

 INFO  Step 3 — Installing packages.

  Core — nyoncode/wire-core ....................... ALREADY DONE
  Forms — nyoncode/wire-forms ..................... ALREADY DONE
  Tables — nyoncode/wire-table ............................ DONE
  Resources & pages — nyoncode/wire-panels ...... NOTHING TO RUN
  Admin shell — nyoncode/wire-admin ....................... DONE
  Run with --force to set up the parts marked ALREADY DONE again.

 INFO  Available, not installed here

  Users — User administration, with roles where the application has them.
  composer require nyoncode/wire-module-users
  …
```

One line per part, ending in what happened to it — the same shape the setup
phase below uses. It was three lists before: everything found, then everything
already set up, then everything being run, which named most parts twice and some
of them three times in three different vocabularies.

**Two questions, and the second only when it means something.** The stack comes
first — forms, tables, sortable, resources, the shell. The ready-made areas come
second, and only once the admin shell is part of the answer, either ticked now or
already set up from an earlier run: a module renders *inside* the panel, and
offering a users area to somebody who has not taken a panel is offering them a
screen with nowhere to appear. Untick the shell and the modules are listed as
`LEFT ALONE` rather than installed behind your back.

**The steps are counted, not promised.** Each stage prints `Step N — …` as it
starts, and the number is however many stages this run actually has: `--all`
asks nothing, so its first heading is the install, and `--dry-run` says "What
would be installed" instead of pretending to install it. A log that stops after
`Step 3` says where the run stopped.

Everything is offered pre-selected: an installer whose default is "nothing" makes
the common case the tedious one, so the question is a multiselect with every box
already ticked and space unticks what you do not want. `--all` skips the question
entirely, which is what a scripted setup wants, and `--dry-run` shows the whole
plan without doing any of it. The mark is drawn only where somebody is watching —
a banner in a deploy log is noise in the one place the output is read by a
machine.

**It only runs what has something left to do.** A part whose installer has
already written everything it publishes is named and skipped — and that is a
correctness rule, not a speed one. A migration this framework ships carries no
date prefix, so it is stamped with the time the publish mapping was built: the
destination path is different in every process, `vendor:publish` looks there,
finds nothing, and writes a *second* copy of a migration the application already
has. Two `create_wire_preferences_table` files, and `php artisan migrate` fails
on the second. So re-running the command after adding a module is safe, and it is
what the listing above shows: the module is set up, and everything already there
is named and left alone.

**A migration the application already has is left out.** Being on disk is not the
only way to have one: `schema:dump --prune` deletes the files and keeps the
tables, another package may make the same table, and `fortify:install` or the
permission installer publish their migrations whatever the application holds.
Every migration an installer writes during the run is compared, as it lands, with
the database and with every other migration the migrator will run — and one whose
tables and columns are all already there is removed, with a line saying so. A
partial overlap stays, and fails at `migrate` where you can see it.

**`--force` sets up every part regardless**, and hands `--force` down so each
installer publishes over what it wrote. That is the flag an upgrade wants, and
the one to reach for when a part is skipped that you wanted run.

**A failed installer fails the command.** The exit code each package's installer
returns is this command's exit code too, and the parts that failed are named — a
scripted setup that cannot see a failed publish is worse than no scripted setup.

**Each installer's own output is kept out of the way.** They print a banner, a
numbered step per publish tag, a tick per file and a list of next steps — a dozen
lines per package, which is what the listing above replaces. It is buffered and
shown in full the moment one of them fails, and with `-v` whenever you ask.

**`--no-interaction` reaches the installers it runs.** Each of them prompts
before touching a production application, and that prompt is drawn on this
command's own output from inside a running progress spinner, which is the one
place nobody can answer it. A run told not to ask means it all the way down.

**It never runs composer.** Offering a module that is not installed is the point
of that second list, and the answer is a line to paste. An artisan command that
shells out to composer runs *inside* the application it is about to change — the
autoloader in use is the one composer is rewriting — and the failure modes
(memory limits, plugins, a production image with no composer at all) are the ones
nobody can debug from a stack trace.

**A part whose provider is not loaded is reported, not fatal.** A class can be
autoloadable while its provider is absent — a `dont-discover` entry, a package
registered in one environment only — and calling a command that is not there
would otherwise abort the whole run.

## The Modules

Each is a separate `composer require`, because an application that wants users
and nothing else should not carry a media library:

| Module | Package |
| --- | --- |
| [Auth](../modules/auth.md) | `nyoncode/wire-module-auth` |
| [Users](../modules/users.md) | `nyoncode/wire-module-users` |
| [Settings](../modules/settings.md) | `nyoncode/wire-module-settings` |
| [Audit log](../modules/audit.md) | `nyoncode/wire-module-audit` |
| [Notifications](../modules/notifications.md) | `nyoncode/wire-module-notifications` |
| [Media](../modules/media.md) | `nyoncode/wire-module-media` |

Install one and run `php artisan wire:install` again; it registers itself, so
there is nothing to add to a config file, and the parts that were already set up
are left alone.

[`wire-boost`](../boost/guidelines-and-skills.md) is listed beside them and never
run: `wire-boost:install` asks which AI agents to configure, and that is not a
question this command should answer on anyone's behalf.

## Setting Up The Application

Installing a package and having a working application are two different things,
and until now only the first had a command. Every package installer knew what was
still missing and said so — "Run: php artisan migrate", "name an ability in
`wire-module-users.permissions`", "`wire-core.audit.enabled` is off — nothing is
being recorded yet" — nine such lines across seven packages, each a diagnosis
with nothing behind it.

So the second half of `wire:install` works through them, one at a time:

```text
 INFO  Step 4 — Setting up this application.

  Fortify .... publish its config and provider, or the sign-in screens have no routes   WOULD RUN
  Teams ............................ scope roles to teams, if this application has them   WOULD RUN
  Roles & permissions ........ publish the permission config and patch your user model   WOULD RUN
  Database tables ..................... run 2 pending migrations   WOULD RUN
  Routes ........................ no routes/web.php to add them to   WAITING
  First administrator . an account already exists, so you can sign in   DONE
  Media links ...................... public/storage is already linked   DONE
  Audit recording ........................ changes are being recorded   DONE
  Stored notifications ............... notifications are being stored   DONE
  Settings cache ................................... cached in `file`   DONE
  Frontend build ...... no package.json, so there is nothing to build   DONE
```

| Step | What it does, and why it is not a footnote |
| --- | --- |
| [Fortify](../modules/auth.md) | Runs `fortify:install`, then asks which sign-in features to have — registration, password reset, e-mail verification, two-factor, passkeys — ticked as the published config has them, and comments or uncomments each in `config/fortify.php`, options block and all. Without it the sign-in screens are registered and their routes are not: the login page is a 404 |
| [Teams](../modules/teams-and-two-factor.md#switching-on-teams) | Asks whether roles are scoped to teams; on yes, publishes the permission config if it is not there yet, sets `permission.teams` to `true`, writes `WIRE_USERS_TEAM_MODEL` and, if you name a different one, the relation. Asked before roles on purpose — the roles installer ends in a `migrate` of its own, and Spatie's migration reads `permission.teams` as it runs. Tables already made without teams count as "no", so it is not asked again |
| [Roles & permissions](../modules/teams-and-two-factor.md) | Runs `permission-extended:install`, which publishes the permission config and migration, migrates them and puts `HasRoles` on your user model. Spatie's migration is left out where the permission tables already exist. Until then the role screens are simply absent. Needs `nyoncode/laravel-permission-extended`, and says so when it is missing |
| Database tables | Runs the outstanding migrations. Three modules asked for this in their own installers and none could act on it |
| [Routes](../panels/modules.md) | Writes a `Route::wireResources()` group into `routes/web.php`, under a prefix and middleware you are asked for — both are PHP in that file, so an answer that is not a URL path or a middleware name is asked again. Without it every screen is a 404 |
| [First administrator](../modules/users.md) | Creates the account you sign in with, and asks whether it is the super-admin — which can do everything, in every team — where the application has roles. Nothing in the stack made one before; the answer was `php artisan tinker`. Only ever the *first*: every account after it is `php artisan wire:user`. The super-admin is given globally, so a first account that belongs to no team can still be one. Where roles were set up earlier in the same run, it is given by `wire:assign-role` in a fresh PHP process, because this one loaded the user model before it was patched |
| [Media links](../modules/media.md) | `storage:link`. Without it uploads work, thumbnails generate, and every image is a 404 that errors nowhere |
| [Audit recording](../core/audit.md) | Switches recording on, so the audit screen is not a view over an empty table |
| [Stored notifications](../modules/notifications.md) | Adds the `database` driver beside the toast, so the bell has a history to show |
| [Settings cache](../modules/settings.md) | Moves the settings cache off the database, where the lookup costs the query it was meant to save — offering only the memory stores that actually answer, because a fresh Laravel lists `redis` whether or not there is one |
| Frontend build | `npm install && npm run build`, so Tailwind compiles the classes in the packages' views — offered again when `resources/css/app.css` or the installed packages changed after the last build, since `laravel new` builds before this installer edits the stylesheet. Until it runs the shell has no width, no colour and no error |

**Detect, then ask, then act.** A step looks first and is offered only while it is
needed, so running the command twice is quiet. What is already true is named and
left alone.

**A part you unticked takes its steps with it.** Every step names the package it
belongs to, and a package that was offered in the first half and left out is not
asked about in the second — unticking the media module and then being asked to
link a public disk for it would be the installer asking a question it has already
been answered. *Offered* is the operative word: a module installed last month is
not a question this run, so its steps still run, and the migrations belong to no
part anyone can untick.

**A step that blows up is reported, not fatal.** `Blocked` covers what a step
could see coming; a migration that collides with one the application already ran
arrives as an exception instead. It is caught, named, and the run carries on to
the steps after it — the command still exits non-zero.

**A step that cannot run says so instead of being offered.** No database, no user
model, no `routes/web.php` — each is reported as something to go and fix rather
than a question whose "yes" ends in a stack trace. That is the `WAITING` column
above.

**Nothing is done without being asked**, and `--no-interaction` means it: a step
that can proceed on defaults does, and one that cannot — the first administrator
needs a password — declines and says why, rather than inventing one into a deploy
log.

### Another account, later

The step above makes one account and stops, because an installer that offered to
add another administrator on every run is one nobody could run twice safely. The
command is the other way in, and it is asked rather than offering:

```bash
php artisan wire:user
php artisan wire:user --name=Jane --email=jane@example.com --password=… --super-admin
php artisan wire:user --email=… --password=… --role=editor --role=support
```

Anything not given is asked for; anything not given **and** not askable stops the
command rather than being invented, because a generated password in a deploy log
is a credential in a log. Where the application has roles, it offers the ones it
has.

The address is held to the rule the user form holds it to — `admin` is not one —
and an address that already has an account is said as that. Typed, it is asked
again, three times at most; passed as `--email`, the command fails, because a
script wants an exit code rather than a question. The installer's step asks the
same way.

The password is held to `Password::defaults()` — the application's own policy,
and the rule the profile screen holds a new password to. Typed, it is asked for
twice, because it is hidden and a typo is an account nobody can sign in as;
passed as `--password`, a refused one fails the command.

**The super-admin is never one of them.** It can do everything, in every team, so
it is its own question: `--super-admin`, or — for the first account only, the one
somebody is letting themselves in with — a confirmation that says exactly that.
It is given globally, so it needs no team, and `--role=super-admin` is refused.
The same holds on the screens: the roles select never offers it, and saving a
user never takes it away.

An account that already exists gets its roles from `wire:assign-role`, which never
touches the name or the password:

```bash
php artisan wire:assign-role jane@example.com --super-admin
php artisan wire:assign-role jane@example.com --role=editor --role=support
php artisan wire:assign-role jane@example.com --role=editor --team=3
```

`--super-admin` asks before it acts wherever somebody can answer, and takes no
`--team`. Every other role, where roles are scoped to teams, goes in the team
named by `--team` or the account's current team; a team it is not a member of is
refused, because the role would sit where the switcher never offers it.

### Contributing a step

A package adds to that list by registering a step, the same way it registers
anything else. The installer never learns what the step is about:

```php
use NyonCode\WireCore\Foundation\Setup\SetupRegistry;

$packager->registeredPackage(fn () => SetupRegistry::instance()->register(WarmTheIndex::class));
```

The class implements `NyonCode\WireCore\Foundation\Setup\Contracts\SetupStep`:
`label()`, `state()` (`Done`, `Pending` or `Blocked`), `summary()`, `apply()`,
`package()` and `sort()`. `state()` may only look — it runs under `--dry-run` too
— and `apply()` asks through a `SetupConsole` rather than a command, which is what
lets a step be tested without a terminal. The console has `confirm()`, `ask()`,
`secret()`, `choose()` for "which one" and `select()` for "which of these"; each
returns its default when nobody is there to answer, so the default is whatever
the step decided was safe to leave as it is.

`package()` is the composer name the step belongs to, and it is what ties the
step to the tick box above: return your own package's name. `sort()` places it —
lower runs first, and the migrations run at `100`, so a step that publishes a
migration or sets a switch a migration reads sorts below that.

A step that changes a published config file uses
`NyonCode\WireCore\Foundation\Setup\ConfigFile`, and only for a value with no
`env()` behind it — `EnvFile` is the first answer. `set('teams', 'true')` rewrites
one single-line `'key' => value,` that occurs exactly once, and returns `false`
for anything else: a key in two sections, a value spread over lines, a file the
application has rewritten. A step that gets `false` tells the person which line
to change.

## What Is Left To You

```php
// routes/web.php, if you would rather write it yourself than be asked
Route::middleware(['web', 'auth'])->prefix('admin')->group(fn () => Route::wireResources());
```

## Every Command This Ships

`wire:install` runs the per-package installers for you; each is also callable on
its own, which is what an application adding one package later reaches for:

```bash
php artisan wire:install                      # the interactive setup over everything installed
php artisan wire:install --all --no-interaction  # …unattended: no question, no prompt underneath
php artisan wire:install --dry-run            # …the whole plan, changing nothing
php artisan wire:install --all --force        # …every part again, publishing over what it wrote
php artisan wire-core:install                 # one package at a time — config, assets, translations
php artisan wire-forms:install
php artisan wire-table:install
php artisan wire-sortable:install
php artisan wire-admin:install                # also writes the layout and the Tailwind @source line
php artisan wire-module-users:install         # …and one per installed module
php artisan wire:user                         # another account, any time — the installer only makes the first
php artisan wire:assign-role jane@example.com --role=editor  # roles for an account that already exists
php artisan wire:assign-role ada@example.com --role=admin --global  # an administrator of every team
php artisan wire:revoke-role ada@example.com --role=admin --global  # …and back
php artisan wire-boost:install --agent=claude # AI agent guidelines and the MCP entry
```

Two generators and three maintenance commands come with them:

| Command | What it does |
| --- | --- |
| `make:wire-dashboard` | A dashboard class in `app/Dashboards`, ready to name widgets — a dashboard is written, not shipped |
| `wire-core:audit-prune --days=` | Delete audit entries older than the retention window ([Audit Log](../core/audit.md)) |
| `wire-core:notifications-prune` | The same for stored notifications |
| `wire-module-media:thumbnails` | (Re)generate conversions for library files ([Media](../modules/media.md)) |
| `wire-module-media:usage` | Recompute where each file is used |
| `wire-boost:update` | Refresh the AI guidelines after an upgrade |
| `wire-boost:mcp` | Run the MCP server ([Boost](../boost/mcp-tools.md)) |

## Related

- [Getting Started](getting-started.md) — the manual path, package by package
- [The Admin Shell](../admin/overview.md) — the layout your pages render in
- [Modules](../panels/modules.md) — what a module is, and how to write your own
- [Ready-Made Modules](../modules/index.md) — what each installable area brings
