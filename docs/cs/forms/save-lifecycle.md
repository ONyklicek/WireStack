---
order: 30
summary: Devět kroků mezi `save()` a uloženým záznamem a hook, který sedí u každého z nich.
---

# Životní cyklus ukládání

Metoda `Form::save()` vykonává striktní 9-krokovou pipeline. Každý krok je jasně definovaný s hooky pro přizpůsobení.

Tato stránka popisuje, co se stane při uložení formuláře.

---

## Přehled pipeline

```
Form::save()
│
├── 1. VALIDACE
│   ├── Posbírat pravidla ze všech polí
│   ├── Sloučit pravidla na úrovni formuláře
│   ├── Projít přes ValidationPipeline
│   └── Při selhání vyhodit ValidationException ← STOP
│
├── 2. MUTACE
│   ├── mutateDataBeforeSave(Closure $fn)
│   │   Transformovat zvalidovaná data před perzistencí
│   └── Vlastní dehydratace každého pole, pak jeho dehydrateStateUsing(Closure $fn)
│
├── 3. PLUGIN HOOK: form.saving
│   └── Pluginy mohou prohlédnout nebo upravit $data
│
├── 4. BEFORE SAVE
│   └── beforeSave(Closure $fn)
│       Void hook — side efekty, externí volání
│
├── 5. PERZISTENCE
│   ├── Zahodit, co není sloupec: dehydrated(false), relace, morph dvojice
│   ├── Výchozí: Model::create($data) nebo $model->update($data)
│   └── Vlastní: using(Closure $fn)
│
├── 6. ULOŽENÍ RELACÍ
│   └── RelationshipSaveHandler kaskáduje Repeater data do relací modelu
│
├── 7. AFTER SAVE
│   └── afterSave(Closure $fn)
│       Void hook — side efekty, vyčištění cache, události
│
├── 8. PLUGIN HOOK: form.saved
│   └── Pluginy pozorují uložený $record
│
└── 9. NOTIFIKACE
    ├── Odeslat úspěšnou notifikaci přes Notifications modul
    └── Přeskočit při disableSuccessNotification()
```

---

## Krok 1: Validace

Posbírá pravidla ze všech field komponent a zvaliduje aktuální stav.

```php
// Automatické v save()
// Lze také zavolat samostatně:
$data = $form->validate();
```

Pokud validace selže, vyhodí se `Illuminate\Validation\ValidationException`. Kroky 2-9 se úplně přeskočí.

Detaily o pravidlech polí, vlastních zprávách a ValidationPipeline viz [Validace](validation.md).

---

## Krok 2: Mutace dat

Transformujte zvalidovaná data, než se dostanou k modelu:

```php
$form->mutateDataBeforeSave(function (array $data): array {
    // Slugify titulku
    $data['slug'] = Str::slug($data['title']);

    // Odstranit dočasná pole
    unset($data['agree_to_terms']);

    // Zašifrovat citlivá data
    $data['ssn'] = encrypt($data['ssn']);

    return $data; // MUSÍ vrátit pole
});
```

Closura dostane pole zvalidovaných dat a **musí** vrátit upravené pole.

### Více mutací

```php
$form
    ->mutateDataBeforeSave(fn (array $data) => array_merge($data, [
        'updated_by' => auth()->id(),
    ]));
```

---

## Opačný směr: form.filling

Tahle stránka je cesta *ven*. Cesta dovnitř má svůj vlastní hook: `Form::fill()`
dispatchuje [`form.filling`](../core/plugins/hooks.md), takže nainstalovaný
balíček může změnit, s čím pole přijde — protějšek kroku `form.saving` níže.

```php
$manager->hook(Hook::FormFilling, function (FormFillingPayload $payload) {
    $payload->data['currency'] ??= auth()->user()->currency;   // [tl! focus]

    return $payload;
}, for: Invoice::class);
```

