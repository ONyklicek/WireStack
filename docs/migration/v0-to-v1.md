# Migration Guide: v0 → v1.0

This guide covers migrating from the monolithic `nyoncode/wire-table` v0.x to the Wire Ecosystem v1.0 (three-package architecture).

For the complete v1.0 documentation, see the [Documentation Index](../index.md).

## 1. Update Composer

```bash
composer require nyoncode/wire-table:^1.0
```

This automatically installs `wire-core` and `wire-forms` as dependencies.

## 2. Namespace Changes

### Find and Replace

Run these replacements across your codebase:

```
NyonCode\WireTable\Actions\ → NyonCode\WireCore\Actions\
NyonCode\WireTable\Notifications\ → NyonCode\WireCore\Notifications\
NyonCode\WireTable\Forms\Fields\ → NyonCode\WireForms\Components\
```

### Full Mapping Table

#### Actions (→ wire-core)
| Before | After |
|--------|-------|
| `NyonCode\WireTable\Actions\Action` | `NyonCode\WireCore\Actions\Action` |
| `NyonCode\WireTable\Actions\BulkAction` | `NyonCode\WireCore\Actions\BulkAction` |
| `NyonCode\WireTable\Actions\HeaderAction` | `NyonCode\WireCore\Actions\HeaderAction` |
| `NyonCode\WireTable\Actions\ActionGroup` | `NyonCode\WireCore\Actions\ActionGroup` |
| `NyonCode\WireTable\Actions\DeleteAction` | `NyonCode\WireCore\Actions\DeleteAction` |
| `NyonCode\WireTable\Actions\DeleteBulkAction` | `NyonCode\WireCore\Actions\DeleteBulkAction` |
| `NyonCode\WireTable\Actions\EditAction` | `NyonCode\WireCore\Actions\EditAction` |
| `NyonCode\WireTable\Actions\ViewAction` | `NyonCode\WireCore\Actions\ViewAction` |
| `NyonCode\WireTable\Actions\ActionHalt` | `NyonCode\WireCore\Actions\ActionHalt` |
| `NyonCode\WireTable\Actions\ModalStep` | `NyonCode\WireCore\Actions\ModalStep` |
| `NyonCode\WireTable\Actions\ModalFooterAction` | `NyonCode\WireCore\Actions\ModalFooterAction` |

#### Notifications (→ wire-core)
| Before | After |
|--------|-------|
| `NyonCode\WireTable\Notifications\TableNotification` | `NyonCode\WireCore\Notifications\TableNotification` |
| `NyonCode\WireTable\Notifications\TableNotificationManager` | `NyonCode\WireCore\Notifications\TableNotificationManager` |
| `NyonCode\WireTable\Notifications\Contracts\NotificationDriver` | `NyonCode\WireCore\Notifications\Contracts\NotificationDriver` |
| `NyonCode\WireTable\Notifications\Drivers\SessionDriver` | `NyonCode\WireCore\Notifications\Drivers\SessionDriver` |
| `NyonCode\WireTable\Notifications\Drivers\LivewireEventDriver` | `NyonCode\WireCore\Notifications\Drivers\LivewireEventDriver` |
| `NyonCode\WireTable\Notifications\Drivers\FlasherDriver` | `NyonCode\WireCore\Notifications\Drivers\FlasherDriver` |

#### Form Fields (→ wire-forms)
| Before | After |
|--------|-------|
| `NyonCode\WireTable\Forms\Fields\Field` | `NyonCode\WireForms\Components\Field` |
| `NyonCode\WireTable\Forms\Fields\TextInput` | `NyonCode\WireForms\Components\TextInput` |
| `NyonCode\WireTable\Forms\Fields\Textarea` | `NyonCode\WireForms\Components\Textarea` |
| `NyonCode\WireTable\Forms\Fields\Select` | `NyonCode\WireForms\Components\Select` |
| `NyonCode\WireTable\Forms\Fields\Checkbox` | `NyonCode\WireForms\Components\Checkbox` |
| `NyonCode\WireTable\Forms\Fields\CheckboxList` | `NyonCode\WireForms\Components\CheckboxList` |
| `NyonCode\WireTable\Forms\Fields\Radio` | `NyonCode\WireForms\Components\Radio` |
| `NyonCode\WireTable\Forms\Fields\Toggle` | `NyonCode\WireForms\Components\Toggle` |
| `NyonCode\WireTable\Forms\Fields\ColorPicker` | `NyonCode\WireForms\Components\ColorPicker` |
| `NyonCode\WireTable\Forms\Fields\FileUpload` | `NyonCode\WireForms\Components\FileUpload` |
| `NyonCode\WireTable\Forms\Fields\RichEditor` | `NyonCode\WireForms\Components\RichEditor` |
| `NyonCode\WireTable\Forms\Fields\Hidden` | `NyonCode\WireForms\Components\Hidden` |

