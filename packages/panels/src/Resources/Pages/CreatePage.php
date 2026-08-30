<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Pages;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireForms\Contracts\ProvidesResourceForm;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;
use NyonCode\WirePanels\Resources\Concerns\BelongsToResource;

/**
 * A full page creating one of a resource's records.
 *
 * `WithForms` finds a host's form by looking for a `form(Form $form): Form`
 * method — the same signature {@see ProvidesResourceForm} declares — so the page
 * is an ordinary form host and validation, wizards, file uploads, repeaters and
 * field actions all arrive unchanged.
 *
 *   class CreateOrder extends CreatePage
 *   {
 *       protected static ?string $resource = OrderResource::class;
 *   }
 *
 * Or write `form()` here and use no resource, exactly as any `WithForms`
 * component does. Both are first class (ADR 0020).
 *
 * **Persistence is the form's, not the page's.** `Form` already owns the save
 * lifecycle — validate, mutate, hooks, persist, notify — and binding the model
 * is all this adds. A resource over a non-Eloquent source declares
 * `Form::using()` in its own `form()` and this page is unchanged, which is the
 * whole of ADR 0020's answer to non-Eloquent writes.
 */
abstract class CreatePage extends Component
{
    use BelongsToResource;
    use WithForms;

    /**
     * The resource whose record this creates, or null when the page builds its
     * own form.
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

    /**
     * Seed the state bag before anything binds to it.
     *
     * Not cosmetic: a select or a date picker entangles its own path, and
     * entangling a key the bag does not have is a silent no-op — the control
     * renders and never writes. `Form::getInitialState()` exists for exactly
     * this, which is how the action modals seed theirs.
     *
     * The edit page seeds from the record instead, layering over the same
     * blanks.
     */
    public function mount(): void
    {
        $this->data = $this->form->getInitialState();
    }

    public function form(Form $form): Form
    {
        $resource = $this->requireResource(ProvidesResourceForm::class);

        // The model is bound here rather than left to the resource's form():
        // the resource already declares which entity it owns, and asking it to
        // repeat that inside every form() is the kind of duplication that only
        // shows up when the two disagree.
        $model = static::$resource::modelClass();

        $form = $form->statePath('data');

        return $resource->form($model !== null ? $form->model($model) : $form);
    }

    /** A create page is titled by the singular: "New order", not "Orders". */
    public function getTitle(): ?string
    {
        if ($this->title !== null) {
            return $this->title;
        }

        $label = $this->resourceLabel();

        return $label !== null ? __('wire-panels::messages.create', ['label' => $label]) : null;
    }

    /**
     * Persist, and hand the result back so a subclass can redirect on it.
     *
     * Deliberately thin: everything that could go wrong — validation, an
     * unauthorized save — is already the form's, and catching it here would only
     * hide it from the host that knows what to do about it.
     */
    public function save(): mixed
    {
        return $this->form->save();
    }

    public function render(): View
    {
        return view('wire-panels::pages.create-page', [
            'title' => $this->getTitle(),
        ]);
    }
}
