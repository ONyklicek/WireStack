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
viz [mobilní prezentace](../configuration.md#mobile).

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
[Vícekrokový wizard](modals.md#multi-step-wizard).

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
současný místo jeho nahrazení. Původní modal zůstane otevřený, ztlumený, za novým;
zavření vrchního modalu vás vrátí zpět na rodiče včetně zachovaných dat formuláře.
Není k tomu potřeba žádné speciální API — jakýkoli callback, který dostane hostitele
`$component` (akce v patičce, akce pole, akce infolistu), může otevřít další akci a ta
se prostě navrství:

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

Poznámky k chování:

- **Hloubka vrstvení není omezená** — každá úroveň se vrství nad předchozí se
  zvyšujícím se `z-indexem` a každé pozadí prohloubí ztmavení pro jasný pocit hloubky.
- **Zavření vrací k rodiči.** `Escape`, tlačítko zavřít, kliknutí na pozadí i akce
  v patičce, která modal zavírá, popnou jen **vrchní** modal a obnoví rodiče. Poslední
  zavření vyprázdní celý zásobník.
- **Data formuláře jsou zachována** pro každou úroveň, takže rodičovský modal zůstane
  přesně tak, jak jste ho opustili.
- Akce v patičce, která vnořený modal *otevře*, se poté automaticky **nezavírá**, takže
  modal, který otevřela, zůstane navrchu.

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
    ->outlined()                    // outline varianta místo solid
    ->color('gray')
    ->size('sm');                   // xs, sm, md, lg
```

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

## Blade komponenty

```blade
<x-wire-actions::button :action="$action" />
<x-wire-actions::group :group="$group" />
<x-wire-actions::modal-host :component="$this" />  {{-- pro WithActions hostitele --}}
```
