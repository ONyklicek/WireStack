---
order: 50
summary: "Dva pluginy napsané od začátku do konce — preset akce a audit formuláře — a jak plugin otestovat, aniž bys kolem něj nabootoval aplikaci."
---

# Příklady a testování

Dva hotové pluginy, každý řešící problém, který aplikace opravdu má, a pak to, co
je udrží funkční: plugin je obyčejný objekt s registrací a bootem, takže jeho test
má blíž k unit testu než k feature testu.

## Praktický příklad: Preset akce

Akce jsou macroable přes svou základní action třídu. Tento plugin přidává znovupoužitelný admin-only preset.

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

Použijte ho na akci:

```php
Action::make('impersonate')
    ->label('Impersonate')
    ->adminOnly()
    ->requiresConfirmation()
    ->action(fn (User $record) => auth()->user()->impersonate($record));
```

## Praktický příklad: Audit formuláře

Tento plugin přidává malý audit hook kolem perzistence formuláře.

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

`form.saving` může upravit data, která se perzistují. `form.saved` je v aktuálním runtime observační, protože save handler nekonzumuje jeho vrácený payload.

## Testování pluginů

Testujte chování pluginu instancováním `PluginManager` přímo.

```php
use NyonCode\WireCore\Core\Plugin\PluginManager;

it('registers tenant plugin', function () {
    $manager = new PluginManager();
    $plugin = new TenantPlugin();

    $manager->register($plugin);

    expect($manager->has('tenant'))->toBeTrue();
});
```

Pro makra plugin nejdřív bootněte:

```php
it('adds tenant table macro', function () {
    $manager = new PluginManager();
    $plugin = new TenantPlugin();

    $manager->register($plugin);
    $manager->boot();

    expect(\NyonCode\WireTable\Table::hasMacro('tenantScoped'))->toBeTrue();
});
```

Pro chování hooku spusťte hook s payloadem, který váš runtime kód emituje:

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

## Související

- [Pluginy](index.md) — kontrakt, který tyhle implementují
- [Hooky](hooks.md) a [Rozšiřování povrchů](extending.md) — co používají
- [Testování](../../start/testing.md) — tři úrovně testů v tomhle repozitáři
- [Akce](../actions/index.md) — povrch, který první příklad přednastavuje
