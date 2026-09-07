<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;
use NyonCode\WireForms\WireFormsServiceProvider;

/**
 * The chrome around a repeater row: duplicating it, moving it without a mouse,
 * dragging it, and which rows are open when the form first renders.
 *
 * The drag half is asserted as *wiring* here — the attributes and the bundle the
 * controller needs — because Pest sees markup, not what a browser does with it.
 * The drag itself is `workbench/scripts/verify-repeater-reorder.mjs`, and that
 * split is the whole reason this feature shipped broken for so long: the handle
 * existed, so every markup assertion passed.
 */
class RepeaterControlsComponent extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = [
        'contacts' => [
            ['id' => 7, 'name' => 'Ada'],
            ['id' => 8, 'name' => 'Grace'],
            ['id' => 9, 'name' => 'Katherine'],
        ],
    ];

    public bool $asTable = false;

    public bool $reorderable = true;

    public bool $cloneable = true;

    public string $expansion = 'all';

    public function form(Form $form): Form
    {
        $repeater = Repeater::make('contacts')
            ->table($this->asTable)
            ->reorderable($this->reorderable)
            ->cloneable($this->cloneable)
            ->collapsible()
            ->itemLabel(fn (array $state) => $state['name'] ?? null)
            ->schema([TextInput::make('name')]);

        match ($this->expansion) {
            'first' => $repeater->expandFirst(),
            'last' => $repeater->expandLast(),
            'none' => $repeater->collapsed(),
            default => $repeater->expandAll(),
        };

        return $form->statePath('data')->schema([$repeater]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

// ─── Duplicating a row ──────────────────────────────────────────────

it('inserts a duplicate directly below its original', function () {
    Livewire::test(RepeaterControlsComponent::class)
        ->call('cloneRepeaterItem', 'data.contacts', 0, 'id')
        ->assertCount('data.contacts', 4)
        ->assertSet('data.contacts.0.name', 'Ada')
        ->assertSet('data.contacts.1.name', 'Ada')
        // Below, not appended: the copy belongs next to what it copies.
        ->assertSet('data.contacts.2.name', 'Grace');
});

it('strips the record key from the copy so it saves as a new child', function () {
    // Keeping the key would make both rows match the same record: the second
    // fill()->save() overwrites the first and one of the two disappears.
    Livewire::test(RepeaterControlsComponent::class)
        ->call('cloneRepeaterItem', 'data.contacts', 0, 'id')
        ->assertSet('data.contacts.0.id', 7)
        ->assertSet('data.contacts.1.id', null);
});

it('ignores a clone of a row that is not there', function () {
    Livewire::test(RepeaterControlsComponent::class)
        ->call('cloneRepeaterItem', 'data.contacts', 9, 'id')
        ->assertCount('data.contacts', 3);
});

it('renders a duplicate button per row only when cloneable', function () {
    expect(Livewire::test(RepeaterControlsComponent::class)->html())
        ->toContain('form-repeater-data.contacts-clone-0')
        ->and(Livewire::test(RepeaterControlsComponent::class, ['cloneable' => false])->html())
        ->not->toContain('form-repeater-data.contacts-clone-0');
});

it('does not offer duplication on a repeater that cannot be added to', function () {
    // Duplicating adds a row, so it answers to the same switches adding does —
    // otherwise maxItems() has a back door.
    $repeater = Repeater::make('items')->cloneable()->maxItems(2);

    expect($repeater->isCloneable())->toBeTrue()
        ->and(Repeater::make('items')->cloneable()->addable(false)->isCloneable())->toBeFalse()
        ->and(Repeater::make('items')->cloneable()->disabled()->isCloneable())->toBeFalse();
});

// ─── Moving a row from the keyboard ─────────────────────────────────

it('moves a row up and down through moveRepeaterItem', function () {
    Livewire::test(RepeaterControlsComponent::class)
        ->call('moveRepeaterItem', 'data.contacts', 2, 1)
        ->assertSet('data.contacts.1.name', 'Katherine')
        ->assertSet('data.contacts.2.name', 'Grace')
        ->call('moveRepeaterItem', 'data.contacts', 0, 2)
        ->assertSet('data.contacts.2.name', 'Ada');
});

it('clamps a move past either end instead of erroring', function () {
    // The buttons are disabled at the ends, but a re-render racing a click can
    // still send -1; a clamped no-op beats a broken form.
    Livewire::test(RepeaterControlsComponent::class)
        ->call('moveRepeaterItem', 'data.contacts', 0, -1)
        ->assertSet('data.contacts.0.name', 'Ada')
        ->call('moveRepeaterItem', 'data.contacts', 0, 99)
        ->assertSet('data.contacts.2.name', 'Ada');
});

it('ignores a move of a row that is not there', function () {
    // The buttons never ask for one, but the endpoint is public and a stale
    // re-render can: doing nothing beats throwing inside a form.
    Livewire::test(RepeaterControlsComponent::class)
        ->call('moveRepeaterItem', 'data.contacts', 9, 0)
        ->assertCount('data.contacts', 3)
        ->assertSet('data.contacts.0.name', 'Ada')
        ->call('moveRepeaterItem', 'data.nothing-here', 0, 1)
        ->assertCount('data.contacts', 3);
});

it('renders move buttons alongside the drag handle, disabled at the ends', function () {
    $html = Livewire::test(RepeaterControlsComponent::class)->html();

    expect($html)->toContain('form-repeater-data.contacts-move-up-0')
        ->and($html)->toContain('form-repeater-data.contacts-move-down-2')
        ->and($html)->toContain('form-repeater-data.contacts-reorder-0');
});

// ─── Dragging ───────────────────────────────────────────────────────

it('wires the drag controller a reorderable repeater needs', function () {
    $html = Livewire::test(RepeaterControlsComponent::class)->html();

    expect($html)
        // The controller, its container attribute and its index attribute — the
        // three halves that have to agree, and the ones nothing registered before.
        ->toContain('wireSortableList(')
        ->toContain('data-sortable-item="0"')
        ->toContain('data-sortable-handle')
        ->toContain('reorderRepeaterItems');
});

it('includes the partial that defines the factory its markup names', function () {
    // The invariant that was missing. `@assets` hands its body to Livewire rather
    // than to this component's markup, so no amount of reading the rendered HTML
    // can tell you whether `wireSortableList` is defined anywhere — which is why
    // three views could name a controller nothing registered and every markup
    // assertion still pass. What the partial itself emits is asserted by
    // wire-core's SortableListAssetTest; this is the other half of the pair.
    foreach (['repeater', 'repeater-table', 'builder'] as $view) {
        $source = (string) file_get_contents(
            dirname(WireFormsServiceProvider::ASSETS_PATH)."/resources/views/components/{$view}.blade.php"
        );

        expect($source)->toContain('wireSortableList(')
            ->and($source)->toContain('wire-core::partials.sortable-list-assets');
    }
});

it('ships no drag controller to a repeater that cannot be reordered', function () {
    $html = Livewire::test(RepeaterControlsComponent::class, ['reorderable' => false])->html();

    expect($html)->not->toContain('wireSortableList(')
        ->and($html)->not->toContain('data-sortable-item');
});

it('wires the table layout to drag its tbody rows', function () {
    $html = Livewire::test(RepeaterControlsComponent::class, ['asTable' => true])->html();

    // The rows are not the wrapper's own children there, so the controller is
    // told where to look.
    expect($html)->toContain("wireSortableList({ container: 'tbody' })")
        ->and($html)->toContain('data-sortable-item="0"');
});

// ─── Which rows start open ──────────────────────────────────────────

it('passes each row its own collapsed default rather than baking a count into x-data', function () {
    // The morph hazard: anything count-dependent in the root x-data changes the
    // attribute text as rows come and go, and Alpine re-initialises the whole
    // component. The per-row default lives inside the loop, where changing text
    // is what a re-render is for.
    $html = Livewire::test(RepeaterControlsComponent::class, ['expansion' => 'last'])->html();

    expect($html)->toContain('isCollapsed(0, true)')
        ->and($html)->toContain('isCollapsed(2, false)')
        ->and($html)->not->toContain('itemCount');
});

it('opens only the first row under expandFirst()', function () {
    $html = Livewire::test(RepeaterControlsComponent::class, ['expansion' => 'first'])->html();

    expect($html)->toContain('isCollapsed(0, false)')
        ->and($html)->toContain('isCollapsed(1, true)');
});

it('seeds the collapse-all toggle from the field policy', function () {
    expect(Livewire::test(RepeaterControlsComponent::class, ['expansion' => 'none'])->html())
        ->toContain('allCollapsed: true')
        ->and(Livewire::test(RepeaterControlsComponent::class)->html())
        ->toContain('allCollapsed: false');
});

it('offers the collapse-all toggle only from two rows up', function () {
    expect(Livewire::test(RepeaterControlsComponent::class)->html())
        ->toContain('form-repeater-data.contacts-toggle-all')
        ->and(Livewire::test(RepeaterControlsComponent::class, [
            'data' => ['contacts' => [['id' => 7, 'name' => 'Ada']]],
        ])->html())->not->toContain('form-repeater-data.contacts-toggle-all');
});

// ─── Names and emptiness ────────────────────────────────────────────

it('gives the table layout a column for the item name', function () {
    $html = Livewire::test(RepeaterControlsComponent::class, ['asTable' => true])->html();

    expect($html)->toContain('Ada')
        ->and($html)->toContain('Katherine');
});

it('heads no name column when no itemLabel was configured', function () {
    expect(Repeater::make('items')->hasItemLabel())->toBeFalse()
        ->and(Repeater::make('items')->itemLabel('Row')->hasItemLabel())->toBeTrue()
        // Configured, but resolving to nothing for this row — the heading must
        // not come and go with the data.
        ->and(Repeater::make('items')->itemLabel(fn () => null)->hasItemLabel())->toBeTrue();
});

it('says the card layout is empty instead of showing bare space', function () {
    $html = Livewire::test(RepeaterControlsComponent::class, ['data' => ['contacts' => []]])->html();

    expect($html)->toContain('form-repeater-data.contacts-empty')
        ->and($html)->toContain('No items yet');
});

it('lets the empty message be named', function () {
    expect(Repeater::make('items')->getEmptyLabel())->toBe('No items yet')
        ->and(Repeater::make('items')->emptyLabel('No contacts yet')->getEmptyLabel())->toBe('No contacts yet');
});
