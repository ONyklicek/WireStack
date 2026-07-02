<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/**
 * slideOverOnMobile() must reach the rendered action modal (regression: the
 * partial computed the flag but never passed it to the modal component).
 */
class AmvUser extends Model
{
    protected $table = 'amv_users';

    protected $guarded = [];

    public $timestamps = false;
}

class AmvComponent extends Component
{
    use WithTable;

    public string $mode = 'slide-over';

    public function mount(string $mode = 'slide-over'): void
    {
        $this->mode = $mode;
    }

    public function table(Table $table): Table
    {
        $action = HeaderAction::make('invite')
            ->form([TextInput::make('name')]);

        $action = match ($this->mode) {
            'slide-over' => $action->slideOverOnMobile(),
            'full-screen' => $action->fullScreenOnMobile(),
            default => $action,
        };

        return $table
            ->model(AmvUser::class)
            ->paginated(false)
            ->columns([TextColumn::make('name')])
            ->selectable()
            ->headerActions([$action]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    Schema::create('amv_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
});

afterEach(function () {
    Schema::dropIfExists('amv_users');
});

it('renders the action modal as a mobile slide-over when slideOverOnMobile() is set', function () {
    Livewire::test(AmvComponent::class, ['mode' => 'slide-over'])
        ->call('openHeaderActionModal', 'invite')
        ->assertSeeHtml('translate-x-full sm:translate-x-0')
        ->assertSeeHtml('justify-end pl-10');
});

it('renders the action modal full screen on mobile when fullScreenOnMobile() is set', function () {
    Livewire::test(AmvComponent::class, ['mode' => 'full-screen'])
        ->call('openHeaderActionModal', 'invite')
        ->assertSeeHtml('translate-y-full sm:translate-y-0')
        ->assertSeeHtml('items-stretch justify-center');
});

it('renders the default dialog without a mobile flag', function () {
    Livewire::test(AmvComponent::class, ['mode' => 'default'])
        ->call('openHeaderActionModal', 'invite')
        ->assertDontSeeHtml('translate-x-full');
});

it('renders a wrapping selection bar so bulk-action buttons stack on mobile (regression: fixed row overflowed)', function () {
    Livewire::test(AmvComponent::class)
        ->assertSeeHtml('flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between')
        ->assertSeeHtml('flex flex-wrap items-center gap-2');
});

it('left-aligns dropdown action-group items (regression: button UA style centered the label)', function () {
    $html = view('wire-table::tables.actions.dropdown-item', [
        'action' => Action::make('edit')->label('Edit'),
        'record' => AmvUser::create(['name' => 'A']),
    ])->render();

    expect($html)->toContain('text-left');
});
