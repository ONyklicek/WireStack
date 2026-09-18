## wire-suite

A meta-package plus one interactive command. `composer require nyoncode/wire-suite` brings core, forms,
tables, sortable, resources and the admin shell in one line; `php artisan wire:install` sets them up by
running **each package's own installer**, never a copy of it.

- **The installer never runs composer.** It lists what it found in the application, sets that up, and then
  prints the `composer require` lines for the modules that are *not* installed. An artisan command that
  shells out to composer runs inside the application composer is rewriting the autoloader of; the failures
  that follows cannot be debugged from a stack trace. So: when a part is missing, hand over the line to
  paste — do not add a shell-out.
- **Everything is offered pre-selected**, because an installer whose default is "nothing" makes the common
  case the tedious one. `--all` skips the question, `--dry-run` shows the plan without doing any of it, and
  `--no-interaction` is passed down to the installers it runs.
- **Two questions, not one.** A catalogue `Component` carries a `ComponentGroup`: `Stack` is offered to
  everyone, `Module` only once `wire-admin` is part of the answer (a ready-made area renders inside the
  shell), and `Tooling` (`wire-boost`) is listed and never run. A new module entry in `Install\Catalogue`
  must say `group: ComponentGroup::Module`, or it lands in the first question.
- **It only runs what has something left to do.** `Install\Setup` reads each part's declared publish
  groups and skips the part when every destination is already there — `--force` overrides it. This is
  correctness, not speed: a migration shipped without a date prefix is stamped when the publish mapping is
  built, so re-publishing writes a *second* copy of one the application already has and `migrate` then
  fails. Never re-run a package installer to "make sure"; check first or pass `--force`.
- **The second half is setup, not installation.** `wire:install` runs the package installers and then
  works through `SetupStep`s: Fortify's config and features, the user model (`TwoFactorAuthenticatable`,
  `PasskeyAuthenticatable` + `PasskeyUser` for the features switched on — written with
  `Foundation\Setup\ClassSource`, the one helper for editing an application class), teams, roles,
  migrations, routes, the
  sign-in destination (Fortify's default `home` of `/home` pointed at the admin's root, which only the
  suite can do — it knows both Fortify's file and the panel prefix; an application's own `home` is left
  alone), the first administrator, `storage:link`, audit recording, stored notifications, the settings cache, the frontend
  build. A package contributes one
  with `SetupRegistry::instance()->register(...)` from its `registeredPackage()` hook — **inside the
  one it already has**, because the toolkit's lifecycle hooks assign rather than append and a second
  call drops the first. The contract is `WireCore\Foundation\Setup\Contracts\SetupStep`:
  `state()` may only look (it runs under `--dry-run`), `Blocked` is how a step says "not yet, and
  here is why" instead of being offered, and `apply()` asks through `SetupConsole` (`confirm`, `ask`,
  `choose`, and `select` for "which of these", which returns its default unattended) so the step is
  testable without a terminal. `package()` names the composer package the step belongs to: when that
  package was offered and unticked, its steps do not run; a package outside the catalogue always runs
  (that is how `RunMigrations` says "always", as `nyoncode/wire-suite`). **Never put step logic in
  wire-suite** — it belongs to the package that knows what a media disk or a super-admin role is.
- **A step wraps another package's installer rather than copying it** — `ConfigureFortify` calls
  `fortify:install`, `EnableRoles` calls `permission-extended:install` — and checks the command is
  registered (`Kernel::all()`), not that a class autoloads. A step that must run before `migrate`
  (it publishes a migration, or sets a switch a migration reads) sorts below `RunMigrations` — and
  below any step whose wrapped installer migrates on its own (`permission-extended:install` does,
  which is why teams sorts before roles). A switch a migration reads in this run is set with
  `config()->set()` as well as in the file.
- **Ask only through `SetupConsole`, never a Prompts function in a step.** `Artisan::call()` leaves
  Laravel Prompts' static output and interactivity as the called command set them — a buffer, or
  "nobody is here" after `--no-interaction` — and `CommandConsole` is what restores both before each
  question. **A file changed on disk is not the class this process loaded**: after
  `permission-extended:install` patches the user model, `Roles::available()` still says no until a new
  process; work that needs the patched class runs in one (`CreateFirstAdministrator` makes the super-admin via
  `wire:assign-role --super-admin` under `Process`).
- **A third-party config literal is edited only through `Foundation\Setup\ConfigFile`**, and only after
  `EnvFile` has no home for it. `set()` rewrites one single-line `'key' => value,` that occurs exactly once
  and returns false otherwise; never add a key or fall back to a wider regex — tell the user the line.
- **A failed installer fails the command.** `Artisan::call()`'s exit code is the run's exit code, and the
  parts that failed are named. Do not drop it.
- **Requiring the suite is not adopting the shell.** `wire-admin` still only renders a page once the
  application's own layout view says so, and naming the layout is a second, separate opt-in.
- Nothing in the stack requires `wire-suite`. It is a convenience for installing, never something to
  depend on from a package or to check for at runtime — ask `application-info` which packages are actually
  present instead.
