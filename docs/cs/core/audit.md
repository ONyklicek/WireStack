---
order: 70
---

# Audit Log

Wire Core obsahuje audit log pro záznam změn modelů a událostí spojených s tabulkami. Ukládá typ události, auditovaný záznam, uživatele, staré hodnoty, nové hodnoty, metadata a timestamp.

## Instalace

Audit log je součástí `wire-core`. Pokud jste nainstalovali `wire-table` nebo `wire-forms`, core je už nainstalován.

Publikujte config a migraci:

```bash
php artisan vendor:publish --tag=wire-core::config
php artisan vendor:publish --tag=wire-core::migrations
php artisan migrate
```

To je celé nastavení — balíček registruje audit event subscriber
automaticky a logger je řízen `wire-core.audit.enabled` (ve výchozím stavu
zapnuto). Pokud jste `AuditEventSubscriber` registrovali ručně v aplikačním
service provideru (setup před 1.7.1), můžete ten řádek odstranit;
subscription je idempotentní, takže jeho ponechání nezpůsobí dvojité logování.

## Zapnutí auditu na modelu

Přidejte `HasAuditable` na jakýkoli Eloquent model, který chcete sledovat.

```php
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Audit\Concerns\HasAuditable;

class Order extends Model
{
    use HasAuditable;
}
```

Trait zaznamenává Eloquent události `created`, `updated` a `deleted`.

## Vyloučení nebo zahrnutí sloupců

Použijte `getAuditExclude()` pro skrytí šumivých nebo citlivých sloupců pro jeden model.

```php
class Order extends Model
{
    use HasAuditable;

    protected function getAuditExclude(): array
    {
        return ['cached_total', 'internal_token'];
    }
}
```

Použijte `getAuditInclude()`, když chcete whitelist.

```php
protected function getAuditInclude(): array
{
    return ['status', 'total', 'assigned_user_id'];
}
```

Globální vyloučení žijí v `config/wire-core.php`:

```php
'audit' => [
    'exclude_columns' => [
        'password',
        'remember_token',
    ],
],
```

## Zobrazení audit záznamů

Každý auditovatelný model dostane relaci `audits()`.

```php
$entries = $order->audits()->latest()->get();
```

Audit záznamy vystavují helpery pro běžné dotazy:

```php
use NyonCode\WireCore\Audit\AuditEntry;

AuditEntry::forRecord($order)->get();
AuditEntry::forEvent('updated')->get();
AuditEntry::byUser($user->id)->get();
AuditEntry::olderThan(90)->delete();
```

Pro zobrazení řádkového audit trailu ve Wire Table přidejte vestavěnou akci:

```php
use NyonCode\WireCore\Audit\Actions\AuditTrailAction;

return $table
    ->model(Order::class)
    ->actions([
        AuditTrailAction::make(),
    ]);
```

Akce otevře slide-over s historií záznamu.

## Manuální audit události

Audit události můžete odesílat ručně pro operace, které neprocházejí auditovanou událostí modelu.

```php
use NyonCode\WireCore\Audit\Events\BulkActionExecuted;

event(new BulkActionExecuted(
    actionName: 'archive',
    modelType: Order::class,
    recordIds: $orders->modelKeys(),
    success: true,
    metadata: ['source' => 'orders-table'],
));
```

Pro aktualizaci jedné buňky:

```php
use NyonCode\WireCore\Audit\Events\InlineCellUpdated;

event(new InlineCellUpdated(
    modelType: Order::class,
    recordId: $order->id,
    column: 'status',
    oldValue: 'draft',
    newValue: 'approved',
));
```

## Dočasné vypnutí

Vypněte audit logging během importů, seederů nebo údržbových jobů:

```php
use NyonCode\WireCore\Audit\AuditLogger;

AuditLogger::withoutAuditing(function () {
    Order::query()->update(['synced_at' => now()]);
});
```

## Retence

Nastavte retenční období ve dnech:

```php
'audit' => [
    'retention_days' => 180,
],
```

Pak naplánujte přibalený prune příkaz:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('wire-core:audit-prune')->daily();
```

Spusťte ho manuálně s ad-hoc přepisem období:

```bash
php artisan wire-core:audit-prune --days=90
```

Bez nakonfigurovaného `retention_days` (a bez `--days`) příkaz varuje a
neprořeže nic. Programové prořezávání je stále dostupné přes
`app(AuditLogger::class)->prune(?int $days = null)`.

## Konfigurace

| Klíč | Výchozí | Popis |
|-----|---------|-------------|
| `enabled` | `true` | Globální on/off přepínač |
| `model` | `AuditEntry::class` | Vlastní model audit záznamu |
| `user_model` | `App\Models\User` | Model uživatele pro relaci `user()` |
| `events` | `null` | `null` loguje všechny podporované události; pole loguje jen vybrané typy událostí |
| `exclude_columns` | `password`, `remember_token` | Globální vyloučení sloupců |
| `retention_days` | `null` | Počet dní, po které se záznamy uchovávají |

Podporované typy událostí jsou `created`, `updated`, `deleted`, `bulk_action` a `cell_updated`.

Kompletní referenci configu viz [Konfigurace](../configuration.md).
