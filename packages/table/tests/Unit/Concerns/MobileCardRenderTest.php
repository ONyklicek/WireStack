<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Support\MobileCardConfig;
use NyonCode\WireTable\Table;

class McInvoice extends Model
{
    protected $table = 'mc_invoices';

    protected $guarded = [];

    public $timestamps = false;

    /** @return HasMany<McItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(McItem::class, 'invoice_id');
    }
}

class McItem extends Model
{
    protected $table = 'mc_items';

    protected $guarded = [];

    public $timestamps = false;
}

class McComponent extends Component
{
    use WithTable;

    public bool $withActions = false;

    public bool $withLimit = false;

    public bool $explicitCard = false;

    public bool $groupedSubRowActions = false;

    public bool $countChildren = false;

    public function table(Table $table): Table
    {
        $table
            ->model(McInvoice::class)
            ->paginated(false)
            ->stackedOnMobile()
            ->columns([
                TextColumn::make('number'),
                TextColumn::make('customer'),
                BadgeColumn::make('status'),
                TextColumn::make('note'),
                TextColumn::make('total')->alignRight(),
            ])
            ->subRows('items')
            ->subRowColumns([
                TextColumn::make('product'),
                TextColumn::make('unit_price'),
                TextColumn::make('line_total')->alignRight()->summarizeSum('Subtotal', scope: 'subRows'),
            ]);

        if ($this->countChildren) {
            $table->query(McInvoice::query()->withCount('items'));
        }

        if ($this->explicitCard) {
            $table->mobileCard(fn (MobileCardConfig $card) => $card->title('customer')->metric('total'));
        }

        if ($this->withActions) {
            $table->subRowActions([
                Action::make('edit')->label('Edit item'),
                Action::make('remove')->label('Remove item'),
            ]);
        }

        if ($this->groupedSubRowActions) {
            $table->subRowActions([
                ActionGroup::make([
                    Action::make('edit')->label('Edit item'),
                    Action::make('divider')->divider(),
                    Action::make('remove')->label('Remove item'),
                ]),
                Action::make('sep')->divider(),
                Action::make('duplicate')->label('Duplicate item'),
            ]);
        }

        if ($this->withLimit) {
            $table->subRowsLimit(1);
        }

        return $table;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('mc_invoices', function (Blueprint $table) {
        $table->id();
        $table->string('number');
        $table->string('customer');
        $table->string('status');
        $table->string('note');
        $table->integer('total');
    });

    Schema::create('mc_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('invoice_id');
        $table->string('product');
        $table->integer('unit_price');
        $table->integer('line_total');
    });

    $invoice = McInvoice::create([
        'number' => 'INV-1001', 'customer' => 'Northwind', 'status' => 'paid',
        'note' => 'first order', 'total' => 9350,
    ]);

    McItem::create(['invoice_id' => $invoice->id, 'product' => 'Monitor', 'unit_price' => 5600, 'line_total' => 5600]);
    McItem::create(['invoice_id' => $invoice->id, 'product' => 'Keyboard', 'unit_price' => 1200, 'line_total' => 2400]);
});

afterEach(function () {
    Schema::dropIfExists('mc_items');
    Schema::dropIfExists('mc_invoices');
});

// ─── Card hierarchy ──────────────────────────────────────────────────────────

it('renders the metric on the title line instead of as a label/value pair', function () {
    Livewire::test(McComponent::class)
        ->assertSee('table-card-metric', escape: false)
        // The figure is not repeated as a "TOTAL" dt/dd below.
        ->assertDontSee('<dt class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-0.5">
                                                        Total', escape: false);
});

it('keeps unclaimed columns in the label/value grid', function () {
    Livewire::test(McComponent::class)->assertSee('Note');
});

it('honours an explicit mobileCard() over derivation', function () {
    $component = new McComponent;
    $component->explicitCard = true;
    $component->mountWithTable();

    $card = $component->getTable()->getMobileCard($component->getTable()->getColumns());

    expect($card->title()?->getName())->toBe('customer')
        ->and($card->metric()?->getName())->toBe('total');
});

it('memoizes the resolved card per column set', function () {
    $component = new McComponent;
    $component->mountWithTable();
    $table = $component->getTable();

    $first = $table->getMobileCard($table->getColumns());

    expect($table->getMobileCard($table->getColumns()))->toBe($first)
        // A different column set must not hand back the memoized card.
        ->and($table->getMobileCard([$table->getColumns()[0]]))->not->toBe($first);
});

// ─── Sub-rows on the card ────────────────────────────────────────────────────

it('renders a collapsed child toggle on the card', function () {
    Livewire::test(McComponent::class)
        ->assertSee('table-card-subrows-toggle', escape: false)
        ->assertSee('aria-expanded="false"', escape: false)
        ->assertDontSee('table-card-sub-row', escape: false);
});

it('renders child rows with their subtotal once expanded', function () {
    Livewire::test(McComponent::class)
        ->call('toggleRowExpansion', '1')
        ->assertSee('table-card-sub-row', escape: false)
        ->assertSee('Monitor')
        ->assertSee('Keyboard')
        // The per-parent subtotal the desktop panel has, which the card lacked.
        ->assertSee('table-card-subrows-summary', escape: false)
        ->assertSee('Subtotal');
});

it('renders the show-more affordance on the card', function () {
    $component = new McComponent;
    $component->withLimit = true;

    Livewire::test(McComponent::class, ['withLimit' => true])
        ->call('toggleRowExpansion', '1')
        ->assertSee('table-card-subrows-more', escape: false)
        ->assertSee(__('wire-table::messages.show_more_count', ['count' => 1]));
});

it('collapses child actions into one dropdown', function () {
    Livewire::test(McComponent::class, ['withActions' => true])
        ->call('toggleRowExpansion', '1')
        // Both actions live under a single trigger; neither renders as a wide
        // labelled button beside the child name.
        ->assertSee('Edit item')
        ->assertSee('Remove item');

    $component = new McComponent;
    $component->withActions = true;
    $component->mountWithTable();

    $group = $component->getTable()->getMobileSubRowActionGroup();

    expect($group->getActions())->toHaveCount(2);
});

it('labels a collapsed toggle "Details" when the count would cost a query', function () {
    Livewire::test(McComponent::class)
        ->assertSee(__('wire-table::messages.details'))
        ->assertDontSee(trans_choice('wire-table::messages.sub_rows_count', 2, ['count' => 2]));
});

it('shows the child count on a collapsed toggle when the query already counted', function () {
    // withCount() puts items_count on the record, so the number is free.
    Livewire::test(McComponent::class, ['countChildren' => true])
        ->assertSee(trans_choice('wire-table::messages.sub_rows_count', 2, ['count' => 2]));
});

it('shows the child count on an expanded toggle without any extra query', function () {
    Livewire::test(McComponent::class)
        ->call('toggleRowExpansion', '1')
        ->assertSee(trans_choice('wire-table::messages.sub_rows_count', 2, ['count' => 2]));
});

it('flattens grouped child actions into one trigger and drops the dividers', function () {
    $component = new McComponent;
    $component->groupedSubRowActions = true;
    $component->mountWithTable();

    $labels = array_map(
        fn ($action) => $action->getLabel(),
        $component->getTable()->getMobileSubRowActionGroup()->getActions(),
    );

    // A nested group is merged, and the separators that only make sense inside
    // the original grouping are gone.
    expect($labels)->toBe(['Edit item', 'Remove item', 'Duplicate item']);
});
