<?php

declare(strict_types=1);

use Spatie\Permission\PermissionServiceProvider;

/*
 * The permission tables, as an application gets them.
 *
 * `nyoncode/laravel-permission-extended` is the permission layer of this stack,
 * and it ships no tables of its own — it extends the behaviour on the user model
 * and leaves the schema to the `spatie/laravel-permission` it requires. So the
 * migration an application publishes is Spatie's, and this is that file rather
 * than a copy of it: a hundred lines transcribed into this repository would be a
 * hundred lines that drift the next time the package changes a pivot key.
 *
 * Located through the package's own provider, not a `../../../vendor` walk: that
 * finds the file wherever Composer put it, and fails loudly with a class name if
 * the package is not installed at all.
 *
 * It reads `config('permission.teams')` while it runs — and the workbench sets
 * that in `WorkbenchServiceProvider::register()`, before any migration — so the
 * schema it creates here is the team-scoped one: a `team_id` on `roles`, and a
 * `team_id` in the primary key of both pivots.
 *
 * Numbered before `create_users_table` so the users table exists first only by
 * accident of the skeleton; what actually matters is that it runs before the
 * seeder, which assigns roles.
 */
$stub = dirname((new ReflectionClass(PermissionServiceProvider::class))->getFileName(), 2)
    .'/database/migrations/create_permission_tables.php.stub';

return require $stub;
