<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Pages;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\DeleteAction;
use NyonCode\WireCore\Actions\EditAction;
use NyonCode\WireForms\Contracts\ProvidesResourceForm;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireTable\Table;

/**
 * A whole resource on one page: the list, with create and edit opened as modals
 * over it and delete on the row.
 *
 * For an entity whose form is too small to deserve a page of its own — a list of
 * tags, of units, of payment terms:
 *
 *   final class ManageTags extends ManagePage
 *   {
 *       protected static ?string $resource = TagResource::class;
 *   }
 *
 *   public static function pages(): array
 *   {
 *       return ['index' => ManageTags::class];
 *   }
 *
 * The resource's one `form()` serves both modals, as it serves both pages of an
 * ordinary resource. **Persistence is the model's, not the form's**: a modal
 * keeps its state in the action's frame, so what it saves is `Model::create()`
 * and `$record->update()` with the validated data. A form that needs the page
 * lifecycle — relationship repeaters, an optimistic lock, `Form::using()` —
 * belongs on a create and an edit page.
 *
 * Who may do what is the model's policy (`create`, `update`, `delete`) when it
 * has one. Without one, whoever may open this page may manage its records; the
 * page's route already asked that.
 */
abstract class ManagePage extends ListPage
{
    /**
     * *New*, as a modal over the list rather than a link to a page.
     */
    protected function createHeaderAction(): ?Action
    {
        $resource = static::$resource;
        $model = $resource !== null ? $resource::modelClass() : null;

        if ($model === null || ! is_subclass_of((string) $resource, ProvidesResourceForm::class)) {
            return null;
        }

        return $this->guardedByPolicy(Action::make('create'), 'create', perRecord: false)
            ->label(__('wire-panels::messages.create', ['label' => $this->resourceLabel() ?? '']))
            ->icon('plus')
            ->modalHeading(__('wire-panels::messages.create', ['label' => $this->resourceLabel() ?? '']))
            ->form(fn (): Form => $this->manageForm())
            ->action(fn (array $data) => $model::query()->create($data))
            ->successNotification(__('wire-panels::messages.created', ['label' => $this->resourceLabel() ?? '']));
    }

    /**
     * The resource's table, with *Edit* and *Delete* on each row.
     *
     * After the resource's own actions, so a resource that declares an edit of
     * its own keeps it first.
     */
    public function table(Table $table): Table
    {
        $table = parent::table($table);

        if (! is_subclass_of((string) static::$resource, ProvidesResourceForm::class)) {
            return $table;
        }

        return $table->actions([
            ...$table->getActions(),
            $this->guardedByPolicy(EditAction::make(), 'update', perRecord: true)
                ->modalHeading(__('wire-panels::messages.edit', ['label' => $this->resourceLabel() ?? '']))
                ->form(fn (): Form => $this->manageForm())
                ->fillFormUsing(fn (?Model $record): array => $record?->attributesToArray() ?? [])
                ->action(fn (Model $record, array $data) => $record->update($data))
                ->successNotification(__('wire-panels::messages.saved')),
            $this->guardedByPolicy(DeleteAction::make(), 'delete', perRecord: true)
                ->action(fn (Model $record) => $record->delete())
                ->successNotification(__('wire-panels::messages.deleted', ['label' => $this->resourceLabel() ?? ''])),
        ]);
    }

    /**
     * Put the model policy's `$ability` on the action — or nothing, when the
     * model has no policy.
     *
     * Nothing rather than a check that answers yes: an authorization callback
     * refuses a request with no signed-in user at all, which would make an
     * unguarded page show a list with no way to change it. Without a policy the
     * page's own route is the guard, as the class says.
     *
     * @template TAction of Action
     *
     * @param  TAction  $action
     * @return TAction
     */
    protected function guardedByPolicy(Action $action, string $ability, bool $perRecord): Action
    {
        $model = static::$resource !== null ? static::$resource::modelClass() : null;

        if ($model === null || Gate::getPolicyFor($model) === null) {
            return $action;
        }

        return $action->authorizeUsing(fn ($user, mixed $record = null): bool => $perRecord
            ? $record instanceof Model && Gate::forUser($user)->allows($ability, $record)
            : Gate::forUser($user)->allows($ability, $model));
    }

    /**
     * The form both modals render: the resource's, over a blank form.
     *
     * Blank — no model, no state path — because the modal binds its own state,
     * and a model bound here would have the form write through its save
     * lifecycle, which a modal does not run.
     */
    protected function manageForm(): Form
    {
        /** @var ProvidesResourceForm $resource */
        $resource = $this->requireResource(ProvidesResourceForm::class);

        return $resource->form(Form::make());
    }
}
