---
order: 50
summary: "Two plugins written end to end — an action preset and a form audit — and how to test one without booting an application around it."
---

# Examples And Testing

Two finished plugins, each solving a problem an application actually has, and
then the part that keeps them working: a plugin is an ordinary object with a
register and a boot, so testing one is closer to a unit test than to a feature
test.

## Practical Example: Action Preset

Actions are macroable through their base action class. This plugin adds a reusable admin-only preset.

```php
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\PluginManager;

final class AdminActionPlugin implements Plugin
{
    public function getId(): string
    {
        return 'admin-actions';
    }

    public function register(PluginManager $manager): void
    {
        //
    }

    public function boot(PluginManager $manager): void
    {
        Action::macro('adminOnly', function (): static {
            return $this->authorizeUsing(
                fn ($user) => method_exists($user, 'isAdmin') && $user->isAdmin()
            );
        });
    }
}
```

Use it on an action:

```php
Action::make('impersonate')
    ->label('Impersonate')
    ->adminOnly()
    ->requiresConfirmation()
    ->action(fn (User $record) => auth()->user()->impersonate($record));
```

## Practical Example: Form Audit

This plugin adds a small audit hook around form persistence.

```php
use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\PluginManager;

final class FormAuditPlugin implements Plugin
{
    public function getId(): string
    {
        return 'form-audit';
    }

    public function register(PluginManager $manager): void
    {
        $manager->hook('form.saving', function (array $payload): array {
            $payload['data']['updated_by'] ??= auth()->id();

            return $payload;
        });

        $manager->hook('form.saved', function (array $payload): void {
            logger()->info('Form saved', [
                'record' => $payload['record'] ?? null,
            ]);
        }, priority: 100);
    }

    public function boot(PluginManager $manager): void
    {
        //
    }
}
```

`form.saving` can modify the data that will be persisted. `form.saved` is observational in the current runtime because the save handler does not consume its returned payload.

## Testing Plugins

Test plugin behavior by instantiating `PluginManager` directly.

```php
use NyonCode\WireCore\Core\Plugin\PluginManager;

it('registers tenant plugin', function () {
    $manager = new PluginManager();
    $plugin = new TenantPlugin();

    $manager->register($plugin);

    expect($manager->has('tenant'))->toBeTrue();
});
```

For macros, boot the plugin first:

```php
it('adds tenant table macro', function () {
    $manager = new PluginManager();
    $plugin = new TenantPlugin();

    $manager->register($plugin);
    $manager->boot();

    expect(\NyonCode\WireTable\Table::hasMacro('tenantScoped'))->toBeTrue();
});
```

For hook behavior, run the hook with the payload your runtime code emits:

```php
it('adds updated_by before form save', function () {
    $manager = new PluginManager();
    $plugin = new FormAuditPlugin();

    $manager->register($plugin);

    $payload = $manager->runHook('form.saving', [
        'data' => ['name' => 'Jane'],
    ]);

    expect($payload['data'])->toHaveKey('updated_by');
});
```

## Related

- [Plugins](index.md) — the contract these implement
- [Hooks](hooks.md) and [Extending Surfaces](extending.md) — what they use
- [Testing](../../start/testing.md) — the three levels of test in this repository
- [Actions](../actions/index.md) — the surface the first example presets
