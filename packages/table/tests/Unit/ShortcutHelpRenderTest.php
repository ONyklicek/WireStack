<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Support\TableGestures;
use NyonCode\WireTable\Table;

/**
 * The `?` keyboard-shortcut help (selection gestures rollout, step 25): a modal
 * shell opened by a window event the grid controller dispatches, with the
 * legend from Table::shortcutLegend() as its body. No Livewire roundtrip.
 */
class ShortcutHelpRow extends Model
{
    protected $table = 'shortcut_help_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class ShortcutHelpComponent extends Component
{
    use WithTable;

    public string $mode = 'selectable';

    public function mount(string $mode = 'selectable'): void
    {
        $this->mode = $mode;
    }

    public function table(Table $table): Table
    {
        $table
            ->model(ShortcutHelpRow::class)
            ->paginated(false)
            ->columns([TextColumn::make('name')]);

        match ($this->mode) {
            'selectable' => $table->selectable(),
            'opted-out' => $table->selectable()->gestures(fn (TableGestures $g) => $g->keyboard(false)),
            'with-form-action' => $table->selectable()->actions([
                Action::make('review')
                    ->label('Review')
                    ->form([TextInput::make('note')])
                    ->action(fn () => null),
            ]),
            default => $table,
        };

        return $table;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('shortcut_help_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
    });
    ShortcutHelpRow::insert([['name' => 'Alpha'], ['name' => 'Beta']]);
});

afterEach(fn () => Schema::dropIfExists('shortcut_help_rows'));

it('renders the help modal opened by a window event, with no wire:model binding', function () {
    $component = Livewire::test(ShortcutHelpComponent::class);
    $html = $component->html();
    $event = 'wire-table-shortcut-help-'.substr(md5($component->id()), 0, 12);

    // The event name is derived from the component id: a page with several
    // tables must open only the help of the table whose row has focus. It must
    // stay lowercase — the listener is an attribute NAME, which the DOM
    // lowercases, so a mixed-case id would never match the dispatch.
    expect($event)->toBe(strtolower($event))
        ->and($html)->toContain('x-on:'.$event.'.window="show = true"')
        // The controller learns the same name through its keyboard config.
        ->toContain($event)
        ->toContain('data-testid="shortcut-help"');
});

it('renders the legend sections and keys in the modal body', function () {
    $html = Livewire::test(ShortcutHelpComponent::class)->html();

    expect($html)->toContain('Navigation')
        ->toContain('Selection')
        ->toContain('Move the active row')
        ->toContain('Select the whole page')
        ->toContain('Show keyboard shortcuts')
        // Keys render their non-Mac label server-side and swap on a Mac.
        ->toContain('Ctrl+A')
        ->toContain('mac ?');
});

it('renders no help at all for a table without grid semantics', function () {
    $html = Livewire::test(ShortcutHelpComponent::class, ['mode' => 'plain'])->html();

    expect($html)->not->toContain('data-testid="shortcut-help"')
        ->not->toContain('wire-table-shortcut-help-');
});

it('drops the help together with the keyboard when the table opts out', function () {
    $html = Livewire::test(ShortcutHelpComponent::class, ['mode' => 'opted-out'])->html();

    expect($html)->not->toContain('data-testid="shortcut-help"')
        ->not->toContain('wire-table-shortcut-help-');
});

it('does not collide with an open form action modal — every teleport key stays unique', function () {
    // Both the help and a form action render the same Modal shell inside one
    // Livewire component. Livewire morphs by wire:key, so two templates sharing
    // one key can have their contents swapped into each other.
    $html = Livewire::test(ShortcutHelpComponent::class, ['mode' => 'with-form-action'])
        ->call('openActionModal', '1', 'review')
        ->html();

    expect(substr_count($html, 'data-testid="shortcut-help"'))->toBe(1)
        ->and($html)->toContain('Review');

    preg_match_all('/wire:key="(wire-modal-[^"]*)"/', $html, $matches);

    expect($matches[1])->toBe(array_unique($matches[1]));
});

it('hands the help event to the keyboard controller config', function () {
    $component = Livewire::test(ShortcutHelpComponent::class);
    $html = $component->html();

    // The `?` case in the controller reads kb.help; without it the key is inert.
    // The config rides in the wireRecordActions x-data, so assert the pairing
    // rather than one escaping of it.
    $keyboard = str($html)->after('keyboard: ')->before(', active:')->toString();

    expect($keyboard)->toContain('help')
        ->toContain('wire-table-shortcut-help-'.substr(md5($component->id()), 0, 12));
});
