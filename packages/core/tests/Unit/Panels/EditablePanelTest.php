<?php

declare(strict_types=1);

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Panels\Components\CheckboxEntry;
use NyonCode\WireCore\Panels\Components\SelectEntry;
use NyonCode\WireCore\Panels\Components\TextInputEntry;
use NyonCode\WireCore\Panels\Components\ToggleEntry;
use NyonCode\WireCore\Panels\Panel;
use NyonCode\WireCore\Panels\PanelComponent;

// ─── Test model & components ─────────────────────────────────────

class PnlUser extends Model
{
    protected $table = 'pnl_users';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'bool',
        'is_verified' => 'bool',
    ];
    // Timestamped: updated_at drives optimistic-lock versioning.
}

class PnlPanelComponent extends PanelComponent
{
    public ?PnlUser $user = null;

    public function panel(): Panel
    {
        return Panel::make()
            ->record($this->user)
            ->columns(2)
            ->schema([
                ToggleEntry::make('is_active')->label('Active'),
                CheckboxEntry::make('is_verified'),
                SelectEntry::make('status')->options(['open' => 'Open', 'closed' => 'Closed']),
                TextInputEntry::make('name')->rules(['required', 'min:2']),
                ToggleEntry::make('locked')->disabled(fn (PnlUser $record) => true),
                TextEntry::make('reference'), // read-only, must never be writable
            ]);
    }
}

class PnlPermComponent extends PanelComponent
{
    public ?PnlUser $user = null;

    public function panel(): Panel
    {
        return Panel::make()
            ->record($this->user)
            ->schema([
                ToggleEntry::make('is_active')->permission('edit-users'),
            ]);
    }
}

class PnlCallbackComponent extends PanelComponent
{
    public ?PnlUser $user = null;

    public array $touched = [];

    public function panel(): Panel
    {
        return Panel::make()
            ->record($this->user)
            ->schema([
                TextInputEntry::make('name')
                    ->saveUsing(function (PnlUser $record, $value) {
                        $record->name = strtoupper((string) $value);
                        $record->save();
                    })
                    ->afterStateUpdated(function (PnlUser $record, $value) {
                        $this->touched[] = $record->getKey();
                    }),
            ]);
    }
}

enum PnlStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}

class PnlLayoutComponent extends PanelComponent
{
    public ?PnlUser $user = null;

    public function panel(): Panel
    {
        return Panel::make()
            ->record($this->user)
            ->schema([
                Section::make('Flags')->schema([
                    ToggleEntry::make('is_active'),
                ]),
            ]);
    }
}

class PnlThrowComponent extends PanelComponent
{
    public ?PnlUser $user = null;

    public function panel(): Panel
    {
        return Panel::make()
            ->record($this->user)
            ->schema([
                TextInputEntry::make('name')->saveUsing(function () {
                    throw new RuntimeException('boom');
                }),
            ]);
    }
}

function pnlComponent(int $id = 1): PnlPanelComponent
{
    $component = new PnlPanelComponent;
    $component->user = PnlUser::find($id);

    return $component;
}

beforeEach(function () {
    Schema::create('pnl_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('status')->default('open');
        $table->boolean('is_active')->default(false);
        $table->boolean('is_verified')->default(false);
        $table->string('reference')->default('REF');
        $table->timestamps();
    });

    PnlUser::create(['name' => 'Alice', 'status' => 'open', 'is_active' => false, 'is_verified' => false]);
    PnlUser::create(['name' => 'Bob', 'status' => 'closed', 'is_active' => true, 'is_verified' => true]);
});

afterEach(function () {
    Schema::dropIfExists('pnl_users');
});

// ─── Happy-path writes ───────────────────────────────────────────

it('writes a toggle entry directly to the record', function () {
    $result = pnlComponent()->updatePanelEntry('1', 'is_active', true);

    expect($result['success'])->toBeTrue()
        ->and($result['version'])->not->toBeNull()
        ->and(PnlUser::find(1)->is_active)->toBeTrue();
});

it('writes a checkbox entry directly to the record', function () {
    $result = pnlComponent()->updatePanelEntry('1', 'is_verified', true);

    expect($result['success'])->toBeTrue()
        ->and(PnlUser::find(1)->is_verified)->toBeTrue();
});

