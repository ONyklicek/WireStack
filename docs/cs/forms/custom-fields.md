---
order: 40
---

# Rozšíření formulářů

Wire Forms je stavěné k rozšiřování. Většina aplikací to nikdy nepotřebuje —
vestavěná [sada polí](fields/index.md) pokrývá běžné případy — ale když potřebujete
pole, které se s balíčkem nedodává, napíšete malou PHP třídu plus Blade
pohled a použijete ho přesně jako first-party pole.

Tato stránka pokrývá, od nejméně po nejvíce náročné:

| Potřebujete… | Použijte |
|-----------|-----|
| Jednorázový vlastní pohled uvnitř formuláře | [`ViewField`](#rychly-vlastni-pohled-viewfield) |
| Statický zobrazovací blok (bez inputu) | [Zobrazovací komponentu](#pouze-zobrazovaci-komponenty) |
| Znovupoužitelný input s vlastním API | [Vlastní pole](#stavba-vlastniho-pole) |
| Stejný preset aplikovaný napříč mnoha formuláři | [Form makra](#znovupouzitelne-presety) |
| Pole, které ukládá něco jiného, než zobrazuje | [Transformace stavu](#transformace-stavu-pole) |
| Logiku kolem každého uložení | [Save hooky](#zapojeni-do-zivotniho-cyklu-ukladani) |
| Pole dodané v balíčku | [Balíčkování polí do pluginu](#balickovani-poli-do-pluginu) |

> Nejdřív preferujte fluent API. Po vlastním poli sáhněte jen když se má stejné
> chování znovupoužít, nebo když žádné vestavěné pole nelze nakonfigurovat, aby
> tu práci udělalo.

---

<a id="quick-custom-view-viewfield"></a>
## Rychlý vlastní pohled (ViewField)

Když potřebujete jen vhodit vlastní markup do formuláře — bez znovupoužitelného API —
použijte [`ViewField`](fields/view-field.md). Vykreslí libovolný Blade pohled a předá
skrz state path formuláře, takže se úplně vyhnete psaní třídy.

```php
use NyonCode\WireForms\Components\Display\ViewField;

ViewField::make('avatar_preview')
    ->view('forms.partials.avatar-preview')
    ->viewData(fn () => ['url' => $this->user->avatar_url]);
```

```blade
{{-- resources/views/forms/partials/avatar-preview.blade.php --}}
<img src="{{ $url }}" class="h-16 w-16 rounded-full" alt="">
```

`viewData()` přijímá pole nebo closuru (vyhodnocenou v čase renderu). `ViewField`
použijte pro náhledy, callouty nebo na míru šité widgety, které nemusí být
sdílenou pojmenovanou komponentou.

---

## Jak je pole poskládané

Každá form komponenta rozšiřuje
`NyonCode\WireCore\Foundation\Components\Component`. Ta základní třída vám zdarma dává:

- factory `make(string $name)` a konstruktor jen s `$name`
- label, hint, helper text, id, size, column span, viditelnost
- `extraAttributes()`, `default()` a vyhodnocování closur přes `evaluate()`
- `render()`, který volá váš `viewName()` s komponentou dostupnou v pohledu jako `$field`

Vstupní pole rozšiřují `NyonCode\WireForms\Components\Field`, který přidává části,
jež dělají pole interaktivním:

| Concern | Co přidává |
|---------|--------------|
| `HasState` | `getStatePath()`, `getWireModelAttribute()` |
| `CanBeLive` | `->live()`, `getWireModelModifier()` |
| `HasDebounce` | `->debounce()`, `getDebounceModifier()` |
| `CanBeReadOnly` | `->disabled()`, `isReadOnly()` |
| `HasFormValidation` | `->rules()`, `->required()`, sběr pravidel |
| `HasPlaceholder`, `HasPrefixAndSuffix`, `HasTooltip`, `CanBeAutofocused` | volitelné prvky |

Pole také deklaruje svůj **typ stavu** pomocí `getStateType()` (výchozí
`'string'`). State hydrator ho používá k přetypování surových request hodnot, než
dorazí do stavu formuláře — vraťte `'int'`, `'float'`, `'bool'` nebo `'array'`, když
vaše hodnota není řetězec.

`getStateType()` tvaruje hodnotu na cestě *dovnitř*. Když ji vaše pole potřebuje
tvarovat i na cestě *ven* — tedy uložit něco jiného, než drží widget —
implementujte [`DehydratesState`](#transformace-stavu-pole), místo abyste
tu práci přehazovali na každý formulář, který pole použije.

Jediná abstraktní metoda, kterou musíte implementovat, je `viewName()`.

---

<a id="building-a-custom-field"></a>
## Stavba vlastního pole

Postavíme pole `MoneyInput`, které ukládá celočíselný počet centů a
vykresluje textový input vědomý si měny.

### 1. PHP třída

```php
<?php

namespace App\Forms\Components;

use NyonCode\WireForms\Components\Field;

class MoneyInput extends Field
{
    protected string $currency = 'USD';

    protected int $decimals = 2;

    public function currency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function decimals(int $decimals): static
    {
        $this->decimals = $decimals;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getDecimals(): int
    {
        return $this->decimals;
    }

    // Hodnota je uložena jako integer (centy). [tl! focus:start]
    public function getStateType(): string
    {
        return 'int';
    } // [tl! focus:end]

    protected function viewName(): string // [tl! focus:start]
    {
        return 'forms.components.money-input';
    } // [tl! focus:end]
}
```

Konvence, které stojí za dodržení, protože je vestavěná pole všechna dodržují:

- **Fluent settery vrací `static`**, aby se volání řetězila.
- **Stav se nastavuje přes protected vlastnosti** s odpovídajícími gettery; Blade
  pohled čte gettery, nikdy vlastnosti.
- **Settery přijímají `Closure`, kde to dává smysl**, a gettery je resolvují
  pomocí `$this->evaluate(...)`. To je to, co rozchodí `->label(fn () => ...)`.

### 2. Blade pohled

Obalte input do sdílených field-wrapper partialů. Vykreslí za vás label,
hint, marker povinnosti, helper text a validační chybu — takže vlastní
pole vypadá stejně jako vestavěné a nepotřebuje pro ně žádný extra markup.

```blade
{{-- resources/views/forms/components/money-input.blade.php --}}
@php
    use App\Forms\Components\MoneyInput;

    assert($field instanceof MoneyInput);

    $wireModifier   = $field->getWireModelModifier();
    $debounceModifier = $field->getDebounceModifier();
    $wireAttr = 'wire:model' . ($wireModifier ? ".{$wireModifier}" : '') . $debounceModifier;
@endphp

@include('wire-forms::partials.field-wrapper-start')

<div class="flex rounded-md shadow-sm">
    <span class="inline-flex items-center rounded-l-md border border-r-0 border-gray-300 bg-gray-50 px-3 text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-400">
        {{ $field->getCurrency() }}
    </span>

    <input
        type="number"
        step="{{ 1 / (10 ** $field->getDecimals()) }}"
        id="{{ $field->getId() }}"
        {{ $wireAttr }}="{{ $field->getWireModelAttribute() }}"
        @if($field->isReadOnly()) readonly @endif
        @if($field->isRequired()) required @endif
        class="block w-full rounded-r-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
    />
</div>

@include('wire-forms::partials.field-wrapper-end')
```

Klíčové zapojení je atribut `wire:model`. `getWireModelAttribute()` vrací
správnou tečkovou state path (například `data.price`) a modifikátorové
helpery přidávají `.live` / `.debounce.Nms`, když se do nich pole přihlásí. To je
stejný vzor, jaký používá každé vestavěné pole — kompletní referenci viz
`packages/forms/resources/views/components/text-input.blade.php`.

### 3. Použití

Vlastní pole je normální komponenta. Přidejte ji do jakéhokoli schématu:

```php
use App\Forms\Components\MoneyInput;

$form->schema([
    MoneyInput::make('price')
        ->currency('EUR')
        ->decimals(2)
        ->required()
        ->helperText('Stored in cents.'),
]);
```

Pro použití ve vlastní aplikaci není potřeba žádný registrační krok: pole si resolvuje
vlastní pohled, takže stačí ho uvést ve schématu.

---

<a id="display-only-components"></a>
## Pouze zobrazovací komponenty

Pro výstup, který nemá vstupní hodnotu — callouty, souhrny, počítané náhledy —
rozšiřte `NyonCode\WireCore\Foundation\Components\ViewComponent` místo
`Field`. Je to stejný základ, minus input/validační concerny, a je to to, co
používá `Placeholder`, `Alert` a `Html`.

```php
<?php

namespace App\Forms\Components;

use Closure;
use NyonCode\WireCore\Foundation\Components\ViewComponent;

class StatBlock extends ViewComponent
{
    protected string|Closure $value = '';

    public function value(string|Closure $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getValue(): string
    {
        return (string) $this->evaluate($this->value);
    }

    protected function viewName(): string
    {
        return 'forms.components.stat-block';
    }
}
```

---

<a id="reusable-presets"></a>
<a id="reusable-presets-with-macros"></a>
## Znovupoužitelné presety

Když nepotřebujete novou komponentu, jen **preset** existujících fluent volání,
máte dvě přesné možnosti. (Form pole nejsou `Macroable` — na rozdíl od `Table`
a `Action`, které podporují `::macro()`; table/action makra viz
[Core Pluginy → Přidávání tlačítek a akcí](../core/plugins.md#pridavani-tlacitek-a-akci).)

**Statická factory** drží preset na jednom místě a čte se čistě na místě volání:

```php
namespace App\Forms;

use NyonCode\WireForms\Components\TextInput;

class Fields
{
    public static function slug(string $name): TextInput
    {
        return TextInput::make($name)
            ->helperText('Lowercase, dash-separated.')
            ->rules(['regex:/^[a-z0-9-]+$/'])
            ->live();
    }
}
```

```php
use App\Forms\Fields;

$form->schema([
    Fields::slug('slug'),
]);
```

**Podtřída, která předkonfiguruje `make()`**, je správná volba, když také
chcete odlišný typ, na který lze odkázat jinde:

```php
use NyonCode\WireForms\Components\TextInput;

class SlugInput extends TextInput
{
    public static function make(string $name): static
    {
        return parent::make($name)
            ->rules(['regex:/^[a-z0-9-]+$/'])
            ->live();
    }
}
```

Použijte **factory nebo podtřídu**, když skládáte existující metody polí, a
plné [vlastní pole](#stavba-vlastniho-pole), když potřebujete nový stav, nový
pohled nebo nový markup.

---

<a id="shaping-the-value-a-field-stores"></a>
## Transformace stavu pole

Stav pole je to, co vyrobil jeho widget. Obvykle je to přesně to, co se má uložit —
ale ne vždy. Input date pickeru umí parsovat jen svůj vlastní formát, zatímco
sloupec chce jiný; upload drží dočasnou cestu, zatímco sloupec chce tu uloženou;
peněžní pole zobrazuje `1 234,50` a ukládá centy.

Dva kontrakty v `NyonCode\WireCore\Foundation\Contracts` pokrývají oba směry. Jsou
nezávislé — implementujte jen ten, který potřebujete:

| Kontrakt | Metoda | Kdy běží |
|---|---|---|
| `HydratesState` | `hydrateState($value, ?Model $record)` | hodnota z modelu → stav, po přetypování dle `getStateType()` |
| `DehydratesState` | `dehydrateState($state, ?Model $record)` | stav → ukládaná hodnota, při ukládání |

Všimněte si, že [`MoneyInput`](#stavba-vlastniho-pole) výše nepotřebuje *ani jeden*:
jeho stav už je ten integer, který ukládá, což plně vyjádří `getStateType(): 'int'`.
Kontrakty se vyplatí až tam, kde se stav a ukládaná hodnota opravdu liší — jako
zde, kde input zobrazuje plaintext a sloupec drží šifrovanou hodnotu:

```php
<?php

namespace App\Forms\Components;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Contracts\DehydratesState;
use NyonCode\WireCore\Foundation\Contracts\HydratesState;
use NyonCode\WireForms\Components\Field;

class EncryptedInput extends Field implements DehydratesState, HydratesState
{
    // Uložené → zobrazené. [tl! focus:start]
    public function hydrateState(mixed $value, ?Model $record = null): mixed
    {
        return $value === null || $value === '' ? $value : decrypt($value);
    }

    // Zobrazené → uložené.
    public function dehydrateState(mixed $state, ?Model $record = null): mixed
    {
        return $state === null || $state === '' ? $state : encrypt($state);
    } // [tl! focus:end]

    protected function viewName(): string
    {
        return 'forms.components.encrypted-input';
    }
}
```

Stejné dva kontrakty pohánějí i [editovatelné sloupce tabulky](../table/columns/editing.md) —
`TextInputColumn` je používá pro svůj trim/velikost písmen/čísla pipeline — takže
komponenta, která je implementuje, se chová stejně ve formuláři i v inline
editované buňce.

> **Oba směry, nebo žádný.** Pokud transformace hodnotu posouvá (převod timezone,
> změna jednotek), implementovat jen `hydrateState()` znamená, že se posunutý stav
> při uložení zapíše rovnou zpátky — a hodnota se s každým round tripem posune zas
> o kus dál. Jednostranná transformace je horší než žádná.

**`dehydrateState()` musí být čistá funkce svých argumentů.** Hostitel ji smí
zavolat víc než jednou za uložení — tabulka dehydratuje jednou bez záznamu, aby
mohla validovat před otevřením transakce, a pak znovu se zamčeným záznamem.
Hostitelé vždy předávají původní stav, nikdy výsledek dřívějšího volání, takže
i transformace, která by se dvojím uplatněním rozbila, je v bezpečí. `$record` je
`null`, když hostitel žádný nemá (create formulář); buňka tabulky ho má vždy.

---

<a id="hooking-into-the-save-lifecycle"></a>
## Zapojení do životního cyklu ukládání

Kontrakty výše patří *poli* — cestují s ním do každého formuláře. Dvě vrstvy níže
patří **formuláři** nebo **aplikaci**. Sáhněte po nich, když ta znalost není
polem: `DehydratesState` na „tohle pole vždycky ukládá centy", hook na „každý
formulář razítkuje tenanta".

Existují dvě vrstvy a skládají se:

**Per-form callbacky** — pro logiku lokální jednomu formuláři. Definované na instanci
`Form` a dokumentované v [Životní cyklus ukládání](save-lifecycle.md):

```php
$form
    ->mutateDataBeforeSave(fn (array $data) => [...$data, 'updated_by' => auth()->id()])
    ->beforeSave(fn (array $data) => /* … */)
    ->afterSave(fn ($record) => $record->notifySubscribers());
```

**Plugin hooky** — pro logiku, která by měla běžet pro *každý* formulář napříč aplikací,
bez zásahu do každé komponenty. Runtime emituje `form.saving` (před
perzistencí, může upravit data) a `form.saved` (po perzistenci,
observační):

```php
app(PluginManager::class)->hook('form.saving', function (array $payload): array {
    $payload['data']['tenant_id'] ??= auth()->user()->tenant_id;

    return $payload;
}, priority: -100);
```

Priority, typované hooky a plný tvar payloadu viz [Core Pluginy → Hook systém](../core/plugins.md#hook-system).
Per-form callback použijte pro jeden formulář; hook pro průřezové pravidlo.

---

<a id="packaging-fields-into-a-plugin"></a>
## Balíčkování polí do pluginu

Sekce výše staví pole uvnitř aplikace, kde třída plus Blade pohled
stačí — pole si resolvuje vlastní pohled, takže žádný registrační krok není potřeba.
Tato sekce pokrývá další krok: dodávat vlastní pole jako **znovupoužitelnou,
instalovatelnou jednotku** — doprovodný balíček, který si ostatní mohou `composer require`,
nebo sdílený modul uvnitř větší aplikace.

### Co „registrace pole" doopravdy znamená

**Neexistuje žádný registr typů polí.** Na rozdíl od sloupců tabulky, filtrů a akcí
— které mají metadatové registry `addColumnType()` / `addFilterType()` / `addActionType()`
— form pole se nikdy nehledají podle názvu. Pole je jen třída
a `render()` volá její `viewName()` přímo přes Laravel helper `view()`.
Takže zprovoznění balíčkovaného pole se scvrkává přesně na dvě věci:

1. **Třída pole je autoloadovatelná** — řeší to `autoload` blok Composeru
   vašeho balíčku, jako u jakékoli PHP třídy.
2. **Pohled pole se resolvuje** — jeho `viewName()` musí mířit na pohled, který Laravel
   najde. V balíčku to znamená zaregistrovat **view namespace**.

[Core plugin](../core/plugins.md) je vrstva navrch: je to místo, kde
nainstalujete průřezové extra — **presety (makra), save hooky a výchozí
konfiguraci** — takže je konzumenti dostanou registrací jedné třídy. Plugin je
pro prosté pole volitelný a povinný až když dodáváte makra nebo hooky.

> **Neregistrujte** pole pomocí `addColumnType()` (ani ostatních registrů typů).
> To jsou metadatové registry pro *tabulkové* sloupce/filtry/akce
> a při vykreslení form pole se nikdy nekonzultují. Registrace pole tam nedělá
> nic užitečného a je zavádějící.

### 1. Rozložení balíčku

Minimální balíček s polem vypadá takto:

```text
acme/wire-money-fields/
├── composer.json
├── src/
│   ├── AcmeMoneyServiceProvider.php
│   ├── AcmeMoneyPlugin.php
│   └── Components/
│       └── MoneyInput.php
└── resources/
    └── views/
        └── components/
            └── money-input.blade.php
```

`composer.json` autoloaduje namespace a registruje service provider, aby se
balíček bootoval automaticky:

```json
{
    "name": "acme/wire-money-fields",
    "require": {
        "nyoncode/wire-forms": "^0.1"
    },
    "autoload": {
        "psr-4": {
            "Acme\\WireMoney\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Acme\\WireMoney\\AcmeMoneyServiceProvider"
            ]
        }
    }
}
```

### 2. Pole namířené na namespaced pohled

Třída pole je identická s app verzí, kromě toho, že `viewName()` vrací
**namespaced** pohled (`namespace::path`), aby se resolvoval bez ohledu na to, kde
je balíček nainstalován:

```php
<?php

namespace Acme\WireMoney\Components;

use NyonCode\WireForms\Components\Field;

class MoneyInput extends Field
{
    protected string $currency = 'USD';

    public function currency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getStateType(): string
    {
        return 'int';
    }

    protected function viewName(): string
    {
        // "acme-money" je namespace registrovaný service providerem.
        return 'acme-money::components.money-input';
    }
}
```

### 3. Registrace view namespace v service provideru

Jediný povinný úkol service provideru je udělat `acme-money::` resolvovatelným.
Můžete ho zaregistrovat ručně pomocí `loadViewsFrom()` a `publishes()` pohledy,
aby konzumenti mohli přepsat váš markup:

```php
<?php

namespace Acme\WireMoney;

use Illuminate\Support\ServiceProvider;
use NyonCode\WireCore\Core\Plugin\PluginManager;

final class AcmeMoneyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Zaregistrovat plugin (makra, hooky, config), jakmile se manager resolvuje.
        $this->app->resolving(PluginManager::class, function (PluginManager $manager) {
            if (! $manager->has('acme-money')) {
                $manager->register($this->app->make(AcmeMoneyPlugin::class));
            }
        });
    }

    public function boot(): void
    {
        // Udělá acme-money::components.money-input resolvovatelným.
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'acme-money');

        // Nechat konzumenty přepsat markup přes vendor:publish.
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/acme-money'),
        ], 'acme-money::views');
    }
}
```

> Pokud je váš balíček postaven na `spatie/laravel-package-toolkit` (stejný toolkit,
> jaký používají Wire balíčky), volání `->hasViews()` ve vaší package konfiguraci
> zaregistruje namespace a publish tag za vás, odvozený z krátkého názvu
> balíčku — přesně jak je vystaveno `wire-forms::partials.field-wrapper-start`.

Jakmile je namespace registrovaný, pole je plně použitelné — `MoneyInput::make('price')`
funguje v jakémkoli schématu bez dalšího nastavení.

### 4. Plugin: presety, hooky a config

Plugin přidejte, když chcete dodat víc než třídu pole — například
znovupoužitelné **preset makro**, **save hook** nebo **výchozí konfiguraci**. Form
pole nejsou `Macroable`, takže field presety se dodávají jako statická factory nebo
podtřída (viz [Znovupoužitelné presety](#znovupouzitelne-presety)); plugin je místo, kde
registrujete `Table`/`Action` makra, hooky a config.

```php
<?php

namespace Acme\WireMoney;

use NyonCode\WireCore\Core\Plugin\Contracts\HasConfiguration;
use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\PluginManager;

final class AcmeMoneyPlugin implements HasConfiguration, Plugin
{
    public function getId(): string
    {
        return 'acme-money';
    }

    public function defaultConfig(): array
    {
        return ['default_currency' => 'USD'];
    }

    public function register(PluginManager $manager): void
    {
        // Průřezový save hook: zaokrouhlit každé money pole na celé centy.
        $manager->hook('form.saving', function (array $payload): array {
            foreach ($payload['data'] as $key => $value) {
                if (str_ends_with($key, '_cents') && is_numeric($value)) {
                    $payload['data'][$key] = (int) round($value);
                }
            }

            return $payload;
        });
    }

    public function boot(PluginManager $manager): void
    {
        // Config-driven výchozí hodnoty jsou zde dostupné, pokud je potřebujete:
        // $manager->getPluginConfig($this->getId())['default_currency']
    }
}
```

Plugin je automaticky zapojen callbackem `resolving()` service provideru v kroku 3,
takže konzumenti dostanou pole, jeho pohledy a jeho hooky jen instalací
balíčku. Registrační vzor a `has()` guard viz
[Core Pluginy → Registrace pluginů z balíčku](../core/plugins.md#registrace-pluginu-z-balicku).

### 5. Konzumenti ho instalují

Z konzumující aplikace je instalace jen Composer plus volitelné přepisy
pohledů:

```bash
composer require acme/wire-money-fields

# Volitelné: přepsat markup pole
php artisan vendor:publish --tag=acme-money::views
```

```php
use Acme\WireMoney\Components\MoneyInput;

$form->schema([
    MoneyInput::make('price_cents')->currency('EUR'),
]);
```

> **Plugin config tagy používají `::`** — `acme-money::views`, `acme-money::config` —
> ne pomlčku. Pomlčkový tag jako `acme-money-views` se neresolvuje na nic.

### Checklist

| Krok | Povinné pro | Mechanismus |
|------|-------------|-----------|
| Autoload třídy pole | Vždy | Composer `psr-4` |
| Registrace view namespace | Vždy (v balíčku) | `loadViewsFrom()` / `->hasViews()` |
| `viewName()` vrací `namespace::path` | Vždy (v balíčku) | Třída pole |
| Publikování pohledů | Volitelné (podpora přepisu) | `publishes(..., 'tag::views')` |
| Registrace pluginu | Jen pro makra/hooky/config | `resolving(PluginManager::class)` |
| Výchozí config | Jen pro konfigurovatelné pluginy | `HasConfiguration` |

---

<a id="js-backed-fields"></a>
## JS-based pole

Ve vestavěných polích existují dvě úrovně chování na straně klienta:

- **Inline Alpine.** Lehká interaktivita nepotřebuje samostatný bundle. `Slider`
  například řídí vše z `x-data` bloku a `@entangle`uje state path pole,
  s případným CSS inlinovaným jednou přes `@once`. Pro
  většinu vlastních polí je to vše, co potřebujete — viz
  `packages/forms/resources/views/components/slider.blade.php`.

  Dávejte ale pozor, kde je hranice: inline blok se posílá znovu **pro každou
  instanci na stránce**, takže tělo delší než pár řádků patří do registrované
  komponenty. Proto si date a time pickery, `Tags`, `Rating` a oba editory nechávají
  v markupu jen konfiguraci a volají factory z `wire-forms-fields.js` — jeden
  `DateTimePicker` stál 28,4 kB HTML na pole před přesunem a 14,5 kB po něm.

- **Předbundlovaný skript přes `@assets`.** Těžší pole (jako `TiptapEditor`) dodávají
  předsestavený JS bundle, který provider servíruje z routy
  (`/wire-forms/assets/{asset}.js`). Pohled pole ho injektuje Livewire
  direktivou `@assets`, takže skript běží i když se pole otevře uvnitř
  modalu — kde by prostý `<script>` tag injektovaný přes DOM morphing nikdy
  neběžel. Viz `packages/forms/resources/views/components/tiptap-editor.blade.php`:

  ```blade
  @assets
  <script src="{{ route('wire-forms.asset', ['asset' => 'tiptap']) }}"></script>
  @endassets
  ```

Pokud stavíte těžší JS-based pole ve vlastním balíčku, následujte stejný
vzor: zabundlujte skript, vystavte ho na routě a injektujte ho přes `@assets`
z pohledu pole, aby byl přítomný kdykoli se pole vykresluje.

**Alpine komponentu registrujte bezpodmínečně.** `alpine:init` proběhne přesně
jednou za dokument, takže bundle, který dorazí později — po `wire:navigate`,
s lazy vykresleným povrchem, uvnitř AJAXem načteného modalu — by se přihlásil
k eventu, jenž už nikdy nenastane, a nezaregistroval by nic; `x-data="myField(…)"`
pak spadne na `myField is not defined`. Každý bundle ve Wire používá tenhle idiom:

```js
let registered = false
const register = () => {
    if (registered || ! window.Alpine) return
    registered = true
    window.Alpine.data('myField', myField)
}
if (window.Alpine) register()
else document.addEventListener('alpine:init', register)
```

Guard `registered` není obranný detail: bundle se legitimně může na jedné stránce
vypsat dvakrát (per-surface include plus
[`@wireStackScripts`](../getting-started.md#javascriptove-assety)) a prohlížeč ho
oba dva krát spustí.

Pokud váš balíček dodává víc než občasné těžké pole, deklarujte bundle v
`configure()` vlastního balíčku místo pouhého per-surface includu —
`@wireStackScripts` ho pak vypíše vedle vlastních bundlů Wire:

```php
use NyonCode\WireCore\Foundation\Assets\Bundle;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;

class MyPackageServiceProvider extends PackageServiceProvider
{
    public const ASSETS_PATH = __DIR__.'/../dist';

    public function configure(Packager $packager): void
    {
        $packager
            ->bootedPackage(function () {
                Bundle::serve('my-package', self::ASSETS_PATH); // [tl! focus]
            })
            ->hasAssets('dist', entries: [
                Bundle::make('my-field.js'),                    // [tl! focus]
            ])
            ->hasAssetFallback(Bundle::servedByRoute('my-package')); // [tl! focus]
    }
}
```

Tři volání, jedno mapování. `Bundle::make()` deklaruje to, čím každý bundle Wire
je — klasický (nemodulový) skript, protože top-level deklarace ES modulu se nikdy
nedostanou na `window` a vaše registrace by tiše neudělala nic. Entries se klíčují
**jménem dodávaného souboru** relativně k adresáři assetů.

Zbylá dvě volání jsou dvě půlky fallbacku, tedy cesty, která se použije tam, kde
do `public/` nejde zapisovat a nic se nepublikovalo. `Bundle::serve()` registruje
routu — jmenuje se `{package}.asset`, odpovídá na `/{package}/assets/{id}.js` a
posílá s ní dlouhou cache hlavičku, kterou fallback potřebuje — a
`Bundle::servedByRoute()` je to, co na ni namíří vykreslený tag. Protože obě
strany vlastní jedna třída, id, které se dostane do URL, je totéž, které routa
přeloží zpátky na váš soubor: pojmenujte bundle `my-field.js`,
`my-package-field.js` nebo po samotném balíčku a všechny tři cesty tam a zpátky
sedí.

Těžká těla držte mimo stránky, které je nepotřebují, tak že je vynecháte z
`entries:` a necháte je dodat pole per-surface — ale nikdy ne malý controller,
který komponentu registruje.

---

## Testování vlastních polí

Pole se vykreslují do HTML, takže nejrychlejší testy asertují na výstup a konfiguraci.

```php
use App\Forms\Components\MoneyInput;

it('renders the currency symbol', function () {
    $field = MoneyInput::make('price')->currency('EUR');

    expect($field->getCurrency())->toBe('EUR')
        ->and($field->getStateType())->toBe('int')
        ->and((string) $field->toHtml())->toContain('EUR');
});
```

Pro end-to-end chování (vazba stavu, validace, uložení) protáhněte pole
uvnitř Livewire form komponenty s testovacími helpery Livewire, stejně jako
balíček testuje vestavěná pole. Spusťte je pomocí `composer test:forms`.

---

## Viz také

- [Reference Form Fields](fields/index.md) — každé vestavěné pole
- [Životní cyklus ukládání](save-lifecycle.md) — per-form save callbacky
- [Validace](validation.md) — sběr pravidel a zprávy
- [Core Pluginy](../core/plugins.md) — hooky, makra, registry typů, balíčkování
