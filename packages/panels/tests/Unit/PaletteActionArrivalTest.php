<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireForms\Concerns\WithActions;
use NyonCode\WirePanels\Resources\Concerns\BelongsToResource;
use NyonCode\WirePanels\Resources\Concerns\ResolvesOneRecord;

/*
 * The far end of the command palette's hand-off.
 *
 * The palette cannot host a modal — it is in a sibling module that may not import
 * the Actions one — so an action that has to ask something is answered by
 * navigating to the page that owns the record, with the action named in the query
 * string. `BelongsToResource` reads it there.
 *
 * Read in the trait's mount hook rather than in a page's own `mount()`, and that
 * placement is the point: Livewire calls the component's `mount()` first, so by
 * the time this runs the record is resolved — which is what `canExecute($record)`
 * needs in order to answer about anything at all.
 *
 * ## What this does not prove
 *
 * That any *shipped* page answers it. None do: `ListPage` composes `WithTable`,
 * the form pages compose `WithForms`, and `ViewPage` deliberately composes no host
 * trait at all (ADR 0020 Q2) — so none of them has `mountAction()`. The seam is
 * real and an application that composes `WithActions` on its own page gets it,
 * which is what the host below is. On a stock page the query parameter is inert
 * by design rather than by accident, and the palette's other two branches are
 * what carry the feature there.
 */
class PaOrder extends Model
{
    protected $table = 'pa_orders';

    protected $guarded = [];

    public $timestamps = false;
}

class PaOrderResource implements DescribesResource
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return PaOrder::class;
    }
}

/** A page that owns an action host, which is what the hand-off needs. */
class PaHostPage extends Component
{
    use BelongsToResource;
    use ResolvesOneRecord;
    use WithActions;

    protected static ?string $resource = PaOrderResource::class;

    /** Set by the plain action, so a test can see it actually ran. */
    public static mixed $ran = null;

    public function getTitle(): string
    {
        return 'Order';
    }

    protected function actions(): array
    {
        return [
            Action::make('plain')->action(function ($record): void {
                static::$ran = $record?->getKey() ?? 'no-record';
            }),
            Action::make('asks')->requiresConfirmation()->action(fn () => null),
            Action::make('forbidden')->hidden()->action(function (): void {
                static::$ran = 'forbidden';
            }),
        ];
    }

    public function render(): string
    {
        return '<div>host</div>';
    }
}

/** The same page without a host, which is every shipped resource page. */
class PaHostlessPage extends Component
{
    use BelongsToResource;
    use ResolvesOneRecord;

    protected static ?string $resource = PaOrderResource::class;

    public function getTitle(): string
    {
        return 'Order';
    }

    public function render(): string
    {
        return '<div>hostless</div>';
    }
}

beforeEach(function () {
    Schema::create('pa_orders', function (Blueprint $table): void {
        $table->id();
        $table->string('number');
    });

    PaOrder::create(['number' => 'INV-1']);

    PaHostPage::$ran = null;
});

afterEach(function () {
    Schema::dropIfExists('pa_orders');
});

it('runs the action the palette sent the page here to open', function () {
    // With the record, not without it: the page resolved it on mount, and an
    // action gated on `$record` would otherwise be asked about null.
    Livewire::withQueryParams(['action' => 'plain'])
        ->test(PaHostPage::class, ['record' => 1]);

    expect(PaHostPage::$ran)->toBe(1);
});

it('opens the modal for an action that has to ask', function () {
    // The whole reason the palette hands this one over rather than running it.
    Livewire::withQueryParams(['action' => 'asks'])
        ->test(PaHostPage::class, ['record' => 1])
        ->assertSet('actionModalOpen', true);

    expect(PaHostPage::$ran)->toBeNull();
});

it('refuses an action the user may not run, however it arrived', function () {
    // The query string is user input. The gate is `mountAction()`'s own
    // `canExecute()`, which is why arriving by URL is not a way around it.
    Livewire::withQueryParams(['action' => 'forbidden'])
        ->test(PaHostPage::class, ['record' => 1]);

    expect(PaHostPage::$ran)->toBeNull();
});

it('does nothing for a name no action answers to', function () {
    // A pasted or stale link should leave the user on the page they asked for,
    // not on an error about a URL they did not write.
    Livewire::withQueryParams(['action' => 'nonsense'])
        ->test(PaHostPage::class, ['record' => 1])
        ->assertOk();

    expect(PaHostPage::$ran)->toBeNull();
});

it('does nothing when no action is named', function () {
    Livewire::test(PaHostPage::class, ['record' => 1])->assertOk();

    expect(PaHostPage::$ran)->toBeNull();
});

it('does nothing on a page that owns no action host', function () {
    // Every shipped resource page is this one. It renders, and the parameter is
    // simply not answered.
    Livewire::withQueryParams(['action' => 'plain'])
        ->test(PaHostlessPage::class, ['record' => 1])
        ->assertOk()
        ->assertSee('hostless');
});
