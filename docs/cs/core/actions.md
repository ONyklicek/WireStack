---
order: 10
---

# Akce

Modul Actions poskytuje řádkové, hromadné a hlavičkové akce pro tabulky a související UI toky.

## Typy akcí

| Třída | Případ užití | Callback dostane |
|-------|----------|-------------------|
| `Action` | Řádková akce — jeden záznam | `fn (Model $record, array $data)` |
| `BulkAction` | Vybrané záznamy | `fn (Collection $records, array $data)` |
| `HeaderAction` | Hlavička tabulky — bez kontextu záznamu | `fn (array $data)` |
| `ActionGroup` | Seskupuje akce do dropdownu | — |

Všechny rozšiřují `BaseAction` a sdílejí stejné fluent API pro label, ikonu, barvu, velikost, modal, životní cyklus.

## Předpřipravené akce

| Třída | Popis |
|-------|-------------|
| `DeleteAction` | Smazání jednoho záznamu s potvrzením |
| `DeleteBulkAction` | Hromadné smazání s potvrzením |
| `RestoreBulkAction` | Hromadné obnovení soft-smazaných záznamů, s potvrzením |
| `ForceDeleteBulkAction` | Hromadné trvalé smazání soft-smazaných záznamů, s potvrzením |
| `EditAction` | Otevře edit modal/formulář |
| `ViewAction` | Otevře view modal |

```php
use NyonCode\WireCore\Actions\DeleteAction;
use NyonCode\WireCore\Actions\DeleteBulkAction;

$table->actions([DeleteAction::make()])
      ->bulkActions([DeleteBulkAction::make()]);
```

Každý preset dodává label, ikonu, barvu a potvrzovací modal; chování
dodáte pomocí `->action()`. Soft-delete presety se párují s tabulkou zúženou na
trashed záznamy (např. `->query(User::onlyTrashed())`):

```php
use NyonCode\WireCore\Actions\ForceDeleteBulkAction;
use NyonCode\WireCore\Actions\RestoreBulkAction;

$table->bulkActions([
    RestoreBulkAction::make()->action(fn ($records) => $records->each->restore()),
    ForceDeleteBulkAction::make()->action(fn ($records) => $records->each->forceDelete()),
]);
```

## Základní použití

```php
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Actions\HeaderAction;

// Řádková akce
Action::make('edit')
    ->label('Edit')
    ->icon('pencil')
    ->color('primary')
    ->url(fn (User $record) => route('users.edit', $record)) // [tl! focus]

// Řádková akce s callbackem
Action::make('archive')
    ->label('Archive')
    ->icon('archive')
    ->action(fn (User $record) => $record->update(['archived' => true])) // [tl! focus]
    ->successNotification('Archived!')

// Hromadná akce
BulkAction::make('export')
    ->label('Export Selected')
    ->icon('download')
    ->action(fn (Collection $records) => Excel::download($records)) // [tl! focus:start]
    ->deselectRecordsAfterCompletion() // [tl! focus:end]

// Hlavičková akce
HeaderAction::make('create')
    ->label('New User')
    ->icon('plus')
    ->url(route('users.create'))
    ->badge(fn () => User::whereNull('verified_at')->count()) // [tl! focus:start]
    ->badgeColor('danger') // [tl! focus:end]
```

## Skupiny akcí

