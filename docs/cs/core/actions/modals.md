---
order: 20
summary: "Na co se akce zeptá, než se spustí — věta, formulář, read-only infolist nebo wizard — a co se stane, když jeden modal otevře další."
---

# Modaly akcí

Akce se může zeptat, než se spustí, a ptá se modalem: větou a dvěma tlačítky,
formulářem k vyplnění, read-only infolistem ke kontrole nebo wizardem kroků.
Všechny čtyři jsou tentýž povrch jinak nastavený — ani jeden z nich není druhý
druh akce a callback na konci je ten, který jste už napsali.

## Potvrzovací modal

```php
Action::make('delete')
    ->requiresConfirmation()
    ->modalHeading('Delete this record?')
    ->modalDescription('This action cannot be undone.')
    ->modalIcon('trash', 'danger')
    ->modalSubmitActionLabel('Yes, delete')
    ->modalCancelActionLabel('Cancel')
    ->action(fn ($record) => $record->delete());
```

## Slide-over

```php
Action::make('details')
    ->slideOver()
    ->stickyHeader()
    ->stickyFooter()
    ->modalMaxHeight('60vh');
```

## Vzhled modalu

```php
Action::make('edit')
    ->modalWidth('2xl')              // sm, md, lg, xl, 2xl, 3xl, 4xl, 5xl
    ->closeModalOnClickAway()
    ->closeModalOnEscape()
    ->slideOverOnMobile()            // slide-over na mobilu, modal na desktopu
    ->fullScreenOnMobile();          // celá obrazovka na mobilu
```

## Modal s formulářem

Když je nainstalováno `wire-forms`, akce mohou zobrazit modaly s formulářem:

```php
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Select;

Action::make('edit')
    ->form([
        TextInput::make('name')->required(),
        Select::make('role')->options([
            'admin' => 'Admin',
            'editor' => 'Editor',
        ]),
    ])
    ->fillFormUsing(fn ($record) => $record->only(['name', 'role']))
    ->action(fn ($record, array $data) => $record->update($data));
```

Closura může vrátit případ enumu rovnou z castovaného atributu (`fn ($record) => ['role' => $record->role]`): naplněný bag každý enum srazí na jeho backing hodnotu, protože právě ta putuje ve stavu Livewiru do prohlížeče a právě proti ní `Select` porovnává hodnoty svých `<option>`. Uložení pak proběhne zpátky přes cast.

