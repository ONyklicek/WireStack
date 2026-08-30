<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Pages;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireForms\Contracts\ProvidesResourceForm;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;
use NyonCode\WirePanels\Resources\Concerns\BelongsToResource;
use NyonCode\WirePanels\Resources\Concerns\EmbedsRelationManagers;
use NyonCode\WirePanels\Resources\Concerns\ResolvesOneRecord;

/**
 * A full page editing one of a resource's records.
 *
 * The same form the create page uses — one schema, not two, because a create
 * form and an edit form that drift apart is the failure this shape prevents.
 * What differs is only that this one starts bound to a record:
 *
 *   class EditOrder extends EditPage
 *   {
 *       protected static ?string $resource = OrderResource::class;
 *   }
 *
 *   `@livewire`(EditOrder::class, ['record' => $order->getKey()])
 *
 * The record arrives as a **key**, not a model. A Livewire component's mount
 * arguments end up in its snapshot, and a hydrated model there is both larger
 * than the key and stale by the time the next request lands — so the key is what
 * travels and the record is resolved per request.
 */
abstract class EditPage extends Component
{
    use BelongsToResource;
    use EmbedsRelationManagers;
    use ResolvesOneRecord;
    use WithForms;

    /**
     * The resource whose record this edits, or null when the page builds its own
     * form and resolves its own record.
     *
     * @var class-string<DescribesResource>|null
     */
    protected static ?string $resource = null;

    /**
     * The bound form state.
     *
     * Declared here, and the matching `statePath('data')` applied below, because
     * binding a form to its host is the page's job — the same division that lets
     * a resource's `table()` know nothing about the component rendering it. A
     * resource that needs a different path sets one in its own `form()`, which
     * runs after this and therefore wins.
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function form(Form $form): Form
    {
        $resource = $this->requireResource(ProvidesResourceForm::class);
        $model = $this->resolveRecord();

        $form = $form->statePath('data');

        return $resource->form($model !== null ? $form->model($model) : $form);
    }

    /** An edit page is titled by the singular, like the create page. */
    public function getTitle(): ?string
    {
        if ($this->title !== null) {
            return $this->title;
        }

        $label = $this->resourceLabel();

        return $label !== null ? __('wire-panels::messages.edit', ['label' => $label]) : null;
    }

    public function save(): mixed
    {
        return $this->form->save();
    }

    /** Seed the form once the record is known — {@see ResolvesOneRecord::mount()}. */
    protected function mountedRecord(): void
    {
        $this->form->fill($this->recordData());
    }

    /**
     * What the form is seeded with.
     *
     * @return array<string, mixed>
     */
    protected function recordData(): array
    {
        return $this->resolveRecord()?->attributesToArray() ?? [];
    }

    public function render(): View
    {
        return view('wire-panels::pages.edit-page', [
            'title' => $this->getTitle(),
            'relationManagers' => $this->relationManagers(),
            // Not `record`: that is the public property holding the *key*, and
            // Livewire injects public properties into the view scope, where it
            // would shadow this.
            'ownerRecord' => $this->resolveRecord(),
        ]);
    }
}