it('writes a select entry directly to the record', function () {
    $result = pnlComponent()->updatePanelEntry('1', 'status', 'closed');

    expect($result['success'])->toBeTrue()
        ->and(PnlUser::find(1)->status)->toBe('closed');
});

it('writes a text input entry directly to the record', function () {
    $result = pnlComponent()->updatePanelEntry('1', 'name', 'Alicia');

    expect($result['success'])->toBeTrue()
        ->and(PnlUser::find(1)->name)->toBe('Alicia');
});

// ─── Security: write whitelist ───────────────────────────────────

it('rejects a write to a read-only entry name', function () {
    $result = pnlComponent()->updatePanelEntry('1', 'reference', 'HACKED');

    expect($result['success'])->toBeFalse()
        ->and(PnlUser::find(1)->reference)->toBe('REF');
});

it('rejects a write to an attribute that is not in the schema', function () {
    $result = pnlComponent()->updatePanelEntry('1', 'status_secret', 'x');

    expect($result['success'])->toBeFalse();
});

// ─── Guards ──────────────────────────────────────────────────────

it('rejects a write when the entry is disabled for the record', function () {
    // Seed a truthy value directly so we can prove it is left untouched.
    PnlUser::where('id', 1)->update(['is_active' => false]);

    $result = pnlComponent()->updatePanelEntry('1', 'locked', true);

    expect($result['success'])->toBeFalse();
});

it('rejects a client key that does not match the bound record', function () {
    $result = pnlComponent(1)->updatePanelEntry('999', 'is_active', true);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe(__('wire-core::messages.record_not_found'));
});

it('rejects a write when the panel has no model record bound', function () {
    $component = new PnlPanelComponent; // user stays null

    $result = $component->updatePanelEntry('1', 'is_active', true);

    expect($result['success'])->toBeFalse();
});

