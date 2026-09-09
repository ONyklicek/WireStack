---
order: 40
summary: "Tři místa volání — uvnitř akce, v obyčejné Livewire komponentě a kolem uložení formuláře — a co každé z nich dělá samo."
---

# Odkud je posílat

Většina notifikací se nepíše ručně: akce ohlásí vlastní úspěch, formulář ohlásí
uložení a tabulka ohlásí hromadný běh. Tahle stránka je o tom, co každé z těch
míst dělá samo a jak z něj místo toho říct něco jiného.

## Použití v akcích

```php
Action::make('save')
    ->action(function ($record, Action $action) {
        $record->save();
        $action->sendSuccessNotification();
    })
    ->successNotification('Saved!');

// Vlastní notifikace z akce
$action->sendNotification(
    Notification::success('Done')
        ->title('Processed')
        ->duration(3000)
        ->icon('check')
);
```

## Použití v komponentách

```php
use NyonCode\WireCore\Notifications\Concerns\InteractsWithNotifications;
use NyonCode\WireCore\Notifications\Notification;

class MyComponent extends Component
{
    use InteractsWithNotifications;

    public function save(): void
    {
        // ... save logika

        // Typové zkratky (berou řetězec zprávy)
        $this->notifySuccess('Record saved');
        $this->notifyError('Save failed');
        $this->notifyWarning('Careful');
        $this->notifyInfo('Heads up');

        // Nebo odeslat plně sestavenou Notification
        $this->notify(
            Notification::success('Record saved')->title('Done')->duration(5000)
        );
    }
}
```

## Použití ve formulářích

Formuláře automaticky odešlou úspěšnou notifikaci po `save()`, pokud není vypnuta:

```php
Form::make()
    ->schema([...])
    ->model(User::class)
    ->successMessage('User saved!')          // vlastní zpráva
    ->save();

// Vypnout
Form::make()
    ->schema([...])
    ->disableSuccessNotification()
    ->save();
```

## Související

- [Notifikace](index.md) — builder, který tahle místa volají
- [Akce: Lifecycle](../actions/lifecycle.md) — kde vzniká vlastní hláška akce
- [Save lifecycle](../../forms/save-lifecycle.md) — notifikační krok formuláře
- [Notifikace v tabulce](../../table/notifications.md) — výchozí hlášky tabulky
