# Changelog

All notable changes to the Wire ecosystem will be documented in this file.

## [0.1.0] – 2026-04-18

### Added
- **Monorepo structure** with three packages: `wire-core`, `wire-forms`, `wire-table`.
- `wire-core` package with shared traits, actions, modals, notifications, icons, colors.
- `wire-forms` package with 20+ field types, layout components, standalone form support.
- GitHub Actions CI (tests matrix PHP 8.2/8.3/8.4 × Laravel 10/11/12, PHPStan, Pint).
- Monorepo split workflow for per-package releases.
- ADR documentation for architectural decisions.

### Breaking Changes

#### Namespace Changes

The following classes have moved to new namespaces. Update your `use` statements:

| Before | After |
|--------|-------|
| `NyonCode\WireTable\Actions\Action` | `NyonCode\WireCore\Actions\Action` |
| `NyonCode\WireTable\Actions\BulkAction` | `NyonCode\WireCore\Actions\BulkAction` |
| `NyonCode\WireTable\Actions\HeaderAction` | `NyonCode\WireCore\Actions\HeaderAction` |
| `NyonCode\WireTable\Actions\ActionGroup` | `NyonCode\WireCore\Actions\ActionGroup` |
| `NyonCode\WireTable\Actions\ActionHalt` | `NyonCode\WireCore\Actions\ActionHalt` |
| `NyonCode\WireTable\Actions\DeleteAction` | `NyonCode\WireCore\Actions\DeleteAction` |
| `NyonCode\WireTable\Actions\DeleteBulkAction` | `NyonCode\WireCore\Actions\DeleteBulkAction` |
| `NyonCode\WireTable\Actions\EditAction` | `NyonCode\WireCore\Actions\EditAction` |
| `NyonCode\WireTable\Actions\ViewAction` | `NyonCode\WireCore\Actions\ViewAction` |
| `NyonCode\WireTable\Actions\ModalStep` | `NyonCode\WireCore\Actions\ModalStep` |
| `NyonCode\WireTable\Actions\ModalFooterAction` | `NyonCode\WireCore\Actions\ModalFooterAction` |
| `NyonCode\WireTable\Notifications\TableNotification` | `NyonCode\WireCore\Notifications\TableNotification` |
| `NyonCode\WireTable\Notifications\TableNotificationManager` | `NyonCode\WireCore\Notifications\TableNotificationManager` |
| `NyonCode\WireTable\Notifications\Contracts\NotificationDriver` | `NyonCode\WireCore\Notifications\Contracts\NotificationDriver` |
| `NyonCode\WireTable\Notifications\Drivers\SessionDriver` | `NyonCode\WireCore\Notifications\Drivers\SessionDriver` |
| `NyonCode\WireTable\Notifications\Drivers\LivewireEventDriver` | `NyonCode\WireCore\Notifications\Drivers\LivewireEventDriver` |
| `NyonCode\WireTable\Notifications\Drivers\FlasherDriver` | `NyonCode\WireCore\Notifications\Drivers\FlasherDriver` |
| `NyonCode\WireTable\Forms\Fields\Field` | `NyonCode\WireForms\Components\Field` |
| `NyonCode\WireTable\Forms\Fields\TextInput` | `NyonCode\WireForms\Components\TextInput` |
| `NyonCode\WireTable\Forms\Fields\Textarea` | `NyonCode\WireForms\Components\Textarea` |
| `NyonCode\WireTable\Forms\Fields\Select` | `NyonCode\WireForms\Components\Select` |
| `NyonCode\WireTable\Forms\Fields\Checkbox` | `NyonCode\WireForms\Components\Checkbox` |
| `NyonCode\WireTable\Forms\Fields\CheckboxList` | `NyonCode\WireForms\Components\CheckboxList` |
| `NyonCode\WireTable\Forms\Fields\Radio` | `NyonCode\WireForms\Components\Radio` |
| `NyonCode\WireTable\Forms\Fields\Toggle` | `NyonCode\WireForms\Components\Toggle` |
| `NyonCode\WireTable\Forms\Fields\DatePicker` | `NyonCode\WireForms\Components\DateTimePicker` (use `->asDate()`) |
| `NyonCode\WireTable\Forms\Fields\DateTimePicker` | `NyonCode\WireForms\Components\DateTimePicker` |
| `NyonCode\WireTable\Forms\Fields\TimePicker` | `NyonCode\WireForms\Components\DateTimePicker` (use `->asTime()`) |
| `NyonCode\WireTable\Forms\Fields\ColorPicker` | `NyonCode\WireForms\Components\ColorPicker` |
| `NyonCode\WireTable\Forms\Fields\FileUpload` | `NyonCode\WireForms\Components\FileUpload` |
| `NyonCode\WireTable\Forms\Fields\RichEditor` | `NyonCode\WireForms\Components\RichEditor` |
| `NyonCode\WireTable\Forms\Fields\Hidden` | `NyonCode\WireForms\Components\Hidden` |
| `NyonCode\WireTable\Forms\Fields\Section` | `NyonCode\WireForms\Components\Layout\Section` |
| `NyonCode\WireTable\Forms\Fields\Fieldset` | `NyonCode\WireForms\Components\Layout\Fieldset` |
| `NyonCode\WireTable\Forms\Fields\Grid` | `NyonCode\WireForms\Components\Layout\Grid` |
| `NyonCode\WireTable\Forms\Fields\Placeholder` | `NyonCode\WireForms\Components\Display\Placeholder` |
| `NyonCode\WireTable\Forms\Fields\Alert` | `NyonCode\WireForms\Components\Display\Alert` |
| `NyonCode\WireTable\Forms\Fields\Html` | `NyonCode\WireForms\Components\Display\Html` |
| `NyonCode\WireTable\Forms\Fields\ViewField` | `NyonCode\WireForms\Components\Display\ViewField` |

