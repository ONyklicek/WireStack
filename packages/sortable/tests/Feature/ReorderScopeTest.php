<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireSortable\Concerns\WithSortable;
use NyonCode\WireSortable\SortableTable;
use NyonCode\WireTable\Table;

/**
 * reorderRows must scope its writes through the table's base query so a
 * client-supplied primary key outside the visible/scoped set is never written
 * (the IDOR-write regression). See rule5 audit H4.
 */
class RstTask extends Model
{
    protected $table = 'rst_tasks';

    protected $guarded = [];

    public $timestamps = false;
}

class RstScopedComponent
{
    use WithSortable;

    public mixed $cachedRecords = null;

    protected string $wireTableClass = Table::class;

    public function __construct()
    {
        $this->isReordering = true;
    }

    public function getTable(): Table
    {
        // Base query scoped to team 1 (a developer ->query()/tenant constraint).
        return SortableTable::make()
            ->query(RstTask::where('team_id', 1))
            ->reorderable('sort_order');
    }
}

beforeEach(function () {
    Schema::create('rst_tasks', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('team_id');
        $t->integer('sort_order')->default(0);
    });
    RstTask::insert([
        ['id' => 1, 'team_id' => 1, 'sort_order' => 1],
        ['id' => 2, 'team_id' => 1, 'sort_order' => 2],
        ['id' => 99, 'team_id' => 2, 'sort_order' => 5], // foreign team, outside scope
    ]);
});

afterEach(fn () => Schema::dropIfExists('rst_tasks'));

it('scopes reorder writes to the base query — a foreign-scope row is never touched', function () {
    $component = new RstScopedComponent;

    // Attempt to rewrite the order of a row belonging to team 2 (outside scope).
    $component->reorderRows([['value' => 99, 'order' => 1]]);

    // The foreign row is untouched: the model-global IDOR write is scoped out.
    expect(RstTask::find(99)->sort_order)->toBe(5);
});

it('still reorders in-scope rows correctly', function () {
    $component = new RstScopedComponent;

    $component->reorderRows([
        ['value' => 2, 'order' => 1],
        ['value' => 1, 'order' => 2],
    ]);

    expect(RstTask::find(2)->sort_order)->toBe(1)
        ->and(RstTask::find(1)->sort_order)->toBe(2);
});
