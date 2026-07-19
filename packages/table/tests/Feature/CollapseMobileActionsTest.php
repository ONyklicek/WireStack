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
use NyonCode\WireTable\Table;

/*
 * collapseActionsOnMobile() folds the several inline row-action buttons of the
 * mobile stacked-card view into a single ActionGroup dropdown trigger. Rendered
 * end-to-end because the whole feature lives in the index view + ActionGroup.
 */

class CollapseActionsUser extends Model
{
    protected $table = 'collapse_actions_users';

    protected $guarded = [];
}

class CollapseActionsHost extends Component
{
    public bool $collapse = false;

    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(CollapseActionsUser::class)
            ->columns([TextColumn::make('name')])
            // Three actions so the default collapse threshold (3) is reached.
            ->actions([
                Action::make('edit')->label('Edit'),
                Action::make('archive')->label('Archive'),
                Action::make('delete')->label('Delete'),
            ])
            ->stackedOnMobile()
            ->collapseActionsOnMobile($this->collapse)
            ->paginated(false);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('collapse_actions_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    CollapseActionsUser::create(['name' => 'Ada Lovelace']);
});

afterEach(fn () => Schema::dropIfExists('collapse_actions_users'));

it('renders each row action inline in the mobile card by default', function () {
    Livewire::test(CollapseActionsHost::class)
        ->assertSee('table-card', escape: false)
        // Both action triggers render as their own buttons; no group dropdown.
        ->assertSee('action-edit', escape: false)
        ->assertSee('action-archive', escape: false)
        ->assertDontSee('action-group-trigger', escape: false);
});

it('collapses the mobile card actions into one dropdown group', function () {
    Livewire::test(CollapseActionsHost::class)
        ->set('collapse', true)
        ->assertSee('table-card', escape: false)
        // The inline buttons are folded into a single floating dropdown trigger.
        ->assertSee('action-group-trigger', escape: false);
});