#### DateTimePicker (unified)
| Before | After |
|--------|-------|
| `DatePicker::make('x')` | `DateTimePicker::make('x')->asDate()` |
| `DateTimePicker::make('x')` | `DateTimePicker::make('x')` (unchanged) |
| `TimePicker::make('x')` | `DateTimePicker::make('x')->asTime()` |

All three are now `NyonCode\WireForms\Components\DateTimePicker`.

#### Layout Components (sub-namespace)
| Before | After |
|--------|-------|
| `NyonCode\WireTable\Forms\Fields\Section` | `NyonCode\WireForms\Components\Layout\Section` |
| `NyonCode\WireTable\Forms\Fields\Grid` | `NyonCode\WireForms\Components\Layout\Grid` |
| `NyonCode\WireTable\Forms\Fields\Fieldset` | `NyonCode\WireForms\Components\Layout\Fieldset` |

#### Display Components (sub-namespace)
| Before | After |
|--------|-------|
| `NyonCode\WireTable\Forms\Fields\Placeholder` | `NyonCode\WireForms\Components\Display\Placeholder` |
| `NyonCode\WireTable\Forms\Fields\Alert` | `NyonCode\WireForms\Components\Display\Alert` |
| `NyonCode\WireTable\Forms\Fields\Html` | `NyonCode\WireForms\Components\Display\Html` |
| `NyonCode\WireTable\Forms\Fields\ViewField` | `NyonCode\WireForms\Components\Display\ViewField` |

## 3. Blade View Changes

```blade
{{-- Before --}}
{!! $this->getTable() !!}

{{-- After --}}
{{ $this->table }}
```

## 4. Interface Changes

`implements HasTable` is now **optional** (it still works, but the trait functions without it):

```php
// Before (required)
class UserTable extends Component implements HasTable { ... }

// After (optional, both work)
class UserTable extends Component implements HasTable { ... }
class UserTable extends Component { use WithTable; ... }
```

## 5. Config File

If you customized notification driver config, update class references:

```
NyonCode\WireTable\Notifications\Drivers\* → NyonCode\WireCore\Notifications\Drivers\*
```

Republish the config:
```bash
php artisan vendor:publish --tag=wire-core-config --force
php artisan vendor:publish --tag=wire-table-config --force
```

## 6. Deprecated Methods

The following methods now emit `E_USER_DEPRECATED` warnings and will be removed in v2.0:

### Table
| Deprecated | Use Instead |
|------------|-------------|
| `polling()` | `poll()` |

### ActionHalt
| Deprecated | Use Instead |
|------------|-------------|
| `modalHeading()` | `heading()` |
| `modalDescription()` | `body()` |
| `modalIcon()` | `icon()` |
| `modalSubmitLabel()` | `submitLabel()` |
| `modalCancelLabel()` | `cancelLabel()` |
| `modalWidth()` | `width()` |
| `formValidation()` | `validation()` |

### Action
| Deprecated | Use Instead |
|------------|-------------|
| `hiddeLabel()` | `hideLabel()` |

## 7. New Features (v1.0)

### Plugin System
Register plugins via config to extend tables, forms, and queries:

```php
// config/wire-core.php
'plugins' => [
    App\Plugins\ExportPlugin::class,
],
```

### Performance
```php
// Simple pagination (no COUNT query)
Table::make()->simplePagination();

// Cursor pagination (for large sequential datasets)
Table::make()->cursorPagination();

// Query result caching
Table::make()->cacheQuery(ttl: 60); // 60 seconds

// Chunked processing for bulk operations
$table->chunk(500, function ($records) {
    // process batch
});
```

## 8. View Publishing

If you published views, republish from the new packages:

```bash
php artisan vendor:publish --tag=wire-core-views --force
php artisan vendor:publish --tag=wire-forms-views --force
php artisan vendor:publish --tag=wire-table-views --force
```
