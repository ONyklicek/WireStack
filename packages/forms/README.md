# Wire Forms

Standalone form system for Laravel Livewire. 20+ field types, validation, layout components. Can be used **independently** or together with [Wire Table](https://github.com/NyonCode/wire-table).

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 12
- Livewire 3.x
- Tailwind CSS 3.x
- Node.js & npm (for Vite asset compilation)

## Installation

```bash
composer require nyoncode/wire-forms
```

This automatically installs `wire-core` as a dependency. The service providers are auto-discovered.

### Tailwind CSS Setup

Wire Forms uses Tailwind utility classes in its Blade templates. Add the package views to your Tailwind content paths:

**Tailwind 3** (`tailwind.config.js`):

```js
export default {
    content: [
        './resources/**/*.blade.php',
        './app/**/*.php',
        './vendor/nyoncode/wire-core/resources/views/**/*.blade.php',
        './vendor/nyoncode/wire-forms/resources/views/**/*.blade.php',
    ],
    darkMode: 'class',
    plugins: [require('@tailwindcss/forms')],
}
```

**Tailwind 4** (`resources/css/app.css`):

```css
@import "tailwindcss";
@plugin "@tailwindcss/forms";
@source "../../vendor/nyoncode/wire-core/resources/views";
@source "../../vendor/nyoncode/wire-forms/resources/views";
```

Install the forms plugin and rebuild:

```bash
npm install -D @tailwindcss/forms
npm run build
```

### Layout Template

Your layout must include Vite assets and Livewire (which provides Alpine.js):

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    {{ $slot }}
    @livewireScripts
</body>
</html>
```

### Publish Config (optional)

```bash
php artisan vendor:publish --tag=wire-forms-config
```

## Quick Start: Standalone Form

Wire Forms works without Wire Table. Here's a complete standalone form using `WithForms` trait and `Form` class:

```php
<?php

namespace App\Livewire;

use Livewire\Component;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireForms\Components\Layout\Section;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class CreateUser extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(User::class)
            ->schema([
                Section::make('User Details')->schema([
                    TextInput::make('name')
                        ->label('Full Name')
                        ->required(),

                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required(),

                    Select::make('role')
                        ->options([
                            'admin' => 'Administrator',
                            'editor' => 'Editor',
                            'viewer' => 'Viewer',
                        ])
                        ->required(),

                    Toggle::make('active')
                        ->label('Active')
                        ->default(true),
                ]),
            ])
            ->successMessage('User created');
    }

    public function render()
    {
        return view('livewire.create-user');
    }
}
```

```blade
<form wire:submit="$this->form->save">
    {{ $this->form }}
    <button type="submit">Create User</button>
</form>
```

### Multi-Form Example

Multiple forms in a single component — methods ending with `Form` are auto-detected:

```php
class UserSettings extends Component
{
    use WithForms;

    public ?array $profileData = [];
    public ?array $passwordData = [];

    public function profileForm(Form $form): Form
    {
        return $form
            ->statePath('profileData')
            ->model($this->user)
            ->schema([
                TextInput::make('name')->required(),
                TextInput::make('email')->email()->required(),
            ])
            ->successMessage('Profile updated');
    }

    public function passwordForm(Form $form): Form
    {
        return $form
            ->statePath('passwordData')
            ->model($this->user)
            ->schema([
                TextInput::make('password')->password()->confirmed(),
                TextInput::make('password_confirmation')->password(),
            ])
            ->successMessage('Password changed');
    }
}
```

```blade
<form wire:submit="$this->profileForm->save">
    {{ $this->profileForm }}
    <button type="submit">Save Profile</button>
</form>

<form wire:submit="$this->passwordForm->save">
    {{ $this->passwordForm }}
    <button type="submit">Change Password</button>
</form>
```

### Standalone Usage (Testing / Jobs)

Forms work without Livewire — useful for testing and console commands:

```php
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Components\TextInput;

$form = Form::make()
    ->schema([
        TextInput::make('name')->required(),
        TextInput::make('email')->email()->required(),
    ])
    ->state(['name' => 'John', 'email' => 'john@example.com']);

$data = $form->validate(); // throws ValidationException on failure
```

## Quick Start: In Action Modal (with Wire Table)

When used with Wire Table, form fields are rendered inside action modals:

```php
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Select;

Action::make('edit')
    ->form([
        TextInput::make('name')->required(),
        Select::make('status')->options([...]),
    ])
    ->action(function ($record, $data) {
        $record->update($data);
    });
```

See [Wire Table documentation](https://github.com/NyonCode/wire-table) for details.

## Field Types

### Input Fields
| Field | Description |
|-------|-------------|
| `TextInput` | Text, email, number, tel, url, password |
| `Textarea` | Multi-line text |
| `Select` | Dropdown with options |
| `Checkbox` | Single checkbox |
| `CheckboxList` | Multiple checkboxes |
| `Radio` | Radio button group |
| `Toggle` | Toggle switch |
| `DateTimePicker` | Date, time, or datetime picker (`->mode('date'\|'time'\|'datetime')`) |
| `ColorPicker` | Color picker |
| `FileUpload` | File upload with preview |
| `RichEditor` | Rich text editor |
| `Hidden` | Hidden input |

### Layout Components
| Component | Description |
|-----------|-------------|
| `Section` | Collapsible section with heading |
| `Fieldset` | Grouped fields with legend |
| `Grid` | Multi-column grid layout |

### Display Components
| Component | Description |
|-----------|-------------|
| `Placeholder` | Static text display |
| `Alert` | Alert/callout box |
| `Html` | Raw HTML content |
| `ViewField` | Custom Blade view |

## Common Field API

```php
TextInput::make('name')
    ->label('Custom Label')
    ->placeholder('Enter value...')
    ->default('Default value')
    ->required()
    ->disabled()
    ->readonly()
    ->hidden()
    ->helperText('Help text below the field')
    ->hint('Hint text', 'info-icon')
    ->prefix('$')
    ->suffix('.00')
    ->prefixIcon('currency')
    ->suffixIcon('calculator')
    ->columnSpan(2)
    ->columnSpanFull()
    ->rules(['min:3', 'max:255'])
    ->extraAttributes(['data-testid' => 'name-input']);
```

## Form API

```php
$form
    ->schema(array $components)
    ->statePath(string $path)
    ->fill(array $data)
    ->state(array $data)                       // alias for fill()
    ->getState(): array
    ->validate(): array                        // throws ValidationException
    ->model(string|Model|null $model)
    ->save(): ?Model
    ->using(Closure $fn)                       // override Eloquent persistence
    ->mutateDataBeforeSave(Closure $fn)
    ->beforeSave(Closure $fn)
    ->afterSave(Closure $fn)
    ->successMessage(string|Closure|null $message)
    ->disableSuccessNotification()
    ->disabled(bool $disabled = true)
    ->isCreating(): bool
    ->isEditing(): bool
    ->getModel(): Model|string|null
    ->getFlatComponents(): array
    ->getValidationRules(): array;
```

## Configuration

```bash
php artisan vendor:publish --tag=wire-forms-config
```

## License

MIT
