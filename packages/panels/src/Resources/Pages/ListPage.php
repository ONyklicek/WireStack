<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Pages;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\BaseAction;
use NyonCode\WireCore\Actions\Contracts\ResolvesActionClick;
use NyonCode\WireCore\Core\Plugin\Contracts\IdentifiesHookTarget;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesBreadcrumbs;
use NyonCode\WirePanels\Pages\Concerns\InteractsWithHeaderActions;
use NyonCode\WirePanels\Pages\Contracts\HasHeaderActions;
use NyonCode\WirePanels\Resources\Concerns\BelongsToResource;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WireTable\Actions\HeaderActionClickResolver;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\RelationManagers\RelationManager;
use NyonCode\WireTable\Table;

/**
 * A full page listing one resource's records.
 *
 * The same shape as {@see RelationManager}:
 * a Livewire component composing {@see WithTable}, which is what makes it an
 * ordinary table host — polling, partials, gestures, exports and every other
 * `WithTable` capability arrive unchanged, because none of them know a resource
 * exists.
 *
 * Two ways to use it, and both are first class (ADR 0020 invariant). Name a
 * resource and the columns come from its `table()`:
 *
 *   class ListOrders extends ListPage
 *   {
 *       protected static ?string $resource = OrderResource::class;
 *   }
 *
 * Or write the table here and use no resource at all, exactly as any `WithTable`
 * component does:
 *
 *   class ListOrders extends ListPage
 *   {
 *       public function table(Table $table): Table
 *       {
 *           return $table->model(Order::class)->columns([...]);
 *       }
 *   }
 *
 * What it deliberately does not do is route. A page is a Livewire component the
 * application mounts wherever it likes; the registry holds no URL shell, and
 * this holds none either.
 */
abstract class ListPage extends Component implements HasHeaderActions, IdentifiesHookTarget, ProvidesBreadcrumbs
{
    use BelongsToResource;
    use InteractsWithHeaderActions;
    use WithTable {
        findHeaderAction as protected findTableHeaderAction;
    }

    /**
     * The resource whose list this is, or null when the page defines its own.
     *
     * @var class-string<DescribesResource>|null
     */
    protected static ?string $resource = null;

    /**
     * The resource's table, or a clear refusal.
     *
     * Reached only on the resource path: a page taking the standalone path
     * overrides this, so control never arrives here.
     */
    public function table(Table $table): Table
    {
        $resource = $this->requireResource(ProvidesResourceTable::class);

        // The model is bound here rather than left to the resource's table(),
        // for the reason the form pages bind it: the resource already declares
        // which entity it owns, and asking it to repeat that inside every
        // surface is the duplication that only shows up when the two disagree.
        // A resource over a non-Eloquent DataSource declares no model and points
        // the table at its source itself.
        $model = static::$resource::modelClass();

        return $resource->table($model !== null ? $table->model($model) : $table);
    }

    /** A list is titled by the plural: "Orders", not "Order". */
    public function getTitle(): ?string
    {
        if ($this->title !== null) {
            return $this->title;
        }

        $resource = static::$resource;

        return $resource !== null ? $resource::pluralLabel() : null;
    }

    /**
     * *New*, when the resource has a create page this user may open.
     *
     * A link and nothing more, guarded by the permission the create route is
     * guarded by — so it cannot offer anything the router would refuse, which is
     * why it is drawn by default when *Delete* on a record page is not.
     *
     * @return array<int, Action|null>
     */
    protected function headerActions(): array
    {
        return [$this->createHeaderAction()];
    }

    /** The ready-made *New*: a link to the create page, or nothing when there is none to open. */
    protected function createHeaderAction(): ?Action
    {
        $url = $this->reachablePageUrl('create');

        if ($url === null) {
            return null;
        }

        return Action::make('create')
            ->label(__('wire-panels::messages.create', ['label' => $this->resourceLabel() ?? '']))
            ->icon('plus')
            ->url($url)
            ->extraAttributes(['wire:navigate' => '']);
    }

    /**
     * A page header action, then the table's own.
     *
     * The page's actions run through the table's engine rather than one of
     * their own: a second engine on this component would be a second modal
     * stack, and the table already renders the one its header actions open in.
     */
    protected function findHeaderAction(string $actionName): ?BaseAction
    {
        return $this->findPageHeaderAction($actionName) ?? $this->findTableHeaderAction($actionName);
    }

    /** A list is about no record, so neither are its actions. */
    protected function headerActionRecord(): ?Model
    {
        return null;
    }

    protected function headerActionClick(): ResolvesActionClick
    {
        return new HeaderActionClickResolver;
    }

    public function render(): View
    {
        return view('wire-panels::pages.list-page', [
            'title' => $this->getTitle(),
            'breadcrumbs' => $this->breadcrumbs(),
            'headerActions' => $this->renderedHeaderActions(),
        ]);
    }
}
