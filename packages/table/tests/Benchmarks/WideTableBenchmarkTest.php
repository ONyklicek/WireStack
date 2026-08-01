<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\SelectColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Columns\ToggleColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * A WIDE table: 25 columns over 20 rows a page — the shape a back-office table
 * actually has, and a different one from "many rows, few columns".
 *
 * It exists because the editable-cell optimisation was first measured on 500
 * rows x 3 editable columns, which flattered it: at 25 columns most cells are
 * plain, so the saving is proportionally smaller and the plain-column path is
 * what dominates. Measured here rather than reasoned about, and reported at
 * several editable ratios because that ratio is the whole variable.
 *
 * Reports, never asserts. A millisecond threshold fails on a loaded machine and
 * gets deleted; this is here to be read when someone profiles, and it does not
 * run in `composer test` (the root phpunit.xml carries Unit and Feature only).
 *
 * Numbers on one developer machine, PHP 8.5, interleaved ON/OFF/ON with the
 * 0-editable row as the control group:
 *
 *     editable   before   after
 *      0 of 25    46.3     39.8   <- control: must not move; the ~6ms is drift
 *      5 of 25    77.0     63.3
 *     10 of 25   107.3     87.4
 *     25 of 25   206.4    167.4
 */

class WideBenchRow extends Model
{
    protected $table = 'wide_rows';

    protected $guarded = [];

    protected $casts = ['flag' => 'bool'];
}

class WideBenchHost extends Component
{
    use WithTable;

    /** How many of the 25 columns are inline-editable. */
    public int $editable = 0;

    public function table(Table $table): Table
    {
        $columns = [];

        for ($i = 1; $i <= 25; $i++) {
            if ($i <= $this->editable) {
                $columns[] = match ($i % 3) {
                    0 => ToggleColumn::make('flag'),
                    1 => TextInputColumn::make('title'),
                    default => SelectColumn::make('role')->options(['a' => 'A', 'b' => 'B']),
                };

                continue;
            }

            $columns[] = $i % 5 === 0
                ? BadgeColumn::make('role')->colors(['a' => 'success', 'b' => 'gray'])
                : TextColumn::make('title');
        }

        return $table->model(WideBenchRow::class)->paginated()->perPage(20)->columns($columns);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    Schema::create('wide_rows', function (Blueprint $t) {
        $t->id();
        $t->string('title');
        $t->string('role')->default('a');
        $t->boolean('flag')->default(false);
        $t->timestamps();
    });

    $now = now();
    $rows = [];
    for ($i = 1; $i <= 200; $i++) {
        $rows[] = ['title' => 'Row '.$i, 'role' => $i % 2 ? 'a' : 'b', 'flag' => (bool) ($i % 2), 'created_at' => $now, 'updated_at' => $now];
    }
    WideBenchRow::insert($rows);
});

afterEach(fn () => Schema::dropIfExists('wide_rows'));

it('measures a 25-column, 20-row page at several editable ratios', function () {
    $out = "\n  25 columns x 20 rows (500 cells), full Livewire render\n";

    foreach ([0, 5, 10, 25] as $editable) {
        // Warm: first render compiles every Blade view and fills the memos.
        Livewire::test(WideBenchHost::class, ['editable' => $editable])->html();

        $runs = 5;
        $t = microtime(true);
        for ($i = 0; $i < $runs; $i++) {
            Livewire::test(WideBenchHost::class, ['editable' => $editable])->html();
        }
        $ms = (microtime(true) - $t) * 1000 / $runs;

        $out .= sprintf("    %2d of 25 editable: %6.1f ms/render\n", $editable, $ms);
    }

    fwrite(STDERR, $out."\n");

    expect(true)->toBeTrue();
});
