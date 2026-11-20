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
 * A modal opened by a row button with no modal of its own reaches the page.
 *
 * The row buttons sit in the `data-region` island, and Livewire 4 targets the
 * nearest island on its own: a click there answers with that island alone. A
 * button whose action has a modal says `wire:island="action-modals"`; one whose
 * callback opens a modal (`openActionModal()` to the editor for the record's
 * kind) does not, so the frame was mounted and nothing in the response drew it —
 * the click looked dead and the modal appeared with the next request. On a
 * stacked mobile card every behaviour-only record action is such a button.
 */
class MfiRow extends Model
{
    protected $table = 'mfi_rows';

    protected $guarded = [];
}

class MfiHost extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(MfiRow::class)
            ->columns([TextColumn::make('name')])
            ->actions([
                Action::make('open')
                    ->label('Open')
                    ->registerActions([
                        Action::make('edit')->modalHeading('Edit this row')->action(fn () => null),
                    ])
                    ->action(fn (MfiRow $record) => $this->openActionModal((string) $record->getKey(), 'edit')),
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

    Schema::create('mfi_rows', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->timestamps();
    });

    MfiRow::create(['id' => 1, 'name' => 'First']);
});

afterEach(fn () => Schema::dropIfExists('mfi_rows'));

/**
 * One call as the browser sends it from a button inside `$island`.
 *
 * @param  list<string>  $params
 * @return array<string, mixed>
 */
function mfiClick(array $snapshot, string $method, array $params, string $island): array
{
    $response = test()->withHeader('X-Livewire', 'true')->postJson(
        app('livewire')->getUpdateUri(),
        ['components' => [[
            'snapshot' => json_encode($snapshot),
            'updates' => [],
            'calls' => [[
                'method' => $method,
                'params' => $params,
                'path' => '',
                'id' => (string) random_int(1, 99999),
                'metadata' => ['island' => ['name' => $island, 'mode' => 'morph']],
            ]],
        ]]],
    );

    $response->assertOk();

    return $response->json('components.0.effects');
}

it('answers a row action that opens a modal with the rows and the modal', function () {
    $effects = mfiClick(Livewire::test(MfiHost::class)->snapshot, 'executeTableAction', ['1', 'open'], 'data-region');
    $fragments = implode('', $effects['islandFragments'] ?? []);

    expect($effects['html'] ?? null)->toBeNull()
        ->and($effects['islandFragments'])->toHaveCount(2)
        ->and($fragments)->toContain('First')
        ->and($fragments)->toContain('Edit this row');
});

it('leaves the modal island alone when a row action opens nothing', function () {
    $effects = mfiClick(Livewire::test(MfiHost::class)->snapshot, 'executeTableAction', ['1', 'plain'], 'data-region');

    expect($effects['html'] ?? null)->toBeNull()
        ->and($effects['islandFragments'])->toHaveCount(1)
        ->and(implode('', $effects['islandFragments']))->toContain('First')
        ->not->toContain('Edit this row');
});

it('renders the modal island once when the call already targeted it', function () {
    $effects = mfiClick(Livewire::test(MfiHost::class)->snapshot, 'openActionModal', ['1', 'edit'], 'action-modals');

    expect($effects['islandFragments'])->toHaveCount(1)
        ->and(implode('', $effects['islandFragments']))->toContain('Edit this row')
        ->not->toContain('First');
});