#### Composer Changes
- `nyoncode/wire-table` now requires `nyoncode/wire-core` and `nyoncode/wire-forms`.
- `nyoncode/engine-core` dependency removed (absorbed into `wire-core`).

#### Config Changes
- Notification driver config keys in `wire-table.php` now reference `NyonCode\WireCore\Notifications\Drivers\*`.

### Migration Guide

1. Update `composer.json`:
   ```bash
   composer require nyoncode/wire-table:^0.1
   ```
   This will automatically install `wire-core` and `wire-forms`.

2. Find and replace namespaces in your codebase using the table above. Most common replacements:
   ```
   NyonCode\WireTable\Actions\ → NyonCode\WireCore\Actions\
   NyonCode\WireTable\Forms\Fields\ → NyonCode\WireForms\Components\
   NyonCode\WireTable\Notifications\ → NyonCode\WireCore\Notifications\
   ```

3. Layout components moved to sub-namespace:
   ```
   NyonCode\WireForms\Components\Layout\Section
   NyonCode\WireForms\Components\Layout\Grid
   NyonCode\WireForms\Components\Layout\Fieldset
   ```

4. Display components moved to sub-namespace:
   ```
   NyonCode\WireForms\Components\Display\Alert
   NyonCode\WireForms\Components\Display\Html
   NyonCode\WireForms\Components\Display\Placeholder
   NyonCode\WireForms\Components\Display\ViewField
   ```

5. DatePicker/TimePicker unified into `DateTimePicker`:
   ```php
   // Before:
   DatePicker::make('birth_date')
   TimePicker::make('start_time')

   // After:
   DateTimePicker::make('birth_date')->asDate()
   DateTimePicker::make('start_time')->asTime()
   ```

6. Blade render syntax changed:
   ```blade
   {{-- Before --}}
   {!! $this->getTable() !!}

   {{-- After --}}
   {{ $this->table }}
   ```

7. If you customized notification driver config, update class references to `NyonCode\WireCore\Notifications\Drivers\*`.
