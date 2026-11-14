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
 * A halt raised from a row button reaches the page.
 *
 * The row buttons sit in the `data-region` island, and Livewire 4 targets the
 * nearest island on its own: a click there skips the component render and
 * answers with the island alone. The halt modal is drawn outside every island,
 * so a row action with no modal of its own that halted set the halt state and
 * sent nothing that draws it — the click looked dead. A Livewire::test() call
 * carries no island and renders everything, which is why every halt test above
 * passed while the browser showed nothing.
 */
class HfiRow extends Model
{
    protected $table = 'hfi_rows';

    protected $guarded = [];
}

class HfiHost extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(HfiRow::class)
            ->columns([TextColumn::make('name')])
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->action(function (bool $confirmed, callable $halt) {
                        if (! $confirmed) {
                            return $halt()->heading('Missing wage on this row');
                        }
                    }),
                Action::make('plain')->label('Plain')->action(fn () => null),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    Schema::create('hfi_rows', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->timestamps();
    });

    HfiRow::create(['id' => 1, 'name' => 'First']);
});

afterEach(fn () => Schema::dropIfExists('hfi_rows'));

/**
 * The request a click on a row button sends: one call, targeting the island
 * the button sits in.
 *
 * @return array<string, mixed>
 */
function hfiRowClick(array $snapshot, string $action): array
{
    $response = test()->withHeader('X-Livewire', 'true')->postJson(
        app('livewire')->getUpdateUri(),
        ['components' => [[
            'snapshot' => json_encode($snapshot),
            'updates' => [],
            'calls' => [[
                'method' => 'executeTableAction',
                'params' => ['1', $action],
                'path' => '',
                'id' => (string) random_int(1, 99999),
                'metadata' => ['island' => ['name' => 'data-region', 'mode' => 'morph']],
            ]],
        ]]],
    );

    $response->assertOk();

    return $response->json('components.0.effects');
}

it('renders the host when a row action halts from inside the data region', function () {
    $snapshot = Livewire::test(HfiHost::class)->snapshot;

    $effects = hfiRowClick($snapshot, 'approve');

    expect($effects['html'] ?? null)->toBeString()
        ->toContain('Missing wage on this row');
});

it('still answers a row action that does not halt with the island alone', function () {
    $snapshot = Livewire::test(HfiHost::class)->snapshot;

    $effects = hfiRowClick($snapshot, 'plain');

    expect($effects['html'] ?? null)->toBeNull()
        ->and(implode('', $effects['islandFragments'] ?? []))->toContain('First');
});
