<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;
use NyonCode\WireTable\WireTableServiceProvider;

/*
 * selectable() + fillHandle() on one table.
 *
 * The fill handle wraps the table in an x-data of its own, INSIDE the selection
 * root — so every selection expression on the header (the select-all checkbox
 * lives under it) is evaluated in a scope stack whose innermost component is the
 * fill handle. Alpine resolves `$root` against the element being evaluated, not
 * against the component that owns the method, so `get pageKeys()` read
 * `data-page-keys` off the fill root, found nothing, and the header checkbox
 * selected zero rows.
 *
 * These tests pin both halves of the fix: the markup shape that causes the
 * shadowing (it is legitimate and stays), and the source no longer asking a
 * magic for the element at gesture time.
 */

class SelFillRow extends Model
{
    protected $table = 'sel_fill_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class SelFillComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(SelFillRow::class)
            ->paginated(false)
            ->columns([
                TextColumn::make('name'),
                // Editable, so the fill handle has somewhere to land — without a
                // fillable column the fill root is not rendered at all.
                TextInputColumn::make('status'),
            ])
            ->selectable()
            ->gestures()
            ->fillHandle();
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('sel_fill_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('status')->nullable();
    });

    SelFillRow::insert([
        ['name' => 'Alpha', 'status' => 'new'],
        ['name' => 'Beta', 'status' => 'new'],
    ]);
});

afterEach(fn () => Schema::dropIfExists('sel_fill_rows'));

it('keeps the page keys on the selection root while the fill handle nests inside it', function () {
    $html = Livewire::test(SelFillComponent::class)->html();

    $selectionRoot = strpos($html, 'data-selection-root');
    $fillRoot = strpos($html, 'data-fill-root');
    $selectAll = strpos($html, 'data-testid="table-select-all"');
    $pageKeys = strpos($html, 'data-page-keys');

    expect($selectionRoot)->toBeInt('the selection root is rendered')
        ->and($fillRoot)->toBeInt('the fill root is rendered')
        ->and($selectAll)->toBeInt('the header select-all checkbox is rendered');

    // The nesting, stated: selection root → fill root → header checkbox. This is
    // the shape that shadows `$root`, and it is the intended shape — `relative`
    // on the fill root is the positioning context for the handle and its range
    // overlay, so it has to be the scroller around the table.
    expect($selectionRoot)->toBeLessThan($fillRoot)
        ->and($fillRoot)->toBeLessThan($selectAll);

    // Both selection datasets belong to the selection root and only to it: they
    // sit in the same opening tag as the marker, and nothing re-declares them on
    // the fill root.
    // The whole opening tag around a marker attribute, in either direction —
    // attribute order in the template is not part of the contract.
    $tagAround = function (int $at) use ($html): string {
        $start = (int) strrpos(substr($html, 0, $at), '<');

        return substr($html, $start, (int) strpos($html, '>', $at) - $start + 1);
    };

    $rootTag = $tagAround($selectionRoot);
    $fillTag = $tagAround($fillRoot);

    expect($pageKeys)->toBeLessThan($selectionRoot) // both are attributes of the same tag
        ->and($rootTag)->toContain('data-matching')
        ->and($rootTag)->toContain('wireRecordSelection(')
        ->and($fillTag)->toContain('wireFillHandle(')
        ->and($fillTag)->not->toContain('data-page-keys')
        ->and(substr_count($html, 'data-page-keys'))->toBe(1)
        ->and(substr_count($html, 'data-selection-root'))->toBe(1);
});

it('reads the selection datasets off a captured element, never off $root', function () {
    // `$root` (like every Alpine magic) resolves against the element the
    // expression is evaluated on. Under the fill handle's x-data that is the
    // wrong element, so the component captures its own root once in init() and
    // reads the datasets from that — from a closure, not a property, because a
    // property is looked up through the same shadowable merged scope.
    $source = file_get_contents(
        dirname(WireTableServiceProvider::ASSETS_PATH).'/resources/js/record-selection.js'
    );

    expect($source)->not->toContain('$root.dataset')
        ->toContain("root = el?.closest?.('[data-selection-root]')")
        ->toContain('get pageKeys() { return JSON.parse(root?.dataset.pageKeys')
        ->toContain('get matching() { return Number(root?.dataset.matching');

    // Same guarantee in what actually ships (needs `npm run build:table-assets`).
    $bundle = file_get_contents(WireTableServiceProvider::ASSETS_PATH.'/wire-table-selection.js');

    expect($bundle)->not->toContain('$root.dataset')
        ->toContain('[data-selection-root]');
});

it('wraps the inline fallback so its top-level declarations stay function-scoped', function () {
    // The fallback injects this source verbatim into a classic <script>, where a
    // bare top-level `let` becomes a GLOBAL lexical binding — a second copy in
    // one document would be a SyntaxError that kills the whole script (and with
    // it the wrapper's x-data: search, filters, the bulk bar, the modal hosts).
    // esbuild gives the bundle an IIFE; this gives the fallback the same one.
    $partial = file_get_contents(
        dirname(WireTableServiceProvider::ASSETS_PATH)
        .'/resources/views/tables/partials/selection-assets.blade.php'
    );

    $open = strpos($partial, '<script>(function () {');
    $inline = strpos($partial, 'file_get_contents(');
    $close = strpos($partial, '})();</script>');

    expect($open)->toBeInt()
        ->and($inline)->toBeInt()
        ->and($close)->toBeInt()
        ->and($open)->toBeLessThan($inline)
        ->and($inline)->toBeLessThan($close);
});
