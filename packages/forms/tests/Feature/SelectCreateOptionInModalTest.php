<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Concerns\WithActions;

/**
 * createOptionForm on a Select that lives inside a stacked action modal — the
 * option overlay must resolve the frame-scoped field, write the created value
 * back into that frame, and render one layer above the modal it opened from.
 */
class ModalOptionStore
{
    /** @var array<string, string> */
    public static array $records = ['c1' => 'Alan Turing'];

    public static int $sequence = 1;

    /** @return array<string, string> */
    public static function all(): array
    {
        return self::$records;
    }

    public static function create(string $name): string
    {
        self::$sequence++;
        $key = 'c'.self::$sequence;
        self::$records[$key] = $name;

        return $key;
    }

    public static function reset(): void
    {
        self::$records = ['c1' => 'Alan Turing'];
        self::$sequence = 1;
    }
}

class CreateOptionInModalHost extends Component
{
    use WithActions;

    protected function actions(): array
    {
        return [
            Action::make('editOrder')
                ->modalHeading('Edit order')
                ->form([
                    TextInput::make('reference'),
                    Select::make('customer')
                        ->options(fn () => ModalOptionStore::all())
                        ->getOptionLabelUsing(fn ($value) => ModalOptionStore::all()[$value] ?? null)
                        ->createOptionForm([TextInput::make('name')->required()])
                        ->createOptionUsing(fn (array $data) => ModalOptionStore::create((string) $data['name'])),
                ])
                ->action(fn () => null),
        ];
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div><x-wire-actions::modal-host :component="$this" /></div>
        BLADE;
    }
}

beforeEach(fn () => ModalOptionStore::reset());

test('createOptionForm resolves the frame-scoped select inside an action modal', function () {
    Livewire::test(CreateOptionInModalHost::class)
        ->call('mountAction', 'editOrder')
        // The select lives at the frame's depth-scoped state path.
        ->call('mountCreateOption', 'mountedActions.0.data.customer')
        ->assertSet('mountedCreateOptionSelect', 'mountedActions.0.data.customer');
});

test('creating an option writes the value back into the open action modal frame', function () {
    Livewire::test(CreateOptionInModalHost::class)
        ->call('mountAction', 'editOrder')
        ->set('mountedActions.0.data.reference', 'ORD-1042')
        ->call('mountCreateOption', 'mountedActions.0.data.customer')
        ->set('createOptionFormData.name', 'Grace Hopper')
        ->call('createSelectOption')
        ->assertHasNoErrors()
        // The created option is selected on the frame's field…
        ->assertSet('mountedActions.0.data.customer', 'c2')
        // …the rest of the frame's form data is untouched…
        ->assertSet('mountedActions.0.data.reference', 'ORD-1042')
        // …the option overlay closed, and the action modal is still open.
        ->assertSet('mountedCreateOptionSelect', null)
        ->assertSet('actionModalOpen', true)
        ->tap(fn ($c) => expect($c->get('mountedActions'))->toHaveCount(1));

    expect(ModalOptionStore::all()['c2'])->toBe('Grace Hopper');
});

test('the option overlay renders one layer above the action modal it opened from', function () {
    Livewire::test(CreateOptionInModalHost::class)
        ->call('mountAction', 'editOrder')          // frame at depth 0 → z-50
        ->call('mountCreateOption', 'mountedActions.0.data.customer')
        // The create overlay sits at zIndexForDepth(1) = 60, above the modal.
        ->assertSeeHtml('z-index: 60')
        ->assertSeeHtml('wire:click="createSelectOption"');
});
