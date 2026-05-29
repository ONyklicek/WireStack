# ADR 0010: Form Save Notifications Integration

## Status
Accepted

## Context
`Form::save()` should show success/error notifications after saving. The Notifications module lives in `wire-core`. Forms must not directly import Notifications classes.

## Decision
`SaveHandler` checks for `NotificationManager` via service container binding:

```php
protected function notifySuccess(Model $record): void
{
    if ($this->config->successMessage === null) {
        return;
    }

    if (! app()->bound(TableNotificationManager::class)) {
        return; // Notifications module unavailable — silent no-op
    }

    $manager = app(TableNotificationManager::class);
    $message = value($this->config->successMessage, $record);
    $manager->success($message);
}
```

### Default messages
- Create mode: `trans('wire-forms::messages.created')` → "Record created"
- Update mode: `trans('wire-forms::messages.updated')` → "Record saved"

### Disabling notifications
```php
$form->successMessage(null);
// or
$form->disableSuccessNotification();
```

### When NotificationManager is missing
If `wire-core` Notifications module is not registered (hypothetical future scenario where Notifications is extracted and not installed), `SaveHandler` silently skips notification. No error, no exception.

## Consequences
- **Good:** Forms package has zero direct imports from Notifications.
- **Good:** Graceful degradation when Notifications is unavailable.
- **Good:** Default success messages provide good UX out of the box.
- **Trade-off:** Slightly indirect — debugging notification issues requires understanding the container binding.
