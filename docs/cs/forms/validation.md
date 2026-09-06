---
order: 20
---

# Validace formulářů

Wire Forms poskytuje validaci na třech úrovních: pravidla na úrovni pole, pravidla na úrovni formuláře a programová validace přes Core ValidationPipeline.

---

## Pravidla na úrovni pole

Každé pole deklaruje svá vlastní validační pravidla:

```php
TextInput::make('name')
    ->required()
    ->maxLength(255)
    ->rules(['string', 'regex:/^[a-zA-Z\s]+$/']);

TextInput::make('email')      // [tl! focus:start]
    ->email()
    ->required()
    ->unique();                // [tl! focus:end]

TextInput::make('age')
    ->numeric()
    ->rules(['integer', 'min:18', 'max:120']);

Select::make('role')
    ->required()
    ->rules('in:admin,editor,viewer');
```

### Vestavěné helpery pravidel

Některá pole poskytují fluent helpery mapující na Laravel pravidla:

| Metoda | Ekvivalentní pravidlo |
|--------|-----------------|
| `->required()` | `required` |
| `->email()` | `email` |
| `->numeric()` | `numeric` |
| `->integer()` | `integer` |
| `->maxLength(255)` | `max:255` |
| `->minLength(3)` | `min:3` |
| `->url()` | `url` |
| `->tel()` | nastaví HTML input typ `tel` (žádné validační pravidlo) |

### Unikátní hodnoty

`unique()` sestaví laravelovské `Rule::unique()` z toho, co formulář už zná:
tabulku z navázaného modelu, sloupec z názvu pole a — v režimu editace —
vyloučí editovaný záznam z jeho vlastní kontroly.

```php
TextInput::make('email')->unique();                      // unique:users,email, ignoruje tohoto uživatele
TextInput::make('email')->unique(ignoreRecord: false);   // počítá se každý řádek, včetně tohoto
TextInput::make('tax_id')->unique(column: 'vat_number'); // pole, jehož název není sloupec
TextInput::make('name')->unique(table: 'companies');     // formulář bez ->model()
```

Pravidlo vzniká až při validaci, ne při deklaraci schématu. To je podstatné,
protože záznam předává formulářový runtime až poté, co vaše metoda `form()`
skončila — pravidlo vyhodnocené při deklaraci by neignorovalo nic právě na tom
formuláři, pro který bylo napsáno.

Kontrolu dále zúžíte přes `modifyRuleUsing`, které dostane pravidlo `Unique`:

```php
TextInput::make('email')
    ->unique(modifyRuleUsing: fn (Unique $rule) => $rule->where('team_id', $this->teamId));
```

Formulář bez `->model()` nemá z čeho tabulku odvodit, takže ji předejte: bez
jednoho i druhého vyhodí `unique()` výjimku `FormConfigurationException` s
názvem pole.

### Vlastní validační zprávy

```php
TextInput::make('name')
    ->required()
    ->validationMessages([
        'required' => 'Please enter a name.',
        'max' => 'Name is too long.',
    ]);
```

---

## Pravidla na úrovni formuláře

Přidejte pravidla na úrovni formuláře, která zahrnují více polí:

```php
Form::make()
    ->schema([
        TextInput::make('password')->password()->required()->rules(['confirmed']),
        TextInput::make('password_confirmation')->password()->required()->dehydrated(false),
    ])
    ->validationMessages([
        'password.confirmed' => 'Passwords do not match.',
    ]);
```

