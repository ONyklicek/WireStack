---
order: 10
---

# Wire Forms

Samostatný systém formulářů pro Laravel Livewire. Funguje nezávisle nebo s Wire Table.

> Potřebujete záznam **zobrazit** read-only místo editace? Viz [Infolisty](../core/infolists.md) — stejné schéma a layout, jen se zobrazovacími entries místo vstupních polí.

## Instalace

```bash
composer require nyoncode/wire-forms
```

Přidejte do Tailwind content cest:
```js
export default {
    content: [
        // ...aktuální cesty aplikace
        './vendor/nyoncode/wire-core/resources/views/**/*.blade.php',
        './vendor/nyoncode/wire-forms/resources/views/**/*.blade.php',
    ]
}
```

---

## Jak formuláře fungují

Definujte schéma `Form` na své Livewire komponentě, navažte ho na state path a vykreslete pomocí `{{ $this->form }}`.

---

## Jeden formulář

```php
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;
use NyonCode\WireForms\Components\TextInput;

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
                TextInput::make('name')->required(),
                TextInput::make('email')->email()->required(),
            ])
            ->successMessage('User created');
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

```blade
<form wire:submit="save">
    {{ $this->form }}
    <button type="submit">Create</button>
</form>
```

---

## Více formulářů

Metody končící na `Form` se automaticky detekují:

```php
class UserSettings extends Component
{
    use WithForms;

    public array $profileData = [];
    public array $passwordData = [];

    public function profileForm(Form $form): Form
    {
        return $form
            ->statePath('profileData')
            ->model($this->user)
            ->schema([
                TextInput::make('name')->required(),
                TextInput::make('bio'),
            ]);
    }

    public function passwordForm(Form $form): Form
    {
        return $form
            ->statePath('passwordData')
            ->schema([
                TextInput::make('current_password')->password()->required(),
                TextInput::make('password')->password()->required()->rules(['confirmed']),
                TextInput::make('password_confirmation')->password()->required(),
            ]);
    }

    public function saveProfile(): void
    {
        $this->profileForm->save();
    }

    public function savePassword(): void
    {
        $data = $this->passwordForm->validate();
        $this->user->update(['password' => Hash::make($data['password'])]);
    }
}
```

```blade
<form wire:submit="saveProfile">
    {{ $this->profileForm }}
    <button type="submit">Save Profile</button>
</form>

<form wire:submit="savePassword">
    {{ $this->passwordForm }}
    <button type="submit">Change Password</button>
</form>
```

### Explicitní registrace formulářů

Alternativa k auto-detekci — vraťte názvy metod přesně tak, jak jsou definované:

```php
protected function getForms(): array
{
    return ['profileForm', 'passwordForm'];
}
```

---

## Režimy modelu

```php
// Create mód — Form::save() volá User::create($data)
$form->model(User::class);

// Edit mód — Form::save() volá $user->update($data)
$form->model($user);

// Žádný model — save() vyhodí chybu, ale validate() funguje
$form->model(null);
```

Introspekce:
```php
$form->isCreating();   // true když je model řetězec třídy
$form->isEditing();    // true když je model instance
$form->getModel();     // instance modelu nebo null
```

---

## Samostatné použití (bez Livewire)

Funguje pro server-side validaci a zpracování dat:

```php
$form = Form::make()
    ->schema([
        TextInput::make('name')->required()->maxLength(255),
        TextInput::make('email')->email()->required(),
    ])
    ->state(['name' => 'John', 'email' => 'john@example.com']);

// Jen validace
$validated = $form->validate(); // při selhání vyhodí ValidationException