`$data` dorazí **dehydratovaná**, přesně tak, jak by je zapsalo `Form::save()`: vymazaný `Select` nebo vyprázdněný číselný `TextInput` je `null`, ne `''`, `DateTimePicker` nese storage formát a časovou zónu, `FileUpload` uloženou cestu a případný váš `dehydrateStateUsing()` je aplikovaný. Proto je `$record->update($data)` výše bezpečné vůči enum i číselnému castu. Viz [co dorazí k záznamu](../../forms/save-lifecycle.md#stejne-transformace-v-action-modalu).

Modal formuláře `HeaderAction` **nemá záznam**, takže jeho closura `fillFormUsing` nebere žádné argumenty. Použijte ji k naplnění počátečního stavu — a array-typovaná pole (`CheckboxList`, `Tags`, multiple `Select`) vždy naplňte prázdným polem, aby se správně navázala od první interakce:

```php
HeaderAction::make('create')
    ->form([
        TextInput::make('name')->required(),
        CheckboxList::make('permissions')->options($permissions)->bulkToggleable(),
    ])
    ->fillFormUsing(fn () => ['name' => '', 'permissions' => []])
    ->action(fn (array $data) => Role::create($data));
```

## Modal s infolistem

Použijte `->infolist()` k otevření **read-only** modalu, který zobrazuje záznam — protějšek `->form()`. Záznam akce se naváže automaticky, modal není potvrzení a ukazuje jen tlačítko zavření (žádné submit). Kompletní referenci entries viz [Infolisty](../infolists/index.md).

```php
use NyonCode\WireCore\Actions\ViewAction;
use NyonCode\WireCore\Infolists\Components\TextEntry;

ViewAction::make()
    ->slideOver()
    ->infolist([
        TextEntry::make('name')->weight('bold'),
        TextEntry::make('email')->copyable(),
        TextEntry::make('created_at')->dateTime()->since(),
    ]);
```

## Vícekrokový wizard

```php
use NyonCode\WireCore\Actions\ModalStep;

Action::make('create')
    ->steps([
        ModalStep::make('Basic Info')
            ->description('Enter user details')
            ->icon('user')
            ->schema([
                TextInput::make('name')->required(),
                TextInput::make('email')->email()->required(),
            ]),

        ModalStep::make('Settings')
            ->schema([
                Select::make('role')->options([...]),
                Toggle::make('active'),
            ]),

        ModalStep::make('Review')
            ->schema([
                Placeholder::make('summary'),
            ]),
    ])
    ->action(fn ($record, $data) => $record->update($data));
```

`->schema()` kroku přijímá Closure pro sestavení polí z dat zadaných v
dřívějších krocích — `->schema(fn (array $data) => [...])`. Closura dostane živý
form-data bag i pro `HeaderAction` (která nemá záznam). Rozpracovaný příklad viz
[Vícekrokový wizard](modals.md#vicekrokovy-wizard).

## Akce v patičce

```php
use NyonCode\WireCore\Actions\ModalFooterAction;

Action::make('edit')
    ->form([...])
    ->modalFooterActions([
        ModalFooterAction::make('save')
            ->label('Save')
            ->color('primary')
            ->submitsForm(),
        ModalFooterAction::make('save-and-close')
            ->label('Save & Close')
            ->action(fn () => $this->saveAndClose()),
    ]);
```

## Vrstvené (vnořené) modaly

Otevření akce, když už je nějaký modal otevřený, **navrství** nový modal nad ten
současný místo jeho nahrazení. Každý otevřený modal je **živý rámec**: rodič zůstává
za aktivním modalem plně reaktivní formulář (ztlumený a klik-inertní, ale stále se
překresluje) a zavření vrchního modalu vás vrátí zpět na rodiče včetně zachovaných dat
formuláře. Není k tomu potřeba žádné speciální API — jakýkoli callback, který dostane
hostitele `$component` (akce v patičce, akce pole, akce infolistu), může otevřít další
akci a ta se prostě navrství:

```php
Action::make('editOrder')
    ->modalHeading('Upravit objednávku')
    ->form([
        TextInput::make('reference')->required(),
        Select::make('customer_id')->options($customers),
    ])
    ->modalFooterActions([
        // Otevře druhý modal nad „Upravit objednávku". Rodič zůstane otevřený
        // za ním; zavření potomka vás vrátí sem s nedotčeným formulářem.
        ModalFooterAction::make('newCustomer')
            ->label('Nový zákazník')
            ->icon('plus')
            ->action(fn ($component) => $component->mountAction('createCustomer')), // [tl! focus]
    ])
    ->action(fn (array $data) => $this->saveOrder($data));

Action::make('createCustomer')
    ->modalHeading('Vytvořit zákazníka')
    ->form([TextInput::make('name')->required()])
    ->action(fn (array $data) => Customer::create($data));
```

V tabulce otevřete vnořený modal z akce úplně stejně — hostitel se předává jako
`$component`:

```php
Action::make('review')
    ->modalHeading('Kontrola')
    ->modalFooterActions([
        ModalFooterAction::make('flag')
            ->label('Označit k dořešení')
            ->action(fn ($component, $record) => $component->openActionModal((string) $record->getKey(), 'addFlag')), // [tl! focus]
    ]);
```

Vnořená akce může být v hlavním seznamu, nebo ji deklarujete **inline** hned vedle
akce, která ji otevírá, přes `registerActions()` — resolver ji najde podle jména
tak či tak:

```php
Action::make('editOrder')
    ->registerActions([                                            // [tl! focus]
        Action::make('createCustomer')->form([...])->action(...),  // [tl! focus]
    ])                                                             // [tl! focus]
    ->modalFooterActions([
        ModalFooterAction::make('newCustomer')
            ->action(fn ($component) => $component->mountAction('createCustomer')),
    ]);
```

### Vrácení dat rodiči

Protože je každá úroveň živý rámec jedné komponenty, může vnořená akce zapsat data
přímo zpět do formuláře předka. Každý callback akce i patičkové akce dostane vedle
obvyklých `$data`/`$record`/`$component` tyto vazby:

- `$set(cesta, hodnota)` — zápis do dat vlastního rámce.
- `$setParent(cesta, hodnota)` — zápis do dat **rodičovského** rámce.
- `$parentData` — čtení aktuálních dat rodičovského rámce.
- `$setFrame(hloubka, cesta, hodnota)` — zápis do libovolného rámce dle hloubky (pro pokročilé).
- `$arguments` — libovolné pole, které jste předali do `mountAction($name, [...])`.

Zásobník je zastropovaný na `ModalStack::MAX_DEPTH` (8). Je to pojistka proti
patologické rekurzi — callbacku, který otevírá modal ve smyčce — ne limit, na
který někdo narazí návrhem toku: otevření dalšího nad tuhle mez je odmítnuto, ne
potichu zahozeno.

To je kanonický vzor „vytvořte + vyberte“ — podformulář, který naplní pole ve formuláři,
z něhož byl otevřen:

```php
Action::make('editOrder')
    ->form([
        TextInput::make('reference')->required(),
        Select::make('customer_id')->options(fn () => Customer::pluck('name', 'id')),
    ])
    ->modalFooterActions([
        ModalFooterAction::make('newCustomer')
            ->label('Nový zákazník')->icon('plus')
            ->action(fn ($component) => $component->mountAction('createCustomer')),
    ])
    ->action(fn (array $data) => $this->saveOrder($data));

Action::make('createCustomer')
    ->modalHeading('Vytvořit zákazníka')
    ->form([TextInput::make('name')->required()])
    // Vytvoří záznam, předá jeho id do rodičovského Selectu a zavře se.
    ->action(function (array $data, $setParent) {                 // [tl! focus]
        $customer = Customer::create($data);                      // [tl! focus]
        $setParent('customer_id', $customer->id);                 // [tl! focus]
    });                                                           // [tl! focus]
```

Zápis překreslí celý zásobník, takže rodičovský `Select` zobrazí novou hodnotu ve
chvíli, kdy se potomek zavře.

Poznámky k chování:

- **Vrstvěte tak hluboko, jak potřebujete** — každá úroveň se vrství nad předchozí se
  zvyšujícím se `z-indexem`; jediné pozadí (scrim) překryje vše pod vrchním modalem, takže
  hluboký zásobník nikdy neztmavne do černa. (Pojistný strop chrání před nekonečnou rekurzí.)
- **Rodič zůstává živý** — interaktivní je jen vrchní modal, ale každý rodič pod ním se
  dál překresluje, takže zápis `$setParent(...)` se za aktivním modalem objeví okamžitě.
- **Zavření vrací k rodiči.** `Escape`, tlačítko zavřít, kliknutí na pozadí i akce
  v patičce, která modal zavírá, popnou jen **vrchní** modal a obnoví rodiče. Poslední
  zavření vyprázdní celý zásobník.
- **Data formuláře jsou zachována** pro každou úroveň, takže rodičovský modal zůstane
  přesně tak, jak jste ho opustili.
- Akce v patičce, která vnořený modal *otevře*, se poté automaticky **nezavírá**, takže
  modal, který otevřela, zůstane navrchu.

### Navigace v zásobníku

Dvě další bindings v callbacku skládají hluboké flow bez přidávání další vrstvy:

- `$replace(jméno, arguments = [])` — vymění **aktivní** modal za jiný **na místě**.
  Vrchní rámec se popne a pojmenovaná akce se namountuje ve stejné hloubce, takže rodiče
  zůstanou nedotčení. Použijte pro pohyb *uvnitř* modalu — tlačítko „zpět na první krok“
  nebo výměnu edit modalu za potvrzovací — místo navršení další úrovně. Záznam u řádkové
  akce se dědí automaticky (přepište přes `record`/`recordKey` v `arguments`).
- `$cancelParents(?upTo = null)` — zavře aktivní modal **i jeho rodiče**. Bez argumentu
  zahodí celý zásobník (jedno „Zrušit vše“); s názvem akce odvine až po nejbližšího
  předka s tím názvem (včetně něj).

```php
Action::make('editOrder')
    ->form([/* … */])
    ->modalFooterActions([
        // Vymění tento modal za potvrzení, na místě — žádná další vrstva.
        ModalFooterAction::make('archive')
            ->label('Archivovat…')
            ->action(fn ($replace) => $replace('confirmArchive')),        // [tl! focus]
        // Zahodí celé vnořené flow naráz.
        ModalFooterAction::make('discard')
            ->label('Zahodit vše')
            ->action(fn ($cancelParents) => $cancelParents()),            // [tl! focus]
    ])
    ->action(fn (array $data) => $this->saveOrder($data));
```

Obojí jsou i veřejné metody (`$this->replaceMountedAction(...)`, `$this->cancelParentActions(...)`),
takže je můžete volat přímo z `wire:click` nebo z `$component`.

## Související

- [Akce](index.md) — třídy, kterým tyhle modaly patří
- [Modaly](../modals.md) — samotné třídy modalů, použité bez akce
- [Formuláře](../../forms/overview.md) — schéma, které modal s formulářem vykreslí
- [Infolisty](../infolists/index.md) — co ukazuje modal s infolistem
- [Životní cyklus a fronty](lifecycle.md) — co běží po odeslání modalu
