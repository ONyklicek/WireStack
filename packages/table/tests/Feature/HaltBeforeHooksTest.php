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
 * What the confirmed pass does with the action's before() hooks.
 *
 * The default skips them, because a halt is normally raised *in* one: re-running
 * it would raise the same halt again and the confirmation would never get past
 * itself. `skipBeforeOnConfirm(false)` is the way out for a hook that guards its
 * own halt with `$confirmed` and still has work to do on the second pass — and
 * before 2.0 that setter was read by nothing at all.
 */

class HbhUser extends Model
{
    protected $table = 'hbh_users';

    protected $guarded = [];

    public $timestamps = false;
}

class HbhComponent extends Component
{
    use WithTable;

    /** @var array<int, string> Every hook invocation, in order. */
    public array $log = [];

    public bool $rerunBefore = false;

    public function table(Table $table): Table
    {
        return $table
            ->model(HbhUser::class)
            ->columns([TextColumn::make('name')])
            ->actions([
                Action::make('archive')
                    ->before(function (bool $confirmed, Action $action): void {
                        $this->log[] = $confirmed ? 'before:confirmed' : 'before:first';

                        if (! $confirmed) {
                            $action->halt()
                                ->heading('Archive it?')
                                ->skipBeforeOnConfirm(! $this->rerunBefore);
                        }
                    })
                    ->action(function (): void {
                        $this->log[] = 'action';
                    }),
            ]);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

beforeEach(function () {
    Schema::create('hbh_users', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });

    HbhUser::create(['id' => 1, 'name' => 'Ada']);
});

afterEach(function () {
    Schema::dropIfExists('hbh_users');
});

it('skips the before hooks on the confirmed pass by default', function () {
    Livewire::test(HbhComponent::class)
        ->call('executeTableActionWithData', '1', 'archive')
        ->assertSet('tableState.modal.halt.show', true)
        ->call('submitHaltModal')
        ->assertSet('log', ['before:first', 'action']);
});

it('runs them again when the halt asked for it with skipBeforeOnConfirm(false)', function () {
    // The hook guards its own halt with $confirmed, so the second pass reaches
    // the action rather than halting into the same modal for ever.
    Livewire::test(HbhComponent::class)
        ->set('rerunBefore', true)
        ->call('executeTableActionWithData', '1', 'archive')
        ->assertSet('tableState.modal.halt.show', true)
        ->call('submitHaltModal')
        ->assertSet('log', ['before:first', 'before:confirmed', 'action']);
});

it('leaves the flag off for the next action in the same request', function () {
    // The flag is per confirmation, not per component: a second, unconfirmed run
    // must go back to the default.
    Livewire::test(HbhComponent::class)
        ->set('rerunBefore', true)
        ->call('executeTableActionWithData', '1', 'archive')
        ->call('submitHaltModal')
        ->set('log', [])
        ->call('executeTableActionWithData', '1', 'archive')
        ->assertSet('log', ['before:first']);
});