it('fails validation before persisting', function () {
    $result = pnlComponent()->updatePanelEntry('1', 'name', 'A'); // min:2

    expect($result['success'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty()
        ->and(PnlUser::find(1)->name)->toBe('Alice');
});

it('denies a permissioned entry without an authorised user', function () {
    $component = new PnlPermComponent;
    $component->user = PnlUser::find(1);

    $result = $component->updatePanelEntry('1', 'is_active', true);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe(__('wire-core::messages.no_permission'));
});

// ─── Optimistic locking ──────────────────────────────────────────

it('reports a conflict when the record version is stale', function () {
    $result = pnlComponent()->updatePanelEntry('1', 'name', 'Zed', '1'); // stale version

    expect($result['success'])->toBeFalse()
        ->and($result['conflict'])->toBeTrue()
        ->and($result['currentVersion'])->not->toBeNull()
        ->and(PnlUser::find(1)->name)->toBe('Alice');
});

it('writes when the record version matches', function () {
    $version = (string) PnlUser::find(1)->updated_at->getTimestamp();

    $result = pnlComponent()->updatePanelEntry('1', 'name', 'Zed', $version);

    expect($result['success'])->toBeTrue()
        ->and(PnlUser::find(1)->name)->toBe('Zed');
});

// ─── Callbacks ───────────────────────────────────────────────────

it('uses a custom save callback and runs afterStateUpdated', function () {
    $component = new PnlCallbackComponent;
    $component->user = PnlUser::find(1);

    $result = $component->updatePanelEntry('1', 'name', 'alicia');

    expect($result['success'])->toBeTrue()
        ->and(PnlUser::find(1)->name)->toBe('ALICIA')
        ->and($component->touched)->toContain(1);
});

// ─── Rendering ───────────────────────────────────────────────────

it('renders an editable toggle entry through its view', function () {
    $html = Panel::make()
        ->record(PnlUser::find(1))
        ->schema([ToggleEntry::make('is_active')->label('Active')])
        ->toHtml();

    expect($html)
        ->toContain('data-testid="panel-editable-is_active"')
        ->toContain("commitMethod: 'updatePanelEntry'")
        ->toContain('role="switch"');
});

it('renders every editable entry type through the panel', function () {
    $html = Panel::make()
        ->record(PnlUser::find(1))
        ->columns(2)
        ->schema([
            ToggleEntry::make('is_active'),
            CheckboxEntry::make('is_verified'),
            SelectEntry::make('status')->options(['open' => 'Open', 'closed' => 'Closed']),
            TextInputEntry::make('name'),
        ])
        ->toHtml();

    expect($html)
        ->toContain('panel-editable-is_active')
        ->toContain('panel-editable-is_verified')
        ->toContain('panel-editable-status')
        ->toContain('panel-editable-name')
        ->toContain('wire-panel');
});

it('is not editable when bound to a plain array (no model key)', function () {
    // Array-bound: no Model, so the entry cannot be edited.
    $entry = ToggleEntry::make('is_active')->record(['is_active' => true]);

    expect($entry->isEditable())->toBeFalse()
        ->and($entry->getRecordKey())->toBeNull()
        ->and($entry->getRecordVersion())->toBe('0');
});

it('renders the base component view', function () {
    $component = new PnlPanelComponent;
    $component->user = PnlUser::find(1);

    expect($component->render())->toBeInstanceOf(View::class);
});

// ─── Entry unit surface ──────────────────────────────────────────

it('exposes editable metadata on entries', function () {
    $toggle = ToggleEntry::make('is_active')->onColor('success')->offColor('danger');
    $select = SelectEntry::make('status')->options(['a' => 'A']);
    $text = TextInputEntry::make('name')->type('email')->rules(['required']);

    expect($toggle->getEditableType())->toBe('toggle')
        ->and($toggle->formatForSave(1))->toBeTrue()
        ->and($toggle->getOnColorClass())->toBeString()
        ->and($toggle->getOffColorClass())->toBeString()
        ->and($select->getOptions())->toBe(['a' => 'A'])
        ->and($text->getInputType())->toBe('email')
        ->and($text->getEditableRules())->toBe(['required']);
});

it('resolves enum option classes and ignores non-enum strings', function () {
    $plain = SelectEntry::make('status')->options('not-an-enum');

    expect($plain->getOptions())->toBe([]);
});

it('reports the checkbox accent color class', function () {
    expect(CheckboxEntry::make('is_verified')->getAccentColorClass())->toBeString()
        ->and(CheckboxEntry::make('is_verified')->color('success')->getAccentColorClass())->toContain('text-');
});

it('resolves a backed-enum class into a select option map', function () {
    $entry = SelectEntry::make('status')->options(PnlStatus::class);

    expect($entry->getOptions())->toBe(['open' => 'Open', 'closed' => 'Closed']);
});

// ─── Layout recursion + Panel value object ───────────────────────

it('finds and writes an editable entry nested in a layout component', function () {
    $component = new PnlLayoutComponent;
    $component->user = PnlUser::find(1);

    $result = $component->updatePanelEntry('1', 'is_active', true);

    expect($result['success'])->toBeTrue()
        ->and(PnlUser::find(1)->is_active)->toBeTrue();
});

it('renders an editable entry nested in a layout component', function () {
    $html = Panel::make()
        ->record(PnlUser::find(1))
        ->schema([
            Section::make('Flags')->schema([ToggleEntry::make('is_active')]),
        ])
        ->toHtml();

    expect($html)->toContain('panel-editable-is_active');
});

it('exposes the panel column count and casts to string', function () {
    $panel = Panel::make()->record(PnlUser::find(1))->columns(3)->schema([ToggleEntry::make('is_active')]);

    expect($panel->getColumns())->toBe(3)
        ->and((string) $panel)->toContain('panel-editable-is_active');
});

// ─── Transaction edge cases ──────────────────────────────────────

it('reports record not found when the row disappears before the locked write', function () {
    $component = pnlComponent(1);

    // The bound (in-memory) model still reports key 1, but the row is gone by the
    // time the locked transaction re-queries it.
    PnlUser::where('id', 1)->delete();

    $result = $component->updatePanelEntry('1', 'is_active', true);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe(__('wire-core::messages.record_not_found'));
});

it('returns a save error when the write throws', function () {
    $component = new PnlThrowComponent;
    $component->user = PnlUser::find(1);

    $result = $component->updatePanelEntry('1', 'name', 'x');

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('boom');
});
