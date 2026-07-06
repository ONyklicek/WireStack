---
order: 20
---

# Začínáme

Tento průvodce popisuje produkční nastavení Wire v Laravel aplikaci.

## Požadavky

| Závislost | Verze |
|------------|---------|
| PHP | ^8.2 |
| Laravel | 10, 11 nebo 12 |
| Livewire | 3.x |
| Tailwind CSS | 3.x+ |
| Alpine.js | 3.x+ (součástí Livewire) |

## Instalace

### Celý ekosystém (table + forms + core)

```bash
composer require nyoncode/wire-table
```

### Pouze formuláře (forms + core)

```bash
composer require nyoncode/wire-forms
```

### Pouze core

```bash
composer require nyoncode/wire-core
```

### Balíček sortable (drag and drop řazení řádků)

```bash
composer require nyoncode/wire-sortable
```

Service providery se registrují automaticky přes Laravel auto-discovery.

## Produkční checklist

Než vykreslíte první komponentu, ujistěte se, že platí všechno níže:

- Livewire 3 je nainstalováno
- Tailwind skenuje vendor pohledy Wire
- vaše aplikace definuje barvu `primary`
- hlavní layout obsahuje `@vite`, `@livewireStyles` a `@livewireScripts`
- layout vykresluje `<x-wire-notifications::toast-container />`, pokud chcete vestavěné toasty

## Konfigurace Tailwind CSS

Wire generuje část utility tříd z PHP (resolvery barev/velikostí, třídy mobilního
bottom-sheetu, responzivní sloupce gridu, …), takže Tailwind musí skenovat jak
**pohledy, tak `src`** balíčků — samotné skenování pohledů tyto třídy mine.

**Tailwind 3** — přidejte cesty do `tailwind.config.js`:

```js
module.exports = {
    content: [
        // ...vaše cesty
        './vendor/nyoncode/wire-core/resources/views/**/*.blade.php',
        './vendor/nyoncode/wire-core/src/**/*.php',
        './vendor/nyoncode/wire-forms/resources/views/**/*.blade.php',
        './vendor/nyoncode/wire-forms/src/**/*.php',
        './vendor/nyoncode/wire-table/resources/views/**/*.blade.php',
        './vendor/nyoncode/wire-table/src/**/*.php',
        './vendor/nyoncode/wire-sortable/resources/views/**/*.blade.php',
        './vendor/nyoncode/wire-sortable/src/**/*.php',
    ],
}
```

**Tailwind 4** — přidejte řádky `@source` do vstupního CSS (např. `app.css`):

```css
@source "../../vendor/nyoncode/wire-core/resources/views";
@source "../../vendor/nyoncode/wire-core/src";
@source "../../vendor/nyoncode/wire-forms/resources/views";
@source "../../vendor/nyoncode/wire-forms/src";
@source "../../vendor/nyoncode/wire-table/resources/views";
@source "../../vendor/nyoncode/wire-table/src";
@source "../../vendor/nyoncode/wire-sortable/resources/views";
@source "../../vendor/nyoncode/wire-sortable/src";
```

<a id="primary-color"></a>
### Barva primary

Komponenty Wire používají `primary` jako výchozí akcentovou barvu (tlačítka, badge, focus ringy atd.). Musíte ji definovat v konfiguraci Tailwindu:

**Tailwind 3** (`tailwind.config.js`):

```js
const colors = require('tailwindcss/colors')

module.exports = {
    theme: {
        extend: {
            colors: {
                primary: colors.blue, // nebo libovolná paleta barev
            },
        },
    },
}
```

**Tailwind 4** (`app.css`):

```css
@theme {
    --color-primary-50: var(--color-blue-50);
    --color-primary-100: var(--color-blue-100);
    --color-primary-200: var(--color-blue-200);
    --color-primary-300: var(--color-blue-300);
    --color-primary-400: var(--color-blue-400);
    --color-primary-500: var(--color-blue-500);
    --color-primary-600: var(--color-blue-600);
    --color-primary-700: var(--color-blue-700);
    --color-primary-800: var(--color-blue-800);
    --color-primary-900: var(--color-blue-900);
    --color-primary-950: var(--color-blue-950);
}
```

> Bez definované barvy `primary` budou tlačítka a další interaktivní prvky neviditelné (bílý text na průhledném pozadí).

<a id="layout-template"></a>
## Šablona layoutu

Váš hlavní layout musí obsahovat Vite assety a Livewire. Přidejte kontejner notifikací, pokud používáte zpětnou vazbu akcí nebo toasty.

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    {{ $slot }}

    <x-wire-notifications::toast-container />

    @livewireScripts
