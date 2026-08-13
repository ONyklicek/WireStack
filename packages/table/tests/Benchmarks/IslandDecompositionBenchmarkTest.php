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
 * Render-cost DECOMPOSITION, for sizing what an island can and cannot remove.
 *
 * WideTableBenchmarkTest reports one number per shape. That number is a sum of
 * three things with very different fates under Livewire 4 islands:
 *
 *   framework fixed   hydrate + snapshot + dehydrate + checksum   islands NEVER remove this
 *   chrome            toolbar, filters, modals, footers           an island CAN skip it
 *   rows              per-row render, linear in page size         an island CAN skip most of it
 *
 * Method: sweep the page size and fit a line. The slope is the per-row cost, the
 * intercept is everything that does not scale with rows. A bare Livewire component
 * with an empty view is measured as the framework floor, so the intercept can be
 * split into "framework" and "chrome".
 *
 * Reports, never asserts.
 */

class IdRow extends Model
{
    protected $table = 'id_rows';

    protected $guarded = [];

    protected $casts = ['flag' => 'bool'];
}

class IdEmptyHost extends Component
{
    public function render()
    {
        return view('id-empty');
    }
}

class IdHost extends Component
{
    use WithTable;

    public int $editable = 0;

    public int $rows = 20;

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

        return $table->model(IdRow::class)->paginated()->perPage($this->rows)->columns($columns);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    app('view')->addLocation(sys_get_temp_dir());
    file_put_contents(sys_get_temp_dir().'/id-empty.blade.php', '<div></div>');

    Schema::create('id_rows', function (Blueprint $t) {
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
    IdRow::insert($rows);
});

afterEach(fn () => Schema::dropIfExists('id_rows'));

function idMeasure(callable $render, int $runs = 5): array
{
    $render(); // warm: compiles views, fills memos

    $t = microtime(true);
    for ($i = 0; $i < $runs; $i++) {
        $html = $render();
    }
    $ms = (microtime(true) - $t) * 1000 / $runs;

    return ['ms' => $ms, 'bytes' => strlen((string) $html)];
}

it('decomposes the render into framework, chrome and rows', function () {
    $out = "\n  ── Render-cost decomposition (25 columns) ───────────────────────\n";

    $floor = idMeasure(fn () => Livewire::test(IdEmptyHost::class)->html(), 20);
    $out .= sprintf("  framework floor (empty component): %6.2f ms   %6d B\n\n", $floor['ms'], $floor['bytes']);

    foreach ([0, 10] as $editable) {
        $out .= sprintf("  %d of 25 editable columns\n", $editable);
        $out .= "    rows      ms      bytes     ms/row    B/row\n";

        $points = [];
        foreach ([1, 5, 10, 20, 50] as $n) {
            $m = idMeasure(fn () => Livewire::test(IdHost::class, ['editable' => $editable, 'rows' => $n])->html());
            $points[$n] = $m;
            $out .= sprintf("    %4d  %7.1f  %9d\n", $n, $m['ms'], $m['bytes']);
        }

        // Least-squares fit over the sweep: slope = per-row, intercept = fixed.
        $xs = array_keys($points);
        $n = count($xs);
        $sumX = array_sum($xs);
        $sumY = array_sum(array_map(fn ($p) => $p['ms'], $points));
        $sumXY = 0.0;
        $sumXX = 0.0;
        foreach ($points as $x => $p) {
            $sumXY += $x * $p['ms'];
            $sumXX += $x * $x;
        }
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumXX - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;

        $sumYB = array_sum(array_map(fn ($p) => (float) $p['bytes'], $points));
        $sumXYB = 0.0;
        foreach ($points as $x => $p) {
            $sumXYB += $x * $p['bytes'];
        }
        $slopeB = ($n * $sumXYB - $sumX * $sumYB) / ($n * $sumXX - $sumX * $sumX);
        $interceptB = ($sumYB - $slopeB * $sumX) / $n;

        $chrome = $intercept - $floor['ms'];

        $out .= sprintf(
            "    fit: %.3f ms/row + %.2f ms fixed   (framework %.2f + chrome %.2f)\n",
            $slope, $intercept, $floor['ms'], $chrome
        );
        $out .= sprintf("    fit: %.0f B/row + %.0f B fixed\n\n", $slopeB, $interceptB);

        // What each island seam would leave behind on a 20-row page.
        $full20 = $points[20]['ms'];
        $full20B = (float) $points[20]['bytes'];
        $out .= sprintf("    20-row page, measured full render: %6.1f ms  %7d B\n", $full20, $points[20]['bytes']);
        $out .= sprintf(
            "      toolbar-only interaction (rows island skipped): %6.1f ms (%.0f%% saved)  %7.0f B (%.0f%% saved)\n",
            $intercept, 100 * (1 - $intercept / $full20),
            $interceptB, 100 * (1 - $interceptB / $full20B)
        );
        $oneRow = $floor['ms'] + $slope;
        $oneRowB = $slopeB;
        $out .= sprintf(
            "      single-cell save (per-row island):              %6.1f ms (%.0f%% saved)  %7.0f B (%.0f%% saved)\n\n",
            $oneRow, 100 * (1 - $oneRow / $full20),
            $oneRowB, 100 * (1 - $oneRowB / $full20B)
        );
    }

    fwrite(STDERR, $out."\n");

    expect(true)->toBeTrue();
});