Vědomě se **nedispatchuje** z `getInitialState()`: to odpovídá na otázku, co
potřebuje ovládací prvek, než se cokoli naváže, a edit stránka volá obojí — hook
na obou by se spustil dvakrát na stránku.

---

## Krok 3: Plugin hook — form.saving

Vystřelí automaticky, když jsou registrované pluginy přes `PluginManager`. Pluginy mohou prohlédnout nebo upravit `$data` před perzistencí. Uživatelský kód s tímto krokem přímo neinteraguje.

---

## Krok 4: Before save

Void hook, který běží po mutaci, ale před perzistencí:

```php
$form->beforeSave(function (array $data): void {
    // Ověřit dostupnost externí služby
    if (! ExternalApi::isAvailable()) {
        throw new \RuntimeException('External service is down');
    }

    // Odeslat pre-save událost
    event(new UserSaving($data));
});
```

Closura dostane zmutovaná data, ale **nevrací** je.

Pokud tento hook vyhodí výjimku, perzistence (krok 5) se přeskočí.

---

## Krok 5: Perzistence

### Výchozí chování

Logika perzistence závisí na režimu modelu:

```php
// Create mód — model je řetězec třídy
$form->model(User::class);
// → User::create($data)

// Edit mód — model je instance
$form->model($user);
// → $user->update($data)
```

<a id="custom-persistence"></a>
### Co se zapíše do záznamu

Mezi zvalidovanými daty a zápisem každé pole říká, jestli je jeho hodnota vůbec
sloupec a jaká ta hodnota má být. Dva háčky, oba na libovolném poli:

```php
TextInput::make('password_confirmation')->dehydrated(false);
TextInput::make('password')->dehydrateStateUsing(fn (string $state) => Hash::make($state));
```

- **`dehydrated(false)`** nechá klíč mimo zápis. Pole s pravidlem a bez sloupce
  za sebou — potvrzení hesla, přepínač „stejné jako fakturační adresa“, hodnota,
  která jen řídí sousední pole — by se jinak nastavilo jako atribut a selhalo na
  chybějícím sloupci.
- **`dehydrateStateUsing()`** nahradí hodnotu na cestě ven. Dostane `$state` a
  záznam (`null` v režimu vytváření).

Pořadí je pevné a záleží na něm: nejdřív hodnotu tvaruje typ pole — `FileUpload`
přesune dočasný upload do trvalého úložiště, `DateTimePicker` aplikuje svůj
formát a časovou zónu — a vaše callback pak vidí ten výsledek, ne surovou
hodnotu z prohlížeče. Oba háčky platí i pro pole uvnitř `Repeateru`, pro každou
položku.

Podmínka může být Closure nad živým stavem sousedních polí, vyhodnocená při
uložení:

```php
Toggle::make('has_nickname'),
TextInput::make('nickname')->dehydrated(fn (callable $get) => (bool) $get('has_nickname')),
```

Některá pole si na tuhle otázku odpovídají sama, protože jejich název od začátku
není sloupec: `Repeater` nebo `Tags` navázané přes `->relationship()` a
`MorphToSelect` (jehož hodnotou je dvojice `{name}_type` / `{name}_id`). Z
rodičovského zápisu vypadnou a jdou vlastní cestou — není co nastavovat.

Všechno vypadává **až u zápisu**. `mutateDataBeforeSave()`, hook `form.saving`
i `beforeSave()` pořád vidí kompletní pole dat a stejně tak kaskáda relací
v kroku 6.

### Stejné transformace v action modalu

Stav formuláře odchází dvěma dveřmi. `save()` ho zapíše do záznamu;
[akce](../core/actions/index.md) s `->form()` ho místo toho předá callbacku:

```php
Action::make('publish')
    ->form([
        Select::make('status')->options(Status::class)->placeholder('None'),
        TextInput::make('price')->numeric(),
        DateTimePicker::make('published_at'),
    ])
    ->action(fn (array $data) => $record->update($data)); // [tl! focus]
```

