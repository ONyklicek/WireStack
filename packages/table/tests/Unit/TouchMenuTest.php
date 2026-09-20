<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Support\RecordAction;
use NyonCode\WireTable\Support\TableGestures;
use NyonCode\WireTable\Table;

/*
 * The row's actions on a tablet.
 *
 * A tablet is wide enough for the desktop table, so it got the desktop's
 * gestures and none of the phone card's buttons — and a finger has no right
 * click and no Delete key. A table whose record actions were all gestures had
 * no way to reach them on an iPad at all. The row's menu now carries them for a
 * finger: a long press opens it (the right click's stand-in) and a "⋯" in the
 * actions column opens it visibly. On a mouse the extra items are never shown.
 */

class TouchMenuRow extends Model
{
    protected $table = 'touch_menu_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class TouchMenuComponent extends Component
{
    use WithTable;

    public string $mode = 'default';

    public function mount(string $mode = 'default'): void
    {
        $this->mode = $mode;
    }

    public function table(Table $table): Table
    {
        $table
            ->model(TouchMenuRow::class)
            ->paginated(false)
            ->columns([TextColumn::make('name')])
            ->actions($this->mode === 'no-column' ? [] : [Action::make('edit')->label('Edit')->action(fn () => null)])
            ->recordActions([
                RecordAction::make(Action::make('open')->label('Open')->action(fn () => null))->onDoubleClick(),
                RecordAction::make(Action::make('archive')->label('Archive')->action(fn () => null))->onKey('Delete'),
                RecordAction::make(Action::make('copy')->label('Copy')->action(fn () => null))->onContextMenu(),
                // Referenced by name: already a button in the column.
                RecordAction::make('edit')->onClick(),
            ]);

        return match ($this->mode) {
            'off' => $table->gestures(false),
            'no-fallback' => $table->recordActionButtonsOnMobile(false),
            'plain' => $table->recordActions([]),
            default => $table,
        };
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

function touchHtml(string $mode = 'default'): string
{
    return str_replace('\\u0022', '"', Livewire::test(TouchMenuComponent::class, ['mode' => $mode])->html());
}

beforeEach(function () {
    Schema::create('touch_menu_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
    });
    TouchMenuRow::insert([['name' => 'Alpha'], ['name' => 'Beta']]);
});

afterEach(fn () => Schema::dropIfExists('touch_menu_rows'));

it('offers a finger every gesture-only action the menu and the column do not already hold', function () {
    $table = Table::make()
        ->actions([Action::make('edit')])
        ->recordActions([
            RecordAction::make(Action::make('open'))->onDoubleClick(),
            RecordAction::make(Action::make('archive'))->onKey('Delete'),
            RecordAction::make(Action::make('copy'))->onContextMenu(),
            RecordAction::make(Action::make('pin'))->onDoubleClick()->alsoInRowActions(),
            RecordAction::make('edit')->onDoubleClick(),
        ]);

    expect(array_map(fn (Action $a) => $a->getName(), $table->getTouchMenuActions()))->toBe(['open', 'archive'])
        ->and($table->hasTouchMenu())->toBeTrue()
        // A key is a window listener on a rendered item; a finger has no key.
        ->and($table->getTouchMenuActions()[1]->getKeyboardShortcut())->toBeNull();
});

it('lists the right-click actions too when there is no right-click menu to hold them', function () {
    $table = Table::make()
        ->gestures(fn (TableGestures $g) => $g->contextMenu(false))
        ->recordActions([RecordAction::make(Action::make('copy'))->onContextMenu()]);

    // contextMenu(false) is the switch a long press answers to as well.
    expect($table->hasTouchMenu())->toBeFalse();

    $table = Table::make()->recordActions([RecordAction::make(Action::make('copy'))->onContextMenu()]);

    expect($table->hasRowContextMenu())->toBeTrue()
        ->and($table->hasTouchMenu())->toBeFalse();
});

it('leaves a finger what the switches leave a mouse', function () {
    $actions = [RecordAction::make(Action::make('open'))->onDoubleClick()];

    expect(Table::make()->recordActions($actions)->gestures(false)->hasTouchMenu())->toBeFalse()
        ->and(Table::make()->recordActions($actions)->recordActionButtonsOnMobile(false)->hasTouchMenu())->toBeFalse();
});

it('mounts the row controller for a table whose only way in is a key', function () {
    // An onKey() action with the keyboard layer off had nothing to answer it;
    // the controller now mounts for the long press that reaches it.
    $table = Table::make()->recordActions([RecordAction::make(Action::make('archive'))->onKey('Delete')]);

    expect($table->hasRecordActionPointer())->toBeFalse()
        ->and($table->mountsRecordActionController())->toBeTrue();
});

it('renders the touch items into the row menu, the ⋯ into the column, and tells the controller', function () {
    $html = touchHtml();

    expect($html)->toContain('<div data-touch-menu')
        ->toContain('touch: true')
        ->toContain('data-testid="row-touch-menu"')
        ->toContain('[@media(pointer:coarse)]:inline-flex')
        ->toContain('[&amp;&gt;tr]:touch-manipulation')
        ->toContain('@contextmenu="onContextMenu($event)"');

    // The touch block is a sibling after the right-click items, hidden by default.
    preg_match('/<div data-touch-menu[^>]*style="display: none;">(.*?)<\/div><\/div><\/template>/s', $html, $block);

    expect($block[1] ?? '')->toContain('Open')->toContain('Archive')
        ->not->toContain('>Copy<')
        ->not->toContain('>Edit<');
});

it('puts no ⋯ on a table without an actions column, and none at all on a row with no menu', function () {
    expect(touchHtml('no-column'))->toContain('<div data-touch-menu')->not->toContain('data-testid="row-touch-menu"');

    // The right-click menu is a mouse gesture and nothing else, so a finger
    // still needs the ⋯ and the long press — even with no touch items to add.
    expect(touchHtml('no-fallback'))->not->toContain('<div data-touch-menu')
        ->toContain('data-testid="row-touch-menu"')
        ->toContain('touch-manipulation');

    foreach (['off', 'plain'] as $mode) {
        expect(touchHtml($mode))->not->toContain('<div data-touch-menu')
            ->not->toContain('data-testid="row-touch-menu"')
            ->not->toContain('touch-manipulation');
    }
});
