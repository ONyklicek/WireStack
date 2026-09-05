<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Pages;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use NyonCode\WireCore\Core\Plugin\Contracts\IdentifiesHookTarget;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WirePanels\Resources\Concerns\BelongsToResource;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
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
abstract class ListPage extends Component implements IdentifiesHookTarget
{
    use BelongsToResource;
    use WithTable;

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

    public function render(): View
    {
        return view('wire-panels::pages.list-page', [
            'title' => $this->getTitle(),
        ]);
    }
}