// Validace + uložení
$form->model(User::class)->save();
```

---

## Reference Form API

### Schéma a stav

```php
->schema(array $components)          // definice polí
->statePath(string $path)            // název Livewire vlastnosti pro stav
->fill(array $data)                  // naplnit stav
->state(array $data)                 // alias pro fill()
->getState(): array                  // aktuální stav
->getValidationRules(): array        // posbíraná pravidla
->validate(): array                  // zvalidovat a vrátit data
```

### Model a uložení

```php
->model(string|Model|null $model)    // Eloquent model (třída pro create, instance pro edit)
->save(): mixed                      // celý životní cyklus uložení
->using(Closure $fn)                 // vlastní save callback (nahrazuje výchozí perzistenci)
->optimisticLock(?string $column = 'updated_at') // přeruší update, když se záznam změnil od fill
```

Ochrana proti souběžné editaci viz [Životní cyklus ukládání → Optimistic locking](save-lifecycle.md#optimistic-locking).

### Hooky životního cyklu uložení

```php
->mutateDataBeforeSave(Closure $fn)  // fn(array $data): array — transformovat data před perzistencí
->beforeSave(Closure $fn)            // fn(array $data): void — běží před perzistencí
->afterSave(Closure $fn)             // fn(Model|mixed $record): void — běží po perzistenci
```

### Notifikace

```php
->successMessage(string|Closure|null $msg)  // vlastní text úspěšné notifikace; Closure dostane $record
->disableSuccessNotification()              // žádná notifikace po uložení
```

### Validace

```php
->validationMessages(array $msgs)    // vlastní validační zprávy
```

### Stav

```php
->disabled(bool $disabled = true)    // udělat všechna pole read-only
```

### Autorizace

```php
->authorize(bool $usePolicy = true)              // zapnout auto-resolvování policy modelu (create/update)
->authorizeUsing(?Closure $callback)             // fn(User $user, $record = null): bool — vlastní auth kontrola
->canSave(): bool                                // zda aktuální uživatel smí uložit
->isReadOnly(): bool                             // true když autorizace zamítne uložení
```

Když je `->authorize()` zapnuto, formulář se stane read-only (a skryje tlačítko uložit), pokud aktuální uživatel nemá `create` nebo `update` policy oprávnění na modelu.

### Introspekce

```php
->isCreating(): bool                 // model je řetězec třídy
->isEditing(): bool                  // model je instance
->getModel(): ?Model                 // aktuální instance modelu
->getFlatComponents(): array         // všechny komponenty (ploše)
```

### Rendering

```php
->toHtml(): string                   // Blade výstup
(string) $form                       // __toString()
```

### Factory

```php
Form::make()                         // statická factory přes container
```

### Livewire vazba

```php
->livewire(Component $component)     // navázat na Livewire komponentu
```

---

## Trait WithForms

Trait `WithForms` poskytuje:

1. **Auto-detekci** — skenuje metody končící na `Form` a registruje je
2. **Lazy resolvování** — formuláře se staví až při prvním přístupu
3. **Cachování** — instance formulářů jsou cachované po dobu requestu
4. **Magický přístup k vlastnosti** — `$this->profileForm` vyresolvuje formulář

```php
class MyComponent extends Component
{
    use WithForms;

    // Přístup přes:
    // $this->form            → volá metodu form()
    // $this->profileForm     → volá metodu profileForm()
    // $this->settingsForm    → volá metodu settingsForm()
}
```

---

## Typy polí

### Vstupní pole

- [TextInput](fields/text-input.md) — text, email, heslo, číslo, tel, url
- [Textarea](fields/textarea.md) — víceřádkový text
- [Select](fields/select.md) — dropdown, searchable, multiple, relace
- [Checkbox](fields/checkbox.md) — jeden checkbox
- [CheckboxList](fields/checkbox-list.md) — skupina více checkboxů
- [Radio](fields/radio.md) — skupina radio tlačítek
- [Toggle](fields/toggle.md) — přepínač on/off
- [DateTimePicker](fields/date-time-picker.md) — jednotné date/time/datetime
- [ColorPicker](fields/color-picker.md) — výběr barvy
- [FileUpload](fields/file-upload.md) — upload souboru/obrázku
- [RichEditor](fields/rich-editor.md) — WYSIWYG editor
- [Hidden](fields/hidden.md) — skryté pole

### Layoutové komponenty

Layoutové a schema komponenty (Grid, Flex, Section, Fieldset, Tabs, Wizard,
Callout, Empty State) žijí ve sdílené sekci [Schema](../core/schema/overview.md) —
stejný slovník používají formuláře, infolisty i modály.

- [Grid](../core/schema/layout/grid.md) — CSS grid layout
- [Section](../core/schema/layout/section.md) — sbalitelná sekce s nadpisem
- [Fieldset](../core/schema/layout/fieldset.md) — HTML fieldset

### Zobrazovací komponenty

- [Placeholder](fields/placeholder.md) — statický text
- [Alert](fields/alert.md) — alert zpráva
- [Html](fields/html.md) — raw HTML
- [ViewField](fields/view-field.md) — vlastní Blade pohled

### Postavte si vlastní

- [Rozšíření formulářů](custom-fields.md) — vlastní pole, zobrazovací komponenty, presety a balíčkování

### Sdílené API pole

Každé pole dědí:

```php
->label(string|Closure $label)
->helperText(string|Closure $text)
->hint(string|Closure $hint)
->hintIcon(string $icon)
->required(bool|Closure $required = true)
->hidden(bool|Closure $hidden = true)
->visible(bool|Closure $visible = true)
->disabled(bool|Closure $disabled = true)
->size('sm'|'md'|'lg'|'xl')
->columnSpan(int|string $span)          // šířka sloupce v gridu
->default(mixed $value)                 // výchozí hodnota
->extraAttributes(array $attrs)         // HTML atributy
->live()                                // wire:model.live
->debounce(int $ms = 500)              // wire:model.blur s debounce
->afterStateUpdated(Closure $callback)  // reagovat na změny hodnoty (auto-zapne live)
->rules(string|array $rules)            // Laravel validační pravidla
->validationMessages(array $messages)   // vlastní validační zprávy
```

Closury `visible()`, `hidden()`, `disabled()` a `afterStateUpdated()` dostávají live state
accessory (`$get`, `$set`, `$state`). Viz [Reaktivní pole](reactive-fields.md).