Obojí dveře aplikují stejnou dehydrataci, takže `$data` tady drží přesně to, co
by zapsalo uložení: `null` za vymazaný select i vyprázdněné číselné pole, storage
formát a časovou zónu u data, uloženou cestu u uploadu a nakonec váš vlastní
`dehydrateStateUsing()`. Kroky wizardu sdílejí jeden data bag a dehydratuje se
každý z nich — ne jen ten, který je při odeslání na obrazovce.

Proběhne to jednou, v místě předání, a až po validaci. Živý stav, na který je
modal navázaný, si drží syrovou hodnotu — pole se pod otevřeným modalem nehne a
opakované odeslání dehydratuje ze stejného výchozího bodu, ne z už
transformovaného.

Totéž platí pro třetí dveře — [halt](../core/actions/lifecycle.md#halt-vykonavani), který
nese formulář: potvrzením se akce vykoná znovu a hodnoty, které halt posbíral,
dehydratují pole jeho vlastního formuláře. Klíče, které si halt přinesl z prvního
pokusu, zůstanou nedotčené — halt formuláři patří jen to, co sám deklaruje.

### Vlastní perzistence

Přepište výchozí pomocí `using()`:

```php
$form->using(function (array $data): mixed {
    // Vytvořit
    $user = User::create($data);
    $user->assignRole($data['role']);
    return $user;
});
```

Callback `using()` nahrazuje celou výchozí create/update logiku. Dostane `$data` (pole zmutovaných dat). Návratová hodnota se stane výsledkem `save()`.

**Relační repeatery a `using()`.** `$data` obsahuje hodnotu každého pole, **včetně relačních `Repeater` polí** (např. klíč `children` pro `Repeater::make('children')->relationship('children')`). Výchozí cesta perzistence tyto klíče před zápisem rodiče odstraní; `using()` ne. Takže:

- **Neprovádějte** hromadné přiřazení celého `$data` — `User::create($data)` by se pokusil zapsat `children` jako sloupec. Přiřaďte jen vlastní atributy rodiče.
- **Vraťte uložený `Model`** a kaskáda relací (krok 6) stále poběží a uloží za vás řádky repeateru do relace:

```php
$form->using(fn (array $data) => User::create(['name' => $data['name']]));
// → řádky children repeateru se nakaskádují na $user->children()
```

- Vraťte **cokoli jiného než `Model`** (id, DTO, výsledek příkazu) a kaskáda se přeskočí — váš callback vlastní perzistenci úplně, včetně relací.

### Žádný model

Pokud je nastaveno `model(null)` a není poskytnut callback `using()`, `save()` vyhodí `InvalidArgumentException`.

<a id="optimistic-locking"></a>
### Optimistic locking

Souběžné editace se mohou tiše navzájem přepsat: dva uživatelé otevřou stejný záznam, oba uloží a druhý zápis přepíše první. Zapněte kontrolu verze pomocí `optimisticLock()`:

```php
$form->model($order)->optimisticLock();          // výchozí sloupec 'updated_at'
$form->model($order)->optimisticLock('version'); // nebo libovolný integer/version sloupec
```

Když je zapnuto, hodnota lock sloupce se zachytí při naplnění formuláře ze záznamu a nese se přes Livewire round trip. Při uložení se **znovu načte aktuální databázová hodnota**; pokud už neodpovídá zachycené baseline — někdo jiný záznam mezitím uložil nebo smazal — uložení se přeruší s `NyonCode\WireForms\Forms\Runtime\StaleModelException` a konfliktní notifikací (`wire-forms::messages.stale`), přičemž novější data zůstanou nedotčená.

- Opt-in a zpětně kompatibilní — bez `optimisticLock()` se nic nemění.
- Běží jen v režimu **update** (existující model).
- Nastavte `->model($record)` **před** `->fill()`, aby se baseline mohla zachytit; pokud baseline chybí, kontrola selže open (uložení nezablokuje).
- Odchyťte `StaleModelException`, pokud chcete konflikt řešit sami (např. znovu načíst a znovu předložit formulář):

```php
use NyonCode\WireForms\Forms\Runtime\StaleModelException;

try {
    $this->form->save();
} catch (StaleModelException $e) {
    // $e->model, $e->lockColumn — znovu načíst a nechat uživatele zkusit znovu
}
```

---

## Krok 6: Uložení relací

Po perzistenci modelu `RelationshipSaveHandler` nakaskáduje data Repeater polí do relací modelu. Tento krok běží jen když je výsledek perzistence instance Eloquent `Model` — což zahrnuje `Model` vrácený z vlastního callbacku `using()` (viz [Vlastní perzistence](#vlastni-perzistence)).

Uživatelský kód s tímto krokem přímo neinteraguje; řeší se automaticky pro Repeater pole s nakonfigurovaným `->relationship()`.

---

## Krok 7: After save

Void hook, který běží po úspěšné perzistenci:

```php
$form->afterSave(function (mixed $record): void {
    // $record je vytvořený/aktualizovaný Model (nebo návratová hodnota using())
    Cache::forget("user:{$record->id}");

    // Odeslat událost
    event(new UserSaved($record));

    // Odeslat notifikaci
    $record->notify(new WelcomeNotification());
});
```

Dostane `$record` — návratovou hodnotu kroku perzistence (typicky instanci Modelu).

---

## Krok 8: Plugin hook — form.saved

Vystřelí po `afterSave`, aby pluginy mohly pozorovat uložený záznam. Uživatelský kód s tímto krokem přímo neinteraguje.

---

## Krok 9: Notifikace

Odešle úspěšnou notifikaci přes Notifications modul:

```php
// Vlastní zpráva
$form->successMessage('User saved successfully!');

// Vypnout úplně
$form->disableSuccessNotification();
```

Notifikace se odešle přes `NotificationManager` s aktivním driverem (session, Livewire, Flasher atd.).

Tento krok vystřelí jen když:
1. Notifications modul je dostupný (kontrola `app()->bound()`)
2. `disableSuccessNotification()` NEBYLO zavoláno
3. Uložení proběhlo bez výjimek

---

## Kompletní příklad

```php
class EditUser extends Component
{
    use WithForms;

    public User $user;
    public array $data = [];

    public function mount(User $user): void
    {
        $this->user = $user;
        $this->form->fill($user->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model($this->user)
            ->schema([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('email')->email()->required(),
                Select::make('role')
                    ->options(['admin' => 'Admin', 'editor' => 'Editor'])
                    ->required(),
                Toggle::make('active'),
            ])
            ->mutateDataBeforeSave(function (array $data): array { // [tl! focus:start]
                $data['updated_by'] = auth()->id();
                return $data;
            })
            ->beforeSave(function (array $data): void {
                Log::info('Updating user', ['id' => $this->user->id]);
            })
            ->afterSave(function (mixed $record): void {
                Cache::forget("user:{$record->id}");
                event(new UserUpdated($record));
            })
            ->successMessage('User updated.'); // [tl! focus:end]
    }

    public function save(): void
    {
        $this->form->save();
        $this->redirect(route('users.index'));
    }
}
```

---

## Ošetření chyb

| Výjimka | Kdy | Efekt |
|-----------|------|--------|
| `ValidationException` | Krok 1 selže | Kroky 2-9 přeskočeny, chyby zobrazeny v UI |
| Jakýkoli `Throwable` | Kroky 2-8 vyhodí | Pipeline se přeruší, žádná notifikace |
| `InvalidArgumentException` | Žádný model + žádný `using()` | Krok 5 selže |

Save pipeline se ve výchozím stavu **neobaluje** do databázové transakce. Pokud potřebujete atomicitu, obalte do `DB::transaction()`:

```php
public function save(): void
{
    DB::transaction(fn () => $this->form->save());
}
```
