<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\Enums\Breakpoint;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Support\ClientViewport;
use NyonCode\WireTable\Table;

/**
 * A stacked table sends only the half the browser shows.
 *
 * `stackedOnMobile()` renders every record twice — a row and a card — and CSS
 * hides one. With the `wire_viewport` cookie the server knows which, and these
 * pin the three answers: no cookie is both halves exactly as before, a wide
 * window is the table alone, a narrow one the cards alone — and every partial a
 * write sends follows the same answer, because the other half has no anchor in
 * that browser.
 */
class VloRow extends Model
{
    protected $table = 'vlo_rows';

    protected $guarded = [];
}

class VloHost extends Component
{
    use WithTable;

    public static ?Closure $configure = null;

    public function table(Table $table): Table
    {
        $table
            ->model(VloRow::class)
            ->stackedOnMobile()
            ->rowPartials()
            ->columns([
                TextInputColumn::make('name'),
                TextColumn::make('amount')->summarizeSum(),
            ]);

        return self::$configure !== null ? (self::$configure)($table) : $table;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    VloHost::$configure = null;

    Schema::create('vlo_rows', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->integer('amount')->default(1);
        $t->timestamps();
    });

    VloRow::create(['name' => 'First', 'amount' => 1]);
    VloRow::create(['name' => 'Second', 'amount' => 2]);
});

afterEach(fn () => Schema::dropIfExists('vlo_rows'));

function vloTest(?string $viewport): Testable
{
    $livewire = $viewport === null ? Livewire::getFacadeRoot() : Livewire::withCookie(ClientViewport::COOKIE, $viewport);

    return $livewire->test(VloHost::class);
}

it('emits the half the window shows', function (?string $viewport, bool $table, bool $cards, string $marker) {
    $html = vloTest($viewport)->html();

    expect(str_contains($html, '<table'))->toBe($table)
        ->and(str_contains($html, 'data-testid="table-card"'))->toBe($cards)
        ->and($html)->toContain('data-wire-layout="'.$marker.'"')
        ->and($html)->toContain('data-wire-layout-query="(min-width: 48rem)"');
})->with([
    'no cookie — both, as before' => [null, true, true, 'both'],
    'a forged value — both' => ['huge', true, true, 'both'],
    'at the breakpoint — table' => ['md', true, false, 'table'],
    'above it — table' => ['2xl', true, false, 'table'],
    'below it — cards' => ['sm', false, true, 'cards'],
    'under every breakpoint — cards' => ['base', false, true, 'cards'],
]);

it('keeps the CSS swap only while both halves are in the document', function () {
    // The swap is what hides one of two; with one half there is nothing to swap
    // it for, and a stale cookie should leave it showing rather than blank.
    expect(vloTest(null)->html())->toContain('hidden md:block')->toContain('md:hidden')
        ->and(vloTest('lg')->html())->not->toContain('hidden md:block')
        ->and(vloTest('sm')->html())->not->toContain('md:hidden');
});

it('reads the cookie against the table’s own breakpoint', function () {
    VloHost::$configure = fn (Table $table) => $table->stackedOnMobile(breakpoint: Breakpoint::Lg);

    $html = vloTest('md')->html();

    expect($html)->toContain('data-wire-layout="cards"')
        ->toContain('data-wire-layout-query="(min-width: 64rem)"')
        ->not->toContain('<table');
});

it('emits both halves where the table opts out', function (string $where) {
    match ($where) {
        'table' => VloHost::$configure = fn (Table $table) => $table->renderVisibleLayoutOnly(false),
        'config' => config(['wire-table.defaults.visible_layout_only' => false]),
    };

    $html = vloTest('sm')->html();

    expect($html)->toContain('<table')
        ->toContain('data-testid="table-card"')
        ->not->toContain('data-wire-layout=');
})->with(['per table' => 'table', 'in config' => 'config']);

it('leaves a table that does not stack alone', function () {
    VloHost::$configure = fn (Table $table) => $table->stackedOnMobile(false);

    expect(vloTest('sm')->html())->toContain('<table')->not->toContain('data-wire-layout=');
});

it('answers a write with the partials of the emitted half', function (?string $viewport, array $sent, array $withheld) {
    $test = vloTest($viewport)->call('updateTableCell', 1, 'name', 'Renamed');

    expect(array_keys($test->effects['wirePartials'] ?? []))->toContain(...$sent)
        ->not->toContain(...$withheld);
})->with([
    'both halves' => [null, ['row-1', 'card-1', 'summary', 'summary-mobile'], ['row-2']],
    'the table' => ['lg', ['row-1', 'summary'], ['card-1', 'summary-mobile']],
    'the cards' => ['sm', ['card-1', 'summary-mobile'], ['row-1', 'summary']],
]);

it('puts the viewport script on the page only for a table that trims', function (bool $stacked, int $expected) {
    // @assets is hoisted out of the component markup, so the partial is counted
    // as it renders rather than looked for in the HTML.
    $count = 0;
    View::composer('wire-table::tables.partials.viewport-assets', function () use (&$count): void {
        $count++;
    });
    VloHost::$configure = fn (Table $table) => $table->stackedOnMobile($stacked);

    vloTest(null);

    expect($count)->toBe($expected);
})->with([
    'stacked' => [true, 1],
    'not stacked' => [false, 0],
]);

it('names the same ladder as the script that writes the cookie', function () {
    // The script cannot import the enum, so it carries its own copy; this is
    // what stops the two disagreeing about which side of a breakpoint a window
    // is on.
    $script = file_get_contents(dirname(__DIR__, 2).'/resources/js/record-viewport.js');

    preg_match('/const LADDER = (\[.*?\])\n/', $script, $match);
    $ladder = json_decode(str_replace("'", '"', $match[1]), true);

    expect($ladder)->toBe(array_map(
        fn (Breakpoint $breakpoint): array => [$breakpoint->value, $breakpoint->minWidth()],
        Breakpoint::cases(),
    ))->and($script)->toContain("'".ClientViewport::COOKIE."'");
});

it('answers null for anything that is not a breakpoint name', function (mixed $value, ?bool $reachesMd) {
    $request = Request::create('/', cookies: $value === null ? [] : [ClientViewport::COOKIE => $value]);

    expect(ClientViewport::reaches(Breakpoint::Md, $request))->toBe($reachesMd);
})->with([
    'missing' => [null, null],
    'empty' => ['', null],
    'an array' => [['md'], null],
    'unknown' => ['xxl', null],
    'base' => ['base', false],
    'sm' => ['sm', false],
    'md' => ['md', true],
    'xl' => ['xl', true],
]);
