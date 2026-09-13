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
- **It only runs what has something left to do.** `Install\Setup` reads each part's declared publish
  groups and skips the part when every destination is already there — `--force` overrides it. This is
  correctness, not speed: a migration shipped without a date prefix is stamped when the publish mapping is
  built, so re-publishing writes a *second* copy of one the application already has and `migrate` then
  fails. Never re-run a package installer to "make sure"; check first or pass `--force`.
- **A failed installer fails the command.** `Artisan::call()`'s exit code is the run's exit code, and the
  parts that failed are named. Do not drop it.
- **Requiring the suite is not adopting the shell.** `wire-admin` still only renders a page once the
  application's own layout view says so, and naming the layout is a second, separate opt-in.
- Nothing in the stack requires `wire-suite`. It is a convenience for installing, never something to
  depend on from a package or to check for at runtime — ask `application-info` which packages are actually
  present instead.
