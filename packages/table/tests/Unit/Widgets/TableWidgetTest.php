<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;
use NyonCode\WireTable\Widgets\TableWidget;

/**
 * The widget that never drew anything.
 *
 * `WireCore\Widgets\TableWidget` shipped from the beginning taking a closure
 * that configured a `Table`, storing it, and rendering a card with a heading and
 * an empty `<div>`. `getTableCallback()`'s only caller in the repository was a
 * test asserting the getter, and the docs page showed a full working example
 * with columns and a query.
 *
 * The cause was structural: the class was in `wire-core` and the table engine is
 * in `wire-table`, which depends on core, so from core it could not be reached.
 * These assert the thing the old tests could not — that rows come out.
 */
class TwOrder extends Model
{
    protected $table = 'tw_orders';

    public $timestamps = false;

    protected $guarded = [];
}

beforeEach(function () {
    Schema::dropIfExists('tw_orders');
    Schema::create('tw_orders', function (Blueprint $table) {
        $table->id();
        $table->string('reference');
        $table->string('status');
    });

    DB::table('tw_orders')->insert([
        ['reference' => 'ORD-1', 'status' => 'paid'],
        ['reference' => 'ORD-2', 'status' => 'pending'],
        ['reference' => 'ORD-3', 'status' => 'paid'],
        ['reference' => 'ORD-4', 'status' => 'refunded'],
        ['reference' => 'ORD-5', 'status' => 'paid'],
        ['reference' => 'ORD-6', 'status' => 'paid'],
    ]);
});

function twWidget(): TableWidget
{
    return TableWidget::make()->table(fn (Table $table) => $table
        ->model(TwOrder::class)
        ->columns([
            TextColumn::make('reference'),
            TextColumn::make('status'),
        ]));
}

// ─── It draws rows ───────────────────────────────────────────────────────────

it('renders the columns and the rows, which is what it never did', function () {
    $html = twWidget()->heading('Recent orders')->toHtml();

    expect($html)->toContain('Recent orders')
        // Column labels…
        ->and($html)->toContain('Reference')
        // …and real cell values from the database.
        ->and($html)->toContain('ORD-1')
        ->and($html)->toContain('paid');
});

it('draws each cell through the column, so a badge is still a badge', function () {
    // Not a second, simpler cell renderer: `Column::renderCell()` is the one
    // owner, so money formats, relation paths and badge colours are identical
    // to a full table's.
    $html = TableWidget::make()->table(fn (Table $table) => $table
        ->model(TwOrder::class)
        ->columns([BadgeColumn::make('status')->colors(['paid' => 'success'])]))
        ->toHtml();

    expect($html)->toContain('paid')
        // The badge's own markup, not a bare string in a <td>.
        ->and($html)->toContain('rounded');
});

// ─── The limit ───────────────────────────────────────────────────────────────

it('draws five rows by default, because there is no pagination to catch the rest', function () {
    $widget = twWidget();

    expect($widget->getLimit())->toBe(5)
        ->and($widget->getRecords())->toHaveCount(5)
        ->and($widget->toHtml())->not->toContain('ORD-6');
});

it('takes a limit of its own', function () {
    expect(twWidget()->limit(2)->getRecords())->toHaveCount(2)
        // A limit below one would render an empty card that looks like a bug.
        ->and(twWidget()->limit(0)->getLimit())->toBe(1);
});

// ─── Nothing to draw ─────────────────────────────────────────────────────────

it('says so when the query comes back empty', function () {
    DB::table('tw_orders')->delete();

    expect(twWidget()->toHtml())->toContain('Nothing to show.')
        ->and(twWidget()->emptyState('No orders yet.')->toHtml())->toContain('No orders yet.');
});

it('says so when it was never given a table at all', function () {
    $widget = TableWidget::make()->heading('Orders');

    expect($widget->getTable())->toBeNull()
        ->and($widget->getRecords())->toBe([])
        ->and($widget->toHtml())->toContain('Nothing to show.');
});

// ─── It is a widget like any other ───────────────────────────────────────────

it('configures the caller\'s table once, not once per thing the view asks for', function () {
    $calls = 0;
    $widget = TableWidget::make()->table(function (Table $table) use (&$calls) {
        $calls++;

        return $table->model(TwOrder::class)->columns([TextColumn::make('reference')]);
    });

    $widget->toHtml();

    expect($calls)->toBe(1);
});

it('carries header actions like every other widget', function () {
    $html = twWidget()
        ->key('orders')
        ->headerActions([Action::make('export')->label('Export')])
        ->toHtml();

    expect($html)->toContain('data-testid="widget-action-export"')
        ->and($html)->toContain('Export');
});

it('answers a poll tick and a filter like every other widget', function () {
    expect(twWidget()->pollingInterval('30s')->usesPartialAnchor())->toBeTrue()
        ->and(twWidget()->key('o')->filter(['week' => 'Week'])->getFilterExpression())
        ->toBe("filterWidget('o', \$event.target.value)");
});