Sbalte sekundární akce do dropdown menu. Na telefonu se menu otevře jako
bottom sheet — přepište pomocí `->sheetOnMobile(false)` / `->mobileBreakpoint('md')`;
viz [mobilní prezentace](../configuration.md#mobil).

```php
use NyonCode\WireCore\Actions\ActionGroup;

$table->actions([
    Action::make('edit')->icon('pencil'),

    ActionGroup::make('more', [
        Action::make('duplicate')
            ->icon('copy')
            ->action(fn ($record) => $record->replicate()->save()),
        Action::make('archive')
            ->icon('archive')
            ->action(fn ($record) => $record->archive()),
        Action::divider(),                    // vizuální oddělovač
        Action::make('delete')
            ->icon('trash')
            ->color('danger')
            ->requiresConfirmation()
            ->action(fn ($record) => $record->delete()),
    ])->divided(),                            // auto-vložit oddělovače mezi položky
]);
```

Skupiny podporují `badge()` a `badgeColor()` stejně jako HeaderAction.

## Dynamické vlastnosti

Všechny vlastnosti podporují Closury — vyhodnocené per-záznam v čase renderu:

```php
Action::make('toggle')
    ->label(fn (User $record) => $record->is_active ? 'Deactivate' : 'Activate')
    ->color(fn (User $record) => $record->is_active ? 'danger' : 'success')
    ->icon(fn (User $record) => $record->is_active ? 'x' : 'check')
    ->hidden(fn (User $record) => $record->trashed())
```

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

Použijte `->infolist()` k otevření **read-only** modalu, který zobrazuje záznam — protějšek `->form()`. Záznam akce se naváže automaticky, modal není potvrzení a ukazuje jen tlačítko zavření (žádné submit). Kompletní referenci entries viz [Infolisty](infolists.md).

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

To je kanonický vzor „vytvoř + vyber" — podformulář, který naplní pole ve formuláři,
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
  zůstanou nedotčení. Použij pro pohyb *uvnitř* modalu — tlačítko „zpět na první krok"
  nebo výměnu edit modalu za potvrzovací — místo navršení další úrovně. Záznam u řádkové
  akce se dědí automaticky (přepiš přes `record`/`recordKey` v `arguments`).
- `$cancelParents(?upTo = null)` — zavře aktivní modal **i jeho rodiče**. Bez argumentu
  zahodí celý zásobník (jedno „Zrušit vše"); s názvem akce odvine až po nejbližšího
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
takže je můžeš volat přímo z `wire:click` nebo z `$component`.

## Lifecycle hooky

```php
Action::make('publish')
    ->before(fn ($record) => $record->validate())
    ->action(fn ($record) => $record->update(['status' => 'published']))
    ->after(fn ($record) => event(new Published($record)))
    ->successNotification('Published!')
    ->failureNotification('Publish failed.');
```

## Halt vykonávání

Halt pozastaví vykonávání a zobrazí sekundární modal pro potvrzení uživatelem:

```php
Action::make('process')
    ->before(function ($record, Action $action) {
        if ($record->has_warnings) {
            $action->halt()
                ->modalHeading('Warnings Detected')
                ->modalDescription('There are unresolved warnings. Continue anyway?');
        }
    })
    ->action(fn ($record) => $record->process());
```

## Icon button

```php
Action::make('edit')
    ->icon('pencil')
    ->iconButton()          // vykreslí jako tlačítko jen s ikonou
    ->tooltip('Edit record');

// Nebo skrýt jen label
Action::make('edit')
    ->icon('pencil')
    ->hideLabel();
```

## URL akce

```php
Action::make('view')
    ->url(fn ($record) => route('users.show', $record), openInNewTab: true);

// Řetězcová URL
Action::make('docs')
    ->url('/docs', openInNewTab: true);
```

## Klávesové zkratky

```php
Action::make('save')->keyboardShortcut('mod+s');
Action::make('delete')->keyboardShortcut('Delete');
```

Používá pod kapotou Alpine.js `@keydown`.

## Outlined a velikost

```php
Action::make('cancel')
    ->outlined()                    // outline varianta místo výchozí solid výplně
    ->color('gray')
    ->size('sm');                   // xs, sm, md, lg
```

## Tiché řádkové akce

Ve výchozím stavu se řádkové akce tabulky vykreslují jako plná, stále barevná
tlačítka. Nastav styl akcí tabulky na `quiet` pro klidnější, profesionálnější
vzhled — akce v klidu vypadají jako neutrální text a barvu odhalí až na hoveru
nebo klávesovém focusu, takže řádek plný akcí přestane soupeřit s daty.

```php
$table->actionsStyle('quiet'); // výchozí je 'solid'
```

Chování tichého stylu:

- Nedestruktivní akce jsou v klidu neutrálně šedé a svou `->color()` získají na hoveru/focusu.
- **Destruktivní akce zůstávají čitelné i v klidu** (červený text), protože dotyková
  zařízení nemají hover — `DeleteAction` tak čte jako nebezpečná i bez interakce.
- Každá akce si drží viditelný focus ring pro klávesnici.

Jednu akci necháš výraznou tím, že ji vrátíš do plné výplně přes `->solid()`:

```php
$table
    ->actions([
        Action::make('view')->icon('outline:eye'),
        Action::make('edit')->icon('pencil')->color('primary'),
        Action::make('approve')->icon('check')->color('success')->solid(), // zůstane plné tlačítko
        DeleteAction::make(),                                              // čitelně červené v klidu
    ])
    ->actionsStyle('quiet');
```

Tichý styl je opt-in; existující tabulky zůstávají beze změny. `->solid()` a
`->outlined()` zůstávají dostupné jako per-akce override.

## Extra atributy

```php
Action::make('custom')
    ->extraAttributes([
        'data-testid' => 'custom-action',
        'x-on:click' => 'console.log("clicked")',
    ]);
```

## Samostatné akce (bez tabulky)

Akce nejsou jen pro tabulky. Jakákoli Livewire komponenta je může deklarovat a plně spustit
— modal, slide-over, wizard, potvrzení, formulář, validaci a celý
životní cyklus — s traitem `WithActions`. Deklarujte pojmenované akce v `actions()`,
vykreslete tlačítka a jednou vhoďte modal host.

```php
use Livewire\Component;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Concerns\WithActions;

class EditPanel extends Component
{
    use WithActions;

    public Offer $offer;

    /** @return array<int, Action> */
    protected function actions(): array
    {
        return [$this->editOfferAction()];
    }

    public function editOfferAction(): Action
    {
        return Action::make('editOffer')
            ->label('Edit')->icon('pencil')
            ->slideOver()
            ->form([TextInput::make('name')->required()])
            ->fillFormUsing(fn () => ['name' => $this->offer->name])
            ->action(fn (array $data) => $this->offer->update($data));
    }

    public function render()
    {
        return view('livewire.edit-panel');
    }
}
```

```blade
{{-- Tlačítko auto-odvodí wire:click="mountAction('editOffer')" --}}
<x-wire-actions::button :action="$this->editOfferAction()" />

{{-- Vykreslit jednou — ukáže modal/slide-over/wizard/potvrzení namountované akce --}}
<x-wire-actions::modal-host :component="$this" />
```

Trait přidává tyto Livewire metody:

| Metoda | Účel |
|--------|---------|
| `mountAction($name, ['record' => $model])` | Otevře modal akce, nebo okamžitě spustí prostou akci. Volitelný `record` ji zúží na model. |
| `callMountedAction()` | Zvaliduje formulář a spustí callback akce. |
| `unmountAction()` | Zavře modal a vyčistí jeho stav. |
| `nextActionModalStep()` / `prevActionModalStep()` | Navigace wizardu. |
| `callModalFooterAction($name)` | Spustí vlastní akci patičky. |

Modal formuláře se váže na veřejnou vlastnost `actionModalFormData`, takže
`fillFormUsing`, field akce a `createOptionForm` se chovají přesně jako
v table action modalu. `WithActions` žije ve `wire-forms` (form-capable hostitel
potřebuje wire-forms field concerny); stejný engine
(`NyonCode\WireCore\Actions\Concerns\InteractsWithActions`) pohání i `WithTable`.

## Reference BaseAction API

Sdílené napříč Action, BulkAction, HeaderAction:

```php
->label(string|Closure $label)
->icon(string|Closure $icon, ?string $position = null)   // pozice: 'before' | 'after'
->color(string|Closure $color)          // primary, danger, success, warning, info, gray
->size(string $size)                    // xs, sm, md, lg
->outlined(bool $outlined = true)
->tooltip(string|Closure $tooltip)
->action(Closure $callback)
->hidden(bool|Closure $hidden = true)
->visible(bool|Closure $visible = true)
->disabled(bool|Closure $disabled = true)
->requiresConfirmation()
->modalHeading(string $heading)
->modalDescription(string $description)
->modalIcon(string $icon, ?string $color)
->modalWidth(string $width)
->modalSubmitActionLabel(string $label)
->modalCancelActionLabel(string $label)
->slideOver()
->form(array $components)
->fillFormUsing(Closure $fn)
->steps(array $steps)
->modal(ModalContract $modal)        // Modal | SlideOver | ConfirmationDialog | Wizard
->before(Closure $fn)
->after(Closure $fn)
->successNotification(string $message)
->failureNotification(string $message)
->keyboardShortcut(string $keys)
->extraAttributes(array $attrs)
```

Override prezentace řádkové akce (`Action`), respektované pod `Table::actionsStyle('quiet')`:

```php
->quiet(bool $quiet = true)   // neutrální v klidu, barva na hoveru/focusu (obvykle nastaveno pro celou tabulku)
->solid(bool $solid = true)   // vynutí plnou výplň i pod tichou tabulkou
```

## Blade komponenty

```blade
<x-wire-actions::button :action="$action" />
<x-wire-actions::group :group="$group" />
<x-wire-actions::modal-host :component="$this" />  {{-- pro WithActions hostitele --}}
```
