<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Plugin\Hooks\TableComposingPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Foundation\Enums\Hook;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Table;

/*
 * `table.composing` — the hook that changes what a table *is*.
 *
 * It exists because `table.configuring` cannot do this and reads as though it
 * can. That one runs inside TableQueryService, on arrays the planner is about to
 * consume: a column added there is searched and sorted on and never rendered.
 * The promise in ADR 0029 — adjust an installed module instead of forking it —
 * needed a hook on the composed instance, which is this.
 */

function tchHost(): object
{
    return new class
    {
        use WithTable;

        public function table(Table $table): Table
        {
            return $table->columns([TextColumn::make('number')]);
        }
    };
}

it('adds a column to a table the caller does not own', function () {
    app(PluginManager::class)->hook(
        Hook::TableComposing,
        function (TableComposingPayload $payload): TableComposingPayload {
            $payload->columns = [...$payload->columns, TextColumn::make('internal_note')];

            return $payload;
        },
    );

    $columns = array_map(
        static fn (object $column): string => $column->getName(),
        tchHost()->getTable()->getColumns(),
    );

    expect($columns)->toBe(['number', 'internal_note']);
});

it('adds a filter the same way', function () {
    app(PluginManager::class)->hook(
        Hook::TableComposing,
        function (TableComposingPayload $payload): TableComposingPayload {
            $payload->filters = [...$payload->filters, SelectFilter::make('status')];

            return $payload;
        },
    );

    expect(tchHost()->getTable()->getFilters())->toHaveCount(1);
});

it('runs once per host, because the composed table is memoized', function () {
    $calls = 0;

    app(PluginManager::class)->hook(Hook::TableComposing, function (TableComposingPayload $payload) use (&$calls) {
        $calls++;

        return $payload;
    });

    $host = tchHost();
    $host->getTable();
    $host->getTable();

    expect($calls)->toBe(1);
});

it('hands the callback the host and the model it can be scoped by', function () {
    $target = null;

    app(PluginManager::class)->hook(Hook::TableComposing, function (TableComposingPayload $payload) use (&$target) {
        $target = $payload->target;

        return $payload;
    });

    $host = new class
    {
        use WithTable;

        public function table(Table $table): Table
        {
            return $table->model(TchOrder::class)->columns([TextColumn::make('number')]);
        }
    };

    $host->getTable();

    expect($target?->surface)->toBe('table')
        ->and($target?->model)->toBe(TchOrder::class)
        ->and($target?->host)->toBe($host)
        ->and($target?->key)->toBeNull();
});

it('leaves a table alone when nothing listens', function () {
    // The hot path: every table in an application that installs no plugin at
    // all. Nothing is dispatched and the column set is not re-wrapped.
    $table = tchHost()->getTable();

    expect($table->getColumns())->toHaveCount(1)
        ->and($table->getFilters())->toBe([]);
});

it('composes a table unchanged when nothing is bound to run hooks', function () {
    $host = tchHost();
    $booted = Container::getInstance();

    try {
        Container::setInstance(new Container);

        expect($host->getTable()->getColumns())->toHaveCount(1);
    } finally {
        Container::setInstance($booted);
    }
});

class TchOrder extends Model
{
    protected $table = 'tch_orders';
}
