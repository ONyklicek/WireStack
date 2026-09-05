<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * The chrome around the rows: how a column header is cased, whether it stays in
 * view, and whether a clipped table says so.
 *
 * All three are CSS the server emits, and all three were invisible to a test
 * that only asked for the labels — the casing bug below shipped for exactly that
 * reason. `<thead>` carried `uppercase` and every header read as uppercase in
 * the markup, but a sortable one renders inside a `<button>`, and the UA
 * stylesheet sets `text-transform: none` on form controls. Tailwind's preflight
 * inherits font family, size and weight onto a button and leaves that one alone,
 * so "Name" sat beside "ROLE" in the same header row. Nothing but the class
 * string is checkable from here, which is why the browser driver
 * (workbench/scripts/verify-table-header-chrome.mjs) reads the computed style
 * back instead.
 */

class HeaderChromeUser extends Model
{
    protected $table = 'header_chrome_users';

    protected $guarded = [];
}

class HeaderChromeHost extends Component
{
    use WithTable;

    public bool $sticky = false;

    public ?string $stickyMaxHeight = null;

    public function table(Table $table): Table
    {
        $table
            ->model(HeaderChromeUser::class)
            ->columns([
                TextColumn::make('name')->sortable(),
                TextColumn::make('role'),
            ])
            ->paginated(false);

        if ($this->sticky) {
            $this->stickyMaxHeight === null
                ? $table->stickyHeader()
                : $table->stickyHeader(maxHeight: $this->stickyMaxHeight);
        }

        return $table;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('header_chrome_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('role');
        $table->timestamps();
    });

    HeaderChromeUser::create(['name' => 'Ada Lovelace', 'role' => 'admin']);
});

afterEach(fn () => Schema::dropIfExists('header_chrome_users'));

it('restates the header casing on the sort button, which does not inherit it', function () {
    $html = Livewire::test(HeaderChromeHost::class)->html();

    // The button that carries a sortable label.
    expect($html)->toContain('data-testid="table-sort-name"');

    $button = mb_substr($html, (int) mb_strpos($html, 'data-testid="table-sort-name"'), 600);

    expect($button)->toContain('uppercase');
});

it('leaves the header unpinned and the scroll region uncapped by default', function () {
    Livewire::test(HeaderChromeHost::class)
        ->assertSee('bg-gray-50 dark:bg-gray-800/50', escape: false)
        ->assertDontSee('sticky top-0', escape: false)
        ->assertDontSee('max-height:', escape: false);
});

it('pins the header and caps the region it pins against', function () {
    Livewire::test(HeaderChromeHost::class)
        ->set('sticky', true)
        // Both halves of the one decision: an uncapped scrollport never scrolls,
        // and a header pinned inside one never moves.
        ->assertSee('sticky top-0 z-10', escape: false)
        ->assertSee('max-height: '.Table::DEFAULT_STICKY_MAX_HEIGHT, escape: false)
        // Opaque, unlike the resting `dark:bg-gray-800/50`: a translucent header
        // shows the rows travelling underneath it.
        ->assertSee('sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 ', escape: false);
});

it('honours a named cap', function () {
    Livewire::test(HeaderChromeHost::class)
        ->set('sticky', true)
        ->set('stickyMaxHeight', '32rem')
        ->assertSee('max-height: 32rem', escape: false)
        ->assertDontSee('max-height: '.Table::DEFAULT_STICKY_MAX_HEIGHT, escape: false);
});

it('frames the scroll region with an edge shadow for each edge there is more past', function () {
    // All three are in the document at every size; Alpine decides which show,
    // from the scroller's own scroll offsets. The server only guarantees they
    // are there and that they cannot swallow a tap meant for the rows beneath.
    $html = Livewire::test(HeaderChromeHost::class)->html();

    expect($html)->toContain('data-testid="table-scroll-shadow-start"')
        ->and($html)->toContain('data-testid="table-scroll-shadow-end"')
        ->and($html)->toContain('data-testid="table-scroll-shadow-bottom"')
        // Three, not four: the top edge is where a sticky header already sits,
        // and an uncapped region cannot scroll vertically at all.
        ->and(substr_count($html, 'data-testid="table-scroll-shadow-'))->toBe(3)
        ->and($html)->not->toContain('table-scroll-shadow-top')
        // Decoration only: they must not swallow a tap meant for the rows
        // beneath, and a screen reader has the table itself.
        ->and(substr_count($html, 'pointer-events-none absolute'))->toBeGreaterThanOrEqual(3)
        ->and(substr_count($html, 'aria-hidden="true"'))->toBeGreaterThanOrEqual(3);
});
