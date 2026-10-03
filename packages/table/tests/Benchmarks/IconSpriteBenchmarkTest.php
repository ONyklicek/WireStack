<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireCore\Actions\DeleteAction;
use NyonCode\WireCore\Actions\EditAction;
use NyonCode\WireCore\Foundation\Icons\IconSprite;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\BooleanColumn;
use NyonCode\WireTable\Columns\IconColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * What the icon sprite saves on a table that draws icons in every row: 50 rows,
 * a selectable table (the checkbox mark), a boolean and an icon column, a badge
 * with an icon, a text column with an icon, and three row actions plus a
 * three-item group — the shape of an ordinary back-office list.
 *
 * Reports, never asserts, and does not run in `composer test` (the root
 * phpunit.xml carries Unit and Feature only).
 *
 * One developer machine, PHP 8.5 — 605 icons on the page (desktop rows and the
 * stacked cards), 17 distinct bodies:
 *
 *                   raw HTML      gzip    render
 *     sprite off    851 965 B   22 315 B   ~190 ms
 *     sprite on     705 288 B   17 297 B   ~178 ms
 *     saved           17.2 %     22.5 %    within noise
 */

class SpriteBenchRow extends Model
{
    protected $table = 'sprite_rows';

    protected $guarded = [];

    protected $casts = ['flag' => 'bool'];
}

class SpriteBenchHost extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(SpriteBenchRow::class)
            ->paginated()
            ->perPage(50)
            ->selectable()
            ->columns([
                TextColumn::make('title')->icon('document-text'),
                BooleanColumn::make('flag'),
                IconColumn::make('role')->icons(['a' => 'check-circle', 'b' => 'x-circle']),
                BadgeColumn::make('role')->colors(['a' => 'success', 'b' => 'gray'])->icons(['a' => 'check', 'b' => 'clock']),
            ])
            ->actions([
                Action::make('view')->icon('eye')->action(fn () => null),
                EditAction::make()->action(fn () => null),
                DeleteAction::make()->action(fn () => null),
                ActionGroup::make([
                    Action::make('duplicate')->icon('document-duplicate')->action(fn () => null),
                    Action::make('archive')->icon('archive-box')->action(fn () => null),
                    Action::make('print')->icon('printer')->action(fn () => null),
                ]),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    Schema::create('sprite_rows', function (Blueprint $t) {
        $t->id();
        $t->string('title');
        $t->string('role')->default('a');
        $t->boolean('flag')->default(false);
        $t->timestamps();
    });

    $now = now();
    $rows = [];
    for ($i = 1; $i <= 50; $i++) {
        $rows[] = ['title' => 'Row '.$i, 'role' => $i % 2 ? 'a' : 'b', 'flag' => (bool) ($i % 2), 'created_at' => $now, 'updated_at' => $now];
    }
    SpriteBenchRow::insert($rows);
});

afterEach(fn () => Schema::dropIfExists('sprite_rows'));

it('measures the markup a 50-row table with icons sends, sprite off and on', function () {
    $out = "\n  50 rows, 4 icon columns, 3 actions + a 3-item group, selectable\n";
    $sizes = [];

    foreach (['off' => false, 'on' => true] as $label => $on) {
        config()->set('wire-core.icons.sprite', $on);
        app()->forgetInstance(IconSprite::class);

        Livewire::test(SpriteBenchHost::class)->html(); // warm the views

        $runs = 5;
        $t = microtime(true);
        for ($i = 0; $i < $runs; $i++) {
            $html = Livewire::test(SpriteBenchHost::class)->html();
        }
        $ms = (microtime(true) - $t) * 1000 / $runs;

        $sizes[$label] = [strlen($html), strlen((string) gzencode($html, 6))];

        $out .= sprintf(
            "    sprite %-3s  %8d B raw  %7d B gzip  %6.1f ms  %4d <svg>  %3d <symbol>\n",
            $label,
            $sizes[$label][0],
            $sizes[$label][1],
            $ms,
            substr_count($html, '<svg'),
            substr_count($html, '<symbol'),
        );
    }

    $out .= sprintf(
        "    saved        %7.1f %% raw  %6.1f %% gzip\n",
        100 * (1 - $sizes['on'][0] / $sizes['off'][0]),
        100 * (1 - $sizes['on'][1] / $sizes['off'][1]),
    );

    fwrite(STDERR, $out."\n");

    expect(true)->toBeTrue();
});
