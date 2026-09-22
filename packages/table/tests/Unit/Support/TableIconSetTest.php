<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\Icons\IconManager;
use NyonCode\WireCore\Foundation\Icons\IconSet;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Support\Icons\TableIconSet;
use NyonCode\WireTable\Table;

/*
 * The marks inside the table's hand-drawn selection checkbox.
 *
 * The table is the only surface in the stack that draws a checkbox itself
 * instead of styling a native `<input>`, and it used to borrow Heroicons' solid
 * `check` for the mark: a filled silhouette ~1.2 px thick at 16 px, spanning its
 * whole viewBox, which inside a 16 px bordered box came out as a hairline jammed
 * into the corners.
 *
 * So the assertions below are about the glyph's PROPERTIES rather than its path
 * data — stroked, 16x16, inset — because those are what make it legible, and
 * about the one thing that is genuinely fragile: the direction of the
 * animation's fallback.
 */
class TisRow extends Model
{
    protected $table = 'tis_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class TisHost extends Component
{
    use WithTable;

    public bool $stacked = false;

    public function mount(bool $stacked = false): void
    {
        $this->stacked = $stacked;
    }

    public function table(Table $table): Table
    {
        return $table->model(TisRow::class)
            ->columns([TextColumn::make('name')])
            ->selectable()
            ->stackedOnMobile($this->stacked)
            ->paginated(false);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('tis_rows', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });

    TisRow::create(['name' => 'Ada']);
});

afterEach(function () {
    Schema::dropIfExists('tis_rows');
});

it('answers for its own glyphs and for nothing else', function () {
    $set = new TableIconSet;

    expect($set->has('checkbox-check'))->toBeTrue()
        ->and($set->names())->toBe(['checkbox-check', 'checkbox-indeterminate'])
        // Heroicons stays the owner of the general-purpose tick and dash. A
        // caller wanting one in a button or a badge still wants the solid ones;
        // these two are shaped for a 16 px box and nothing else.
        ->and($set->has('check'))->toBeFalse()
        ->and($set->has('minus'))->toBeFalse()
        ->and($set->getPath('check'))->toBeNull()
        ->and($set->getIcon('check'))->toBeNull();
});

it('describes its own format, so the marks are stroked rather than filled at 20x20', function () {
    // A plain IconSet is wrapped in the Heroicons solid format (0 0 20 20, fill).
    // These are drawn for a 16 px box and are legible there only because they are
    // stroked, so the set implements ProvidesIconMetadata and says so itself.
    $icon = (new TableIconSet)->getIcon('checkbox-check');

    expect($icon)->not->toBeNull()
        ->and($icon->viewBox)->toBe('0 0 16 16')
        ->and($icon->attributes)->toBe([
            'fill' => 'none',
            'stroke' => 'currentColor',
            'stroke-width' => '2',
            'stroke-linecap' => 'round',
            'stroke-linejoin' => 'round',
        ]);
});

it('keeps the mark clear of the box it sits in', function () {
    // The Heroicons check spans its whole viewBox, which is why it pressed into
    // the corners of a bordered 16 px button. Every coordinate in these two must
    // leave room for the 1-unit half-stroke plus the border: inside [2, 14].
    $set = new TableIconSet;

    foreach ($set->names() as $name) {
        preg_match_all('/-?\d+(?:\.\d+)?/', (string) preg_replace('/^.*\bd="([^"]*)".*$/s', '$1', (string) $set->getPath($name)), $matches);

        expect($matches[0])->not->toBeEmpty();

        foreach ($matches[0] as $coordinate) {
            expect((float) $coordinate)->toBeGreaterThanOrEqual(2.0)
                ->and((float) $coordinate)->toBeLessThanOrEqual(14.0);
        }
    }
});

it('is registered under the table prefix at boot', function () {
    $icons = app(IconManager::class);

    expect($icons->has('table:checkbox-check'))->toBeTrue()
        ->and($icons->has('table:checkbox-indeterminate'))->toBeTrue()
        ->and($icons->render('table:checkbox-check', 'w-4 h-4'))
        ->toContain('viewBox="0 0 16 16"')
        ->toContain('stroke-width="2"');
});

it('leaves the tick drawn when nothing declares an offset', function () {
    // The load-bearing half of the animation, and the one thing here that could
    // fail silently in a consumer's build. The mark is drawn by a dash long
    // enough to cover its own path, and the glyph declares NO stroke-dashoffset
    // — an undeclared one computes to 0, which is the fully drawn tick. The
    // selection cell then animates the offset down to 0 from a hidden 12.
    //
    // Were it the other way round — hidden in the glyph, revealed by a utility —
    // a Tailwind build that never emitted `[stroke-dashoffset:0]` would hide the
    // mark permanently, and a checkbox that never ticks reads as broken rather
    // than as unanimated.
    $check = (string) (new TableIconSet)->getPath('checkbox-check');

    expect($check)->toContain('stroke-dasharray="11"')
        ->and($check)->not->toContain('stroke-dashoffset');

    $cell = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/tables/partials/selection-cell.blade.php');

    expect($cell)->toContain('x-transition:enter-start="[stroke-dashoffset:12]"')
        ->and($cell)->toContain('x-transition:enter-end="[stroke-dashoffset:0]"');
});

it('lets an application swap the marks by re-registering the prefix', function () {
    // The documented extension point, and the reason these are an icon set at all
    // rather than an <svg> in the partial: a consumer restyles the checkbox from a
    // provider's boot() instead of publishing `tables.partials.selection-cell`.
    // `registerIconSet` overwrites the prefix and flushes the render cache, so the
    // swap reaches a table rendered later in the same process.
    app(IconManager::class)->registerIconSet(new class implements IconSet
    {
        public function getPath(string $name): ?string
        {
            return $name === 'checkbox-check' ? '<path d="M3 3 13 13"/>' : null;
        }

        public function has(string $name): bool
        {
            return $name === 'checkbox-check';
        }

        /** @return array<int, string> */
        public function names(): array
        {
            return ['checkbox-check'];
        }
    }, 'table');

    $html = Livewire::test(TisHost::class)->html();

    expect($html)->toContain('M3 3 13 13')
        ->and($html)->not->toContain('M4.5 8.5 7 11 11.5 5.5');
});

it('draws one mark in the row, the header and the card, and no inline svg', function () {
    foreach ([false, true] as $stacked) {
        $html = Livewire::test(TisHost::class, ['stacked' => $stacked])->html();

        $svgs = substr_count($html, '<svg');
        $stamped = preg_match_all('/<svg[^>]*aria-hidden="true"/', $html);

        // Every <svg> came out of IconManager, which stamps each one it resolves.
        // A hand-written one in a template would not be stamped.
        expect($svgs)->toBeGreaterThan(0)
            ->and($stamped)->toBe($svgs)
            // The row's tick and the header's select-all are the same string, so
            // the two boxes cannot drift apart the way a tick and a Heroicons
            // dash did.
            ->and(substr_count($html, 'M4.5 8.5 7 11 11.5 5.5'))->toBeGreaterThanOrEqual($stacked ? 3 : 2)
            // And the mark stretches to the button's content box instead of
            // carrying a 16 px size that sat a pixel off centre inside it.
            ->and($html)->not->toContain('h-4 w-4 absolute inset-0 text-white');
    }
});