</body>
</html>
```

Neinstalujte Alpine samostatně. Livewire 3 ho už obsahuje.

## Publikování konfigurace (volitelné)

```bash
php artisan vendor:publish --tag=wire-core::config
php artisan vendor:publish --tag=wire-forms::config
php artisan vendor:publish --tag=wire-table::config
php artisan vendor:publish --tag=wire-sortable::config
```

## Publikování pohledů (volitelné)

```bash
php artisan vendor:publish --tag=wire-core::views
php artisan vendor:publish --tag=wire-forms::views
php artisan vendor:publish --tag=wire-table::views
php artisan vendor:publish --tag=wire-sortable::views
```

---

## Rychlý start: Tabulka

```php
use Livewire\Component;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\DeleteAction;
use NyonCode\WireCore\Actions\DeleteBulkAction;

class UserTable extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(User::class)
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('email')
                    ->searchable(),

                BadgeColumn::make('role')
                    ->colors([
                        'primary' => 'admin',
                        'success' => 'editor',
                        'gray' => 'viewer',
                    ]),

                TextColumn::make('created_at')
                    ->dateTime('d.m.Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        'admin' => 'Admin',
                        'editor' => 'Editor',
                        'viewer' => 'Viewer',
                    ]),
            ])
            ->actions([
                Action::make('edit')
                    ->icon('pencil')
                    ->url(fn (User $r) => route('users.edit', $r)),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('name')
            ->searchable()
            ->paginated();
    }
}
```

```blade
<div>
    {{ $this->table }}
</div>
```

Dále: [Sloupce](table/columns/index.md), [Filtry](table/filters/index.md), [Akce](table/actions.md)

---

## Rychlý start: Formulář

```php
use Livewire\Component;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\Toggle;

class EditUser extends Component
{
    use WithForms;

    public array $data = [];

    public function mount(User $user): void
    {
        $this->form()->model($user)->fill($user->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(User::class)
            ->schema([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('email')->email()->required(),
                Select::make('role')
                    ->options(['admin' => 'Admin', 'editor' => 'Editor', 'viewer' => 'Viewer'])
                    ->required(),
                Toggle::make('active'),
            ])
            ->successMessage('User saved.');
    }

    public function save(): void
    {
        $this->form()->save();
    }
}
```

```blade
<form wire:submit="save">
    {{ $this->form }}
    <button type="submit">Save</button>
</form>
```

Dále: [Reference polí](forms/fields/index.md), [Validace](forms/validation.md), [Životní cyklus ukládání](forms/save-lifecycle.md)

## Řešení potíží

### Chybí styly

- ověřte, že vendor cesty Wire jsou v Tailwind content nebo `@source`
- přebuildujte assety pomocí `npm run build`
- vyčistěte zkompilované pohledy pomocí `php artisan view:clear`

### Komponenty se vykreslují bez JavaScriptového chování

- ověřte, že layout obsahuje `@livewireScripts`
- odstraňte samostatný bootstrap Alpine z `resources/js/app.js`

### Notifikace se nezobrazují

- ověřte, že layout vykresluje `<x-wire-notifications::toast-container />`
- ověřte, že vámi nakonfigurovaný notifikační driver je platný
- zkontrolujte, zda akce skutečně odesílá úspěšnou nebo chybovou notifikaci

---

## Vývoj (monorepo)

```bash
git clone ...
composer install

# Spustit všechny testy
composer test

# Po balíčcích
composer test:core    # 793 tests
composer test:forms   # 212 tests
composer test:table    # 369 tests
composer test:sortable # 10 tests

# Styl kódu
composer lint          # Pint (Laravel preset)

# Statická analýza
composer analyse       # PHPStan level 6
```

## Další kroky

- [Sloupce tabulky](table/columns/index.md) — všech 13 typů sloupců
- [Pole formulářů](forms/overview.md) — všechny typy polí a Form API
- [Akce](core/actions.md) — řádkové, hromadné, hlavičkové akce
- [Core pluginy](core/plugins.md) — znovupoužitelná rozšíření aplikace a balíčků
- [Konfigurace](configuration.md) — konfigurační soubory a proměnné prostředí
- [Autorizace](authorization.md) — Gates, policies, oprávnění
- [Exporty tabulky](table/exports.md) — stahování CSV, Excel, PDF
- [Audit log](core/audit.md) — historie změn modelů
- [Sortable řádky](sortable/overview.md) — drag & drop řazení řádků