Potvrzovací pole má pravidlo, ale žádný sloupec za sebou, proto je označené
`dehydrated(false)`: účastní se validace a před zápisem záznamu vypadne. Bez
toho by uložení zkusilo nastavit na modelu atribut `password_confirmation` a
selhalo na chybějícím sloupci — viz
[Životní cyklus uložení](save-lifecycle.md#co-se-zapise-do-zaznamu).

---

## Programová validace

### validate()

Zvaliduje stav proti všem posbíraným pravidlům a vrátí zvalidovaná data:

```php
// V Livewire komponentě
public function save(): void
{
    $data = $this->form->validate();
    // $data obsahuje jen zvalidovaná pole
    // Při selhání vyhodí Illuminate\Validation\ValidationException
}
```

### getValidationRules()

Prohlédnout si posbíraná pravidla bez validace:

```php
$rules = $this->form->getValidationRules();
// ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email'], ...]
```

---

## Validace v životním cyklu uložení

Při volání `$form->save()` proběhne validace automaticky jako první krok:

```
save()
├── 1. Validace ← všechna pravidla polí + formuláře
├── 2. mutateDataBeforeSave()
├── 3. Plugin hook: form.saving
├── 4. beforeSave()
├── 5. Perzistence (create/update)
├── 6. Uložení relací
├── 7. afterSave()
├── 8. Plugin hook: form.saved
└── 9. Úspěšná notifikace
```

Pokud validace selže, `save()` vyhodí `ValidationException` a kroky 2-9 se přeskočí.

---

## Samostatná validace (bez Livewire)

```php
$form = Form::make()
    ->schema([
        TextInput::make('name')->required(),
        TextInput::make('email')->email()->required(),
    ])
    ->state(['name' => '', 'email' => 'not-an-email']);

try {
    $data = $form->validate();
} catch (ValidationException $e) {
    $errors = $e->errors();
    // ['name' => ['The name field is required.'], 'email' => ['The email field must be a valid email address.']]
}
```

## Podmíněná pravidla

Pravidla mohou používat closury pro dynamickou validaci. Closura dostane reaktivní
accessory `$get` / `$set` pole, takže pravidla mohou záviset na živém stavu
sousedů. Closura může obalit celou sadu pravidel, nebo sedět uvnitř pole pravidel
jako jedna položka:

```php
TextInput::make('company_name')
    ->required(fn (callable $get) => $get('type') === 'business')
    ->rules(fn (callable $get) => $get('type') === 'business' ? ['min:2'] : []);

// Closury mohou být i jednotlivé položky v poli pravidel:
TextInput::make('slug')->rules([
    'string',
    fn (callable $get) => $get('type') === 'business' ? 'required' : 'nullable',
]);
```

### Podmiňovací helpery

Fluent zkratky vyjadřují nejběžnější mezipolní podmínky bez psaní closury. Každá
porovnává živou hodnotu jiného pole; předání pole odpovídá „je jedno z".

| Metoda | Chování |
|--------|----------|
| `->requiredIf('type', 'business')` | required, když se `type` rovná hodnotě (nebo je jednou z pole) |
| `->requiredUnless('type', 'individual')` | required, pokud se `type` nerovná hodnotě |
| `->requiredWith('company')` | required, když má `company` neprázdnou hodnotu |
| `->visibleWhen('type', 'business')` | zobrazeno jen když `type` odpovídá |
| `->hiddenWhen('type', 'individual')` | skryté když `type` odpovídá |
| `->disabledWhen('locked', true)` | disabled když `locked` odpovídá |

```php
Select::make('department')
    ->visibleWhen('type', 'business')
    ->requiredIf('type', 'business');
```

`visibleWhen` / `hiddenWhen` / `disabledWhen` jsou sdílené foundation helpery,
takže jsou dostupné i na sloupcích, filtrech a akcích. Na surface bez kontextu
živého stavu jsou no-op (nechají komponentu viditelnou/zapnutou).

Skrytá pole se během validace přeskočí, takže pravidlo `required` na poli, které
uživatel aktuálně nevidí, nikdy nezablokuje odeslání.

---

## Live validace

Ve výchozím stavu formulář validuje jako celek při odeslání. Zapněte poli
per-field validaci během reaktivního roundtripu, aby se jeho chyba objevila (a
zmizela), jak uživatel interaguje, bez označení zbytku formuláře:

```php
TextInput::make('email')->email()->required()->validateLive();   // při každé změně
TextInput::make('name')->required()->validateOnBlur();           // když focus odejde
```

`validateLive()` zapne `live()` a `validateOnBlur()` zapne `live.blur` vazbu, takže
server vidí změnu a obnoví jen položku error bagu daného pole. Podmiňovací helpery
jako `requiredIf()` se ctí i live, protože čtou aktuální stav sousedů při každém
roundtripu. Live validace funguje i pro pole uvnitř `Repeater` položek — pole
každého řádku validuje proti své vlastní item path (např. `data.contacts.0.email`).

> Live validace kontroluje jedno pole po druhém. Pravidla, která porovnávají
> surové hodnoty sousedů přes řetězcovou syntax Laravelu (např.
> `required_if:other,value`), je nejlepší validovat při odeslání; pro reaktivní
> ekvivalent použijte `requiredIf()`.

---

## Zobrazení chyb

Validační chyby se automaticky navážou na Livewire error bag a zobrazí se vedle příslušných polí. Prefix state path se aplikuje automaticky:

```php
// Když statePath('data') a pole je TextInput::make('name')
// Klíč chyby: data.name
// Livewire zobrazí: @error('data.name')
```

V Blade není potřeba žádné manuální vykreslování chyb.
