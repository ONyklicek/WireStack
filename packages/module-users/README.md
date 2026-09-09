# Wire Module Users

A ready-made users area for [Wire](https://github.com/nyoncode), installed as a
package — and the reference implementation of how a module ships as one.

```bash
composer require nyoncode/wire-module-users
php artisan wire-module-users:install
```

The application lists nothing and edits no config: the module registers itself
through the plugin manager during its provider's register phase, and the core
provider spreads its resources and its menu group into the registries that own
them.

## What it manages

Your user model, not one of its own — `config('wire-module-users.model')` points
at `App\Models\User`, and the three columns it touches are configuration too,
because `users` is the one table every application has changed.

| Screen | Notes |
| --- | --- |
| Users list, create, edit, view | The password field is empty on edit and means *keep the current one*; a typed one is hashed |
| Roles list, create, edit | Only where roles exist. Permissions are edited as names, so a wildcard is just a name |

Roles appear when `nyoncode/laravel-permission-extended` is installed **and** the
user model carries **its** `HasRoles` trait. Both are checked: the package can be
installed while the model never took the trait, and every role save would then
fail at its last step.

That package and not the `spatie/laravel-permission` it is built on — these
screens assume its wildcard matching, its super-admin gate and its
permission-change events, so a model on bare Spatie is deliberately not detected.
`'roles' => true` overrules the look.

## What it does not do

**Authorization.** Every check in this stack is `Gate::allows()`, which both
permission packages register themselves into, so wildcards (`invoices.*`) and a
super-admin bypass work without this module knowing they exist. Who may manage
users is your policy.

**Authentication.** Login, password reset and two-factor are Laravel's, through
Fortify or Breeze. `wire-admin` ships the card they render inside.

## Documentation

Full docs: [`docs/modules/users.md`](../../docs/modules/users.md)
([česky](../../docs/cs/modules/users.md)).

## License

MIT.
