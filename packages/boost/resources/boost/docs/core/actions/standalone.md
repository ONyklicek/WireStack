---
order: 50
summary: "Actions on any Livewire component — the whole surface, modals and lifecycle included, with one trait and one modal host."
---

# Actions Outside A Table

Actions are not a table feature that a table happens to own. Any Livewire
component can declare and fully run them — modal, slide-over, wizard,
confirmation, form, validation and the whole lifecycle — with one trait and one
modal host rendered once.

## Standalone Actions (without a table)

Actions are not table-only. Any Livewire component can declare and fully run them
— modal, slide-over, wizard, confirmation, form, validation and the whole
lifecycle — with the `WithActions` trait. Declare named actions in `actions()`,
render the buttons, and drop the modal host once.

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
{{-- The button auto-derives wire:click="mountAction('editOffer')" --}}
<x-wire-actions::button :action="$this->editOfferAction()" />

{{-- Render once — shows the mounted action's modal/slide-over/wizard/confirmation --}}
<x-wire-actions::modal-host :component="$this" />
```

The trait adds these Livewire methods:

| Method | Purpose |
|--------|---------|
| `mountAction($name, ['record' => $model])` | Open the action's modal, or run a plain action immediately. The optional `record` scopes it to a model. |
| `callMountedAction()` | Validate the form and run the action callback. |
| `unmountAction()` | Close the modal and clear its state. |
| `nextActionModalStep()` / `prevActionModalStep()` | Wizard navigation. |
| `callModalFooterAction($name)` | Run a custom footer action. |

The modal form binds to the public `actionModalFormData` property, so
`fillFormUsing`, field actions and `createOptionForm` behave exactly as they do
in a table action modal. `WithActions` lives in `wire-forms` (a form-capable host
needs the wire-forms field concerns); the same engine
(`NyonCode\WireCore\Actions\Concerns\InteractsWithActions`) also backs `WithTable`.

## A Halt Works Here Too

An action on a standalone host can stop itself mid-flight and ask, exactly as one
in a table does — the pipeline that raises a halt is core's, and since 2.0 so is
the modal that shows it:

```php
Action::make('archive')
    ->action(function (bool $confirmed, array $data, callable $halt) {
        if (! $confirmed) {
            return $halt()                                   // [tl! focus:start]
                ->heading('Why are you archiving this?')
                ->form([TextInput::make('reason')->required()]);
        }                                                    // [tl! focus:end]

        $this->order->archive($data['reason']);
    });
```

The modal host you already render is all it needs. See
[Lifecycle And Queues](lifecycle.md#halt-execution) for what a halt carries, and
for the one thing it asks of the application: a cache store that survives a
request, without which its fields do not come back after a failed validation.

## Related

- [Actions](index.md) — the classes declared here
- [Action Modals](modals.md) — everything a standalone action may open
- [Panels: Pages](../../panels/pages.md) — `ListPage` hosts actions through `WithTable`; the other four compose no runtime
- [Forms](../../forms/overview.md) — the other half of `WithActions`' host component
