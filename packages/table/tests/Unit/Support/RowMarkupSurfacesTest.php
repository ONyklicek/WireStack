<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * The row and card markup that only some tables emit.
 *
 * `RowRenderer` and `CardRenderer` assemble their markup in PHP from Blade
 * compiled once per table, which is what removed the per-row morph markers
 * (848–1035 B per row). The branches below are the ones an ordinary fixture
 * never takes — a record url, actions before the cells, a column with a
 * phone-specific rendering, a bordered table — so each is asserted against a
 * table configured for it and a control that is not.
 */
class RmsRow extends Model
{
    protected $table = 'rms_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class RmsHost extends Component
{
    use WithTable;

    public bool $url = false;

    public bool $actionsFirst = false;

    public bool $responsive = false;

    public bool $bordered = false;

    public bool $stacked = false;

    public bool $titleClaimed = false;

    public function mount(
        bool $url = false,
        bool $actionsFirst = false,
        bool $responsive = false,
        bool $bordered = false,
        bool $stacked = false,
        bool $titleClaimed = false,
    ): void {
        $this->url = $url;
        $this->actionsFirst = $actionsFirst;
        $this->responsive = $responsive;
        $this->bordered = $bordered;
        $this->stacked = $stacked;
        $this->titleClaimed = $titleClaimed;
    }

    public function table(Table $table): Table
    {
        $name = TextColumn::make('name');

        if ($this->responsive) {
            // A column that says what it renders on a phone. The two must differ,
            // or renderResponsiveCell() short-circuits to one of them.
            $name->mobileDisplayUsing(fn ($state): string => 'M:'.$state)
                ->desktopDisplayUsing(fn ($state): string => 'D:'.$state);
        }

        // The editable column is the control for the record-url link: a link
        // wrapped around an input would swallow the click that starts the edit.
        $table->model(RmsRow::class)
            ->columns([$name, TextInputColumn::make('note')])
            ->paginated(false);

        // Every column claimed by some other slot, so the title has nothing left
        // to derive itself from.
        if ($this->titleClaimed) {
            $table->mobileCard(fn ($card) => $card->metric('name')->meta('note'));
        }

        if ($this->url) {
            $table->recordUrl(fn (RmsRow $record): string => '/rows/'.$record->id);
        }

        if ($this->actionsFirst) {
            $table->actionsPosition('start')->actions([
                Action::make('open')->label('Open')->action(fn () => null),
            ]);
        }

        return $table
            ->bordered($this->bordered)
            ->stackedOnMobile($this->stacked);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

function rmsHtml(array $params = []): string
{
    return Livewire::test(RmsHost::class, $params)->html();
}

/** The first <tr> of the table body, where the cell order is readable. */
function rmsFirstRow(array $params = []): string
{
    $html = rmsHtml($params);
    $body = substr($html, (int) strpos($html, '<tbody'));

    return substr($body, 0, (int) strpos($body, '</tr>'));
}

beforeEach(function () {
    Schema::create('rms_rows', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('note')->nullable();
    });

    RmsRow::create(['name' => 'Ada', 'note' => 'first']);
});

afterEach(fn () => Schema::dropIfExists('rms_rows'));

it('wraps a cell in the record url, and leaves the editable one alone', function () {
    // recordUrl turns the row into a link to the record. The editable cell keeps
    // its own interaction: an <a> around the input would swallow the click that
    // starts the edit, which is the whole reason the link is per-cell rather
    // than around the row.
    $row = rmsFirstRow(['url' => true]);

    expect($row)->toContain('<a href="/rows/1"')
        ->and(substr_count($row, '<a href="/rows/1"'))->toBe(1)
        ->and(rmsFirstRow())->not->toContain('<a href="/rows/1"');
});

it('escapes the record url it builds', function () {
    // The url reaches an href, so it goes through e() — a record whose url
    // carries a quote must not be able to close the attribute.
    expect(rmsFirstRow(['url' => true]))->not->toContain('href="/rows/1"onmouse');
});

it('puts the action cell before the cells when asked, and after them by default', function () {
    $first = rmsFirstRow(['actionsFirst' => true]);
    $last = rmsFirstRow();

    // The action cell is the one carrying the row action's button.
    $actionAt = strpos($first, 'wire:key="act-1-open"');
    $nameAt = strpos($first, 'Ada');

    expect($actionAt)->not->toBeFalse()
        ->and($actionAt)->toBeLessThan($nameAt)
        // …and the default leaves it off the row entirely, since this fixture
        // only adds an action when it is asking for the leading position.
        ->and($last)->not->toContain('wire:key="act-1-open"');
});

it('renders a column that declares a phone-specific cell through the responsive path', function () {
    // hasResponsiveDisplay() sends the cell down renderResponsiveCell(), which
    // emits both renderings with the breakpoint classes that swap them. The
    // ordinary path emits neither prefix.
    $row = rmsFirstRow(['responsive' => true]);

    expect($row)->toContain('M:Ada')
        ->toContain('D:Ada')
        ->and(rmsFirstRow())->not->toContain('D:Ada');
});

it('borders every cell when the table is bordered', function () {
    expect(rmsFirstRow(['bordered' => true]))->toContain('border border-gray-200')
        ->and(rmsFirstRow())->not->toContain('border border-gray-200');
});

it('links the card title to the record too', function () {
    // The card is the same record rendered again for the width that hides the
    // table, so the affordance the desktop cells get belongs on the one slot a
    // thumb aims at.
    $html = rmsHtml(['stacked' => true, 'url' => true]);

    expect($html)->toContain('<a href="/rows/1"')
        ->and(rmsHtml(['stacked' => true]))->not->toContain('<a href="/rows/1"');
});

it('renders a phone-specific cell in the card as well', function () {
    expect(rmsHtml(['stacked' => true, 'responsive' => true]))->toContain('M:Ada');
});

it('renders a card without a title when every column is claimed by another slot', function () {
    // The title is derived from the first unclaimed column, so a card that names
    // every column as a metric or meta leaves the slot with nothing to promote.
    // It renders the slots it does have rather than dying on the null.
    $html = rmsHtml(['stacked' => true, 'titleClaimed' => true]);

    expect($html)->toContain('data-testid="table-card"')
        // The record is still on the card, through the slots that did resolve.
        ->and($html)->toContain('Ada');
});
