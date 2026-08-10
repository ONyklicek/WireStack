---
order: 20
---

# Začínáme

Tento průvodce popisuje produkční nastavení Wire v Laravel aplikaci.

## Požadavky

| Závislost | Verze |
|------------|---------|
| PHP | ^8.2 |
| Laravel | 12.61+ nebo 13.12+ |
| Livewire | 3.x |
| Tailwind CSS | 3.x+ |
| Alpine.js | 3.x+ (součástí Livewire) |
| `nyoncode/laravel-package-toolkit` | ^2.4 (nainstaluje se sám) |

Poslední řádek si sami nevyžadujete — Composer ho stáhne spolu s balíčky Wire.
Je tu proto, že určuje dva řádky nad sebou. Toolkit vlastní mirror do
`public/vendor`, který dostane JavaScriptové bundly na disk (viz
[JavaScriptové assety](#javascriptove-assety)), a verze 2.4 vyžaduje
`illuminate/support ^12.61.1|^13.12.0` — takže právě tohle, a ne `^12.0`
deklarované balíčky Wire, je verze Laravelu, proti které se instalace opravdu
řeší. Aplikace, která si toolkit připíná sama, mu musí povolit `^2.4`, jinak
`composer require nyoncode/wire-table` vůbec neprojde.

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
- layout má v `<head>` `@wireStackScripts` — v praxi nutné pro každou aplikaci, která
  naviguje přes `wire:navigate` (viz [JavaScriptové assety](#javascriptove-assety))
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

Váš hlavní layout musí obsahovat Vite assety a Livewire a jedno `@wireStackScripts`
v `<head>`. Přidejte kontejner notifikací, pokud používáte zpětnou vazbu akcí nebo toasty.

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @wireStackScripts {{-- [tl! focus] --}}
</head>
<body>
    {{ $slot }}

    <x-wire-notifications::toast-container />

    @livewireScripts
</body>
</html>
```

Neinstalujte Alpine samostatně. Livewire 3 ho už obsahuje.

<a id="javascript-assets"></a>
## JavaScriptové assety

Interaktivní části Wire — dropdowny, kontextové menu řádku, taby, wizardy,
buňky inline editace, fill handle, výběr řádků, record akce, drag & drop
řazení — jsou malé Alpine komponenty dodávané jako předsestavené bundly přímo
z balíčků. Není co instalovat, není co publikovat a na vaší straně není žádný
build krok: balíčky si své bundly samy zkopírují do `public/vendor/` a servírují je
jako statické soubory, s cache-bustingem podle času poslední změny souboru.

**`@wireStackScripts` dostane bundly všech nainstalovaných balíčků do dokumentu.**
Jeden řádek v `<head>` layoutu a každý controller je přítomný na každé stránce:

```blade
<head>
    @wireStackScripts
</head>
```

Když chcete, zúžíte ho na jediný balíček:

```blade
@wireStackScripts('wire-table')
```

### Proč ho SPA aplikace chce

Bez direktivy si každý povrch svůj bundle stejně načte, když se vykreslí — stránka
s tabulkou tedy načte bundly tabulky, stránka s formulářem ty formulářové. Funguje
to a aplikace, která direktivu nikdy nepřidá, funguje dál.

Přestane to stačit ve chvíli, kdy navigujete přes `wire:navigate`. Cesta
**cachovaného Zpět/Vpřed** v Livewire nečeká na nově injektované `<head>` skripty,
než na prohozené stránce inicializuje Alpine. Bundle, který dorazí *spolu* s novou
stránkou, tak může závod prohrát a markup se inicializuje proti registru, který
komponentu ještě nemá:

```text
Uncaught ReferenceError: wireRecordSelection is not defined
```

což se projeví mrtvými dropdowny, checkboxy, které nic nedělají, a — nejhlasitěji —
šedým scrimem přes celou stránku, protože každý backdrop mobilního sheetu je
navázaný na stav, který už neexistuje.

Bundle, který byl v dokumentu už při prvním příchodu návštěvníka, tenhle závod
prohrát nemůže. Přesně to je úkol `@wireStackScripts`: není to pohodlí, je to
jediné umístění, které cesta cachovaného zpět/vpřed nepředběhne.

> Těžké, volitelné assety — TipTap rich editor, controller grafů nad Chart.js —
> záměrně **nejsou** v sadě načítané vždy. Povrch, který je potřebuje, si je
> vyzvedne, až se vykreslí. Grafy navíc potřebují Chart.js, který zůstává vlastní
> závislostí vaší aplikace.

### Odkud se ty soubory vlastně berou

Jsou to **reálné soubory pod `public/vendor/<balíček>`** a dostanou se tam samy.
První vykreslení stránky po nasazení zkopíruje bundly každého balíčku z instalace
do `public/` a emituje tyhle cesty:

```html
<script src="/vendor/wire-core/wire-core-dropdown.js?id=1786118403" ...></script>
```

Nic se nespouští, nic nenastavuje. Kopírování je inkrementální — soubor, který už
je na místě a je aktuální, se nechá být — takže v ustáleném stavu request udělá
hrst `stat` volání a nula zápisů. Po upgradu je to jedna kopie na změněný bundle,
na jednom requestu. Kopie přistávají přes dočasný soubor a atomický přesun, takže
prohlížeč stahující bundle uprostřed kopírování nikdy nedostane půlku.

Záleží na tom víc, než to zní. Servírování bundlů z **routy** balíčku funguje jen
tehdy, když se request dostane do PHP — a hodně rozšířené nastavení webserveru
odpovídá na `.js` samo:

```nginx
location ~* \.(js|css)$ {
    try_files $uri =404;      # routa není soubor na disku → 404
}
```

Na sdíleném hostingu tenhle blok často není váš, abyste ho měnili — a úplně stejně
rozbíjí i Livewire vlastní `/livewire/livewire.js`. Soubor, který existuje,
naservíruje každá konfigurace webserveru, jaká je, a proto vám ho balíčky připraví.

**Publikování je pořád podporované** a dělá tutéž kopii dopředu, čímž ji sundá
z prvního requestu po nasazení:

```bash
php artisan vendor:publish --tag=laravel-assets --force
```

`laravel-assets` je tag, který skeleton Laravelu už spouští ze svého composer
`post-update-cmd`, takže `composer update` udržuje kopie aktuální sám od sebe. Ani
příkaz, ani ten hook nejsou povinné.

### Když `public/` není zapisovatelné

Read-only kontejner, Vapor, zpevněné nasazení: nic nespadne. Bundly servíruje routa
přesně jako předtím, a chcete buď publikovací příkaz výše (spuštěný při buildu, kdy
je filesystém ještě zapisovatelný), nebo `try_files … /index.php?$query_string`, aby
byla routa dosažitelná.

Pokud tam už **starší** kopie je, servíruje se dál, místo aby se spadlo na routu,
která nemusí být dosažitelná — a konzole to řekne, na každé stránce a bez ohledu na
`APP_DEBUG`, včetně názvů bundlů a příkazu, který to spraví. Viz
[Řešení potíží](troubleshooting.md#javascriptove-404-a-wirex-is-not-defined).

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
        return $table // [tl! focus:start]
            ->model(User::class)
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('email')
                    ->searchable(),

                BadgeColumn::make('role')
                    ->colors([
                        'admin' => 'primary',
                        'editor' => 'success',
                        'viewer' => 'gray',
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
            ->paginated(); // [tl! focus:end]
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
        return $form // [tl! focus:start]
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
            ->successMessage('User saved.'); // [tl! focus:end]
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
- ověřte, že `<head>` layoutu obsahuje `@wireStackScripts` — zvlášť když se rozbití
  projeví až po návštěvě přes `wire:navigate` nebo na Zpět/Vpřed
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
