<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Actions\DeleteAction;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * Deleting the last row on the current page must not strand the user on an
 * empty page past the end of the result set: the paginator is re-anchored to
 * the last populated page (the page below) after the delete.
 */
class PcadPost extends Model
{
    protected $table = 'pcad_posts';

    protected $guarded = [];

    public $timestamps = false;
}

class PcadComponent extends Component
{
    use WithTable;

    public bool $simple = false;

    public function table(Table $table): Table
    {
        $table = $table
            ->model(PcadPost::class)
            ->paginated(true)
            ->perPage(2)
            ->columns([TextColumn::make('title')])
            ->actions([
                DeleteAction::make()
                    ->action(fn ($record) => $record->delete()),
            ])
            ->bulkActions([
                BulkAction::make('destroy')
                    ->action(fn ($records) => $records->each->delete()),
            ]);

        return $this->simple ? $table->simplePagination() : $table;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    Schema::create('pcad_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
    });

    // 5 rows, perPage 2 → 3 pages (page 3 holds a single row, id 5).
    PcadPost::insert([
        ['id' => 1, 'title' => 'A'],
        ['id' => 2, 'title' => 'B'],
        ['id' => 3, 'title' => 'C'],
        ['id' => 4, 'title' => 'D'],
        ['id' => 5, 'title' => 'E'],
    ]);
});

afterEach(function () {
    Schema::dropIfExists('pcad_posts');
});

it('steps back to the last populated page when the last row on the page is deleted', function () {
    $component = Livewire::test(PcadComponent::class)
        ->call('setPage', 3);

    expect($component->instance()->getPage())->toBe(3);

    // Delete the only row on page 3 (id 5).
    $component->call('executeTableAction', '5', 'delete', true);

    expect($component->instance()->getPage())->toBe(2)
        ->and(PcadPost::count())->toBe(4);

    // The clamped page renders real rows, not an empty page.
    $records = $component->instance()->getTableRecords();
    expect($records->count())->toBe(2)
        ->and($records->currentPage())->toBe(2);
});

it('does not move the page when a deletion leaves rows on the current page', function () {
    $component = Livewire::test(PcadComponent::class)
        ->call('setPage', 2);

    // Deleting id 3 leaves ids 1,2,4,5 → page 2 still holds two rows (4, 5).
    $component->call('executeTableAction', '3', 'delete', true);

    expect($component->instance()->getPage())->toBe(2)
        ->and($component->instance()->getTableRecords()->count())->toBe(2);
});

it('clamps past several emptied pages when a bulk delete removes the tail', function () {
    $component = Livewire::test(PcadComponent::class)
        ->call('setPage', 3);

    // Bulk-delete ids 3, 4, 5 → only ids 1, 2 remain (a single page left).
    $component
        ->call('toggleRecordSelection', '3')
        ->call('toggleRecordSelection', '4')
        ->call('toggleRecordSelection', '5')
        ->call('executeBulkAction', 'destroy', true);

    expect($component->instance()->getPage())->toBe(1)
        ->and(PcadPost::count())->toBe(2)
        ->and($component->instance()->getTableRecords()->count())->toBe(2);
});

it('leaves page 1 untouched when its last row is deleted', function () {
    $component = Livewire::test(PcadComponent::class);

    expect($component->instance()->getPage())->toBe(1);

    $component->call('executeTableAction', '1', 'delete', true);

    expect($component->instance()->getPage())->toBe(1);
});

it('does not clamp simple pagination, which has no last page to compute', function () {
    $component = Livewire::test(PcadComponent::class, ['simple' => true])
        ->call('setPage', 3);

    $component->call('executeTableAction', '5', 'delete', true);

    // Simple pagination cannot know the last page, so the page is left as-is.
    expect($component->instance()->getPage())->toBe(3);
});
