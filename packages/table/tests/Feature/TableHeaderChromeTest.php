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

it('marks a clipped region with its own scrollbar, not with an overlay', function () {
    // A region that clips does it in silence, and the sign that there is more
    // used to be three gradients over the edges — kept in sync with the
    // scroller's offsets by an Alpine component, a scroll listener and a
    // ResizeObserver. A scrollbar says the same thing, says how much more, and
    // answers a drag; it is a property of the box, so there is no state to be
    // wrong after a morph. The server's whole part in it is the class and the
    // stylesheet that opts the element off the platform's auto-hiding overlay
    // scrollbar — whether one is actually painted is a browser question, and
    // `verify-table-header-chrome.mjs` measures it there.
    $html = Livewire::test(HeaderChromeHost::class)->html();

    expect($html)->toContain('overflow-x-auto wire-scroller')
        // And nothing is laid over the rows any more.
        ->and($html)->not->toContain('table-scroll-shadow');
});

test('the scrollbar rules ship with the class, and keep the guard that makes them work', function () {
    // Read from the file rather than from a render: `@assets` hoists its body
    // into the page's head through Livewire's asset registry, so a component's
    // own `html()` carries the id of the block and not one line of the CSS.
    //
    // wire-core's, not this package's: a table's region, a table widget's card
    // and a repeater wider than its field are the same box with the same
    // silence, and wire-forms sits below wire-table in the graph.
    $partial = dirname(__DIR__, 3).'/core/resources/views/partials/scroller-assets.blade.php';

    expect(is_file($partial))->toBeTrue()
        ->and(file_get_contents($partial))
        ->toContain('.wire-scroller::-webkit-scrollbar')
        // Declaring the pseudo-element is the whole mechanism: it is what opts an
        // element out of the platform's auto-hiding overlay scrollbar.
        ->toContain('-webkit-appearance: none')
        // The guard is load-bearing, not tidiness. Chrome 121+ ignores every
        // `::-webkit-scrollbar` rule on an element that also sets `scrollbar-width`
        // or `scrollbar-color`, so writing both unconditionally would hand Chrome
        // back the overlay scrollbar this file exists to defeat. Firefox does not
        // support `selector()`, which is what makes the query pick out exactly the
        // engine that needs the standard properties.
        ->toContain('@supports not selector(::-webkit-scrollbar)')
        ->toContain('scrollbar-color');
});

it('clips the card to its own radius, so nothing inside squares off the corner', function () {
    // The card is rounded; a `<tr>` background is not, and neither is a
    // full-height edge gradient. A selected, striped, hovered or row-coloured
    // LAST row paints a rectangle to the card's bottom edge, and without a clip
    // it fills the corner the border is still curving around. Neither can carry
    // a radius of its own — a `<tr>` has no border box to round, and an overlay
    // does not know which of its ends is at the card's edge — so the clip has to
    // live on the element that owns the radius.
    $html = Livewire::test(HeaderChromeHost::class)->html();

    expect($html)->toContain('rounded-2xl border border-gray-200 dark:border-gray-700 overflow-clip')
        // `clip`, never `hidden`: hidden makes the card a scroll container, and
        // that is what a `position: sticky` descendant sticks inside. The
        // stacked cards' group headings stick against the viewport, and would
        // silently stop on a phone. See index.blade.php for the whole argument.
        ->and($html)->not->toContain('rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden');
});
