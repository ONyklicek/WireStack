---
order: 50
summary: "Akce na libovolné Livewire komponentě — celý povrch včetně modálů a lifecyclu, s jednou traitou a jedním hostitelem modálů."
---

# Akce mimo tabulku

Akce nejsou funkce tabulky, kterou tabulka náhodou vlastní. Kterákoli Livewire
komponenta je umí deklarovat a plně spustit — modal, slide-over, wizard,
potvrzení, formulář, validaci i celý lifecycle — s jednou traitou a jedním
hostitelem modálů vykresleným jednou.

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

## Halt tu funguje taky

Akce na samostatném hostiteli se umí zastavit v půli a zeptat přesně tak jako ta
v tabulce — pipeline, která halt vyvolá, je z core, a od 2.0 je z core i modal,
který ho ukáže:

```php
Action::make('archive')
    ->action(function (bool $confirmed, array $data, callable $halt) {
        if (! $confirmed) {
            return $halt()                                   // [tl! focus:start]
                ->heading('Proč to archivuješ?')
                ->form([TextInput::make('reason')->required()]);
        }                                                    // [tl! focus:end]

        $this->order->archive($data['reason']);
    });
```

Nepotřebuje nic než modal host, který stejně vykresluješ. Co halt nese a co
jediné chce po aplikaci — cache store, který přežije request, bez něhož se po
neúspěšné validaci nevrátí jeho pole — popisují
[Lifecycle a fronty](lifecycle.md#halt-vykonavani).

## Související

- [Akce](index.md) — třídy, které se tu deklarují
- [Modály akcí](modals.md) — všechno, co samostatná akce může otevřít
- [Panely: Stránky](../../panels/pages.md) — `ListPage` hostí akce skrze `WithTable`; zbylé čtyři žádný runtime neskládají
- [Formuláře](../../forms/overview.md) — druhá polovina hostitelské komponenty `WithActions`
