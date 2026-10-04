<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireSortable\Concerns\WithSortable;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * `wire-sortable.animation` is printed into an Alpine expression. Printed raw, a
 * null or a word made `animation: ,` — a syntax error that takes the whole table
 * region with it — so it is cast and encoded on the way out.
 */
class AcTask extends Model
{
    protected $table = 'ac_tasks';

    protected $guarded = [];

    public $timestamps = false;
}

class AcHost extends Component
{
    use WithSortable;
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(AcTask::class)
            ->columnReorderable()
            ->columns([TextColumn::make('title')])
            ->paginated(false);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('ac_tasks', function (Blueprint $t) {
        $t->id();
        $t->string('title')->nullable();
        $t->integer('sort_order')->nullable();
    });
});

afterEach(fn () => Schema::dropIfExists('ac_tasks'));

test('the configured animation reaches the drag controller as a number', function (mixed $configured, string $expected) {
    config()->set('wire-sortable.animation', $configured);

    expect(Livewire::test(AcHost::class)->html())->toContain($expected);
})->with([
    'an int' => [300, 'animation: 300,'],
    'a numeric string from the environment' => ['200', 'animation: 200,'],
    'null keeps the default' => [null, 'animation: 150,'],
]);
