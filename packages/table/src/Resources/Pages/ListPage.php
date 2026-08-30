<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Resources\Pages;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Exceptions\ResourcePageException;
use NyonCode\WireTable\RelationManagers\RelationManager;
use NyonCode\WireTable\Resources\Contracts\DescribesResource;
use NyonCode\WireTable\Resources\Contracts\ProvidesResourceTable;
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
abstract class ListPage extends Component
{
    use WithTable;

    /**
     * The resource whose list this is, or null when the page defines its own.
     *
     * @var class-string<DescribesResource>|null
     */
    protected static ?string $resource = null;

    /**
     * Optional heading. Falls back to the resource's plural label, which is the
     * reason that label is on the static contract: a page shows it before it has
     * composed anything.
     */
    protected ?string $title = null;

    /**
     * The resource's table, or a clear refusal.
     *
     * Reached only on the resource path: a page taking the standalone path
     * overrides this, so control never arrives here. Every branch below is a
     * page left half-declared, and each throws rather than returning the table
     * untouched — an empty table reads as "no records", which is the one failure
     * that looks like data.
     */
    public function table(Table $table): Table
    {
        $resource = static::$resource;

        if ($resource === null) {
            throw ResourcePageException::noTableSource(static::class);
        }

        if (! in_array(DescribesResource::class, class_implements($resource) ?: [], true)) {
            throw ResourcePageException::notAResource(static::class, $resource);
        }

        if (! is_subclass_of($resource, ProvidesResourceTable::class)) {
            throw ResourcePageException::resourceCannotList(static::class, $resource);
        }

        return $this->resourceInstance()->table($table);
    }

    public function getTitle(): ?string
    {
        if ($this->title !== null) {
            return $this->title;
        }

        $resource = static::$resource;

        return $resource !== null ? $resource::pluralLabel() : null;
    }

    /** @return class-string<DescribesResource>|null */
    public static function resourceClass(): ?string
    {
        return static::$resource;
    }

    /**
     * The resource as an object, or null when the page declares none.
     *
     * Built here rather than injected: the surfaces are instance methods, so
     * something has to instantiate, and the page is the one place that knows
     * which resource it is showing. Resolved through the container so a resource
     * may type-hint its own dependencies.
     */
    protected function resourceInstance(): ?object
    {
        return static::$resource === null ? null : app(static::$resource);
    }

    public function render(): View
    {
        return view('wire-table::resources.list-page', [
            'title' => $this->getTitle(),
        ]);
    }
}
