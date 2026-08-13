<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireCore\Actions\ModalStep;
use NyonCode\WireCore\Actions\ViewAction;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/**
 * Which buttons a table action modal's footer renders, per modal shape.
 *
 * The table has three footer partials — `action-modal-modal-footer` and
 * `action-modal-slideover-footer`, both delegating to `wizard-footer` for a
 * multi-step action — and they carry the same Back/Next/Submit decisions as
 * wire-core's `modal-host-footer`, which shipped that logic broken in 1.17.2
 * with a green suite. Only the wizard's Next/Back had any render coverage
 * (WithTableWizardTest), and nothing asserted a button's *absence*, which is
 * the half a mis-nested conditional breaks.
 *
 * Note the testids differ from wire-core's footer: the table's wizard uses
 * `wizard-back` / `wizard-next` where core uses `modal-back` / `modal-next`.
 */
class AmfUser extends Model
{
    protected $table = 'amf_users';

    protected $guarded = [];

    public $timestamps = false;
}

class AmfComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(AmfUser::class)
            ->paginated(false)
            ->columns([TextColumn::make('name')])
            ->headerActions([
                // Each action exists twice: once in the default dialog shell and
                // once as a slide-over, so one dataset drives both footers.
                $this->inviteAction('invite'),
                $this->inviteAction('inviteSlide')->slideOver(),

                // Three steps so the middle one — Back *and* Next, no Submit —
                // is a state the matrix can actually reach.
                $this->onboardAction('onboard'),
                $this->onboardAction('onboardSlide')->slideOver(),
            ])
            ->actions([
                ViewAction::make('inspect')
                    ->modalHeading('Inspect')
                    ->infolist([TextEntry::make('name')]),
                ViewAction::make('inspectSlide')
                    ->modalHeading('Inspect')
                    ->slideOver()
                    ->infolist([TextEntry::make('name')]),
            ]);
    }

    private function inviteAction(string $name): HeaderAction
    {
        return HeaderAction::make($name)
            ->modalHeading('Invite')
            ->form([TextInput::make('name')])
            ->action(fn () => null);
    }

    private function onboardAction(string $name): HeaderAction
    {
        return HeaderAction::make($name)
            ->modalHeading('Onboard')
            ->steps([
                ModalStep::make('One')->schema([TextInput::make('first')]),
                ModalStep::make('Two')->schema([TextInput::make('second')]),
                ModalStep::make('Three')->schema([TextInput::make('third')]),
            ])
            ->action(fn () => null);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    Schema::create('amf_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
});

afterEach(function () {
    Schema::dropIfExists('amf_users');
});

/**
 * Assert exactly which of the footer's four buttons are present.
 *
 * @param  list<string>  $expected  testids that must render; every other footer
 *                                  button must be absent
 */
function assertTableFooterButtons(mixed $test, array $expected): void
{
    foreach (['modal-cancel', 'modal-submit', 'wizard-back', 'wizard-next'] as $testId) {
        $needle = 'data-testid="'.$testId.'"';

        in_array($testId, $expected, true)
            ? $test->assertSeeHtml($needle)
            : $test->assertDontSeeHtml($needle);
    }
}

// The suffix that turns each action into its slide-over twin, so every case
// below runs against both footer partials.
dataset('modal shells', [
    'modal' => [''],
    'slide-over' => ['Slide'],
]);

// ─── Single-step form ────────────────────────────────────────────

it('renders cancel and submit for a single-step form action', function (string $shell) {
    // No wizard, so Submit is the only way to commit — losing it makes the
    // modal a dead end the user can only cancel out of.
    $test = Livewire::test(AmfComponent::class)
        ->call('openHeaderActionModal', 'invite'.$shell);

    assertTableFooterButtons($test, ['modal-cancel', 'modal-submit']);
})->with('modal shells');

it('wires the single-step buttons to the table modal methods', function (string $shell) {
    Livewire::test(AmfComponent::class)
        ->call('openHeaderActionModal', 'invite'.$shell)
        ->assertSeeHtml('wire:click="closeActionModal"')
        ->assertSeeHtml('wire:click="submitActionModal"');
})->with('modal shells');

// ─── Wizard ──────────────────────────────────────────────────────

it('renders cancel and next on the first wizard step', function (string $shell) {
    $test = Livewire::test(AmfComponent::class)
        ->call('openHeaderActionModal', 'onboard'.$shell);

    assertTableFooterButtons($test, ['modal-cancel', 'wizard-next']);
})->with('modal shells');

it('renders back and next on a middle wizard step', function (string $shell) {
    $test = Livewire::test(AmfComponent::class)
        ->call('openHeaderActionModal', 'onboard'.$shell)
        ->call('nextActionModalStep');

    expect($test->instance()->tableState->get('modal.actions.0.currentStep'))->toBe(1);

    assertTableFooterButtons($test, ['modal-cancel', 'wizard-back', 'wizard-next']);
})->with('modal shells');

it('renders back and submit on the last wizard step, never next', function (string $shell) {
    $test = Livewire::test(AmfComponent::class)
        ->call('openHeaderActionModal', 'onboard'.$shell)
        ->call('nextActionModalStep')
        ->call('nextActionModalStep');

    expect($test->instance()->tableState->get('modal.actions.0.currentStep'))->toBe(2);

    assertTableFooterButtons($test, ['modal-cancel', 'wizard-back', 'modal-submit']);
})->with('modal shells');

// ─── Infolist (read-only) ────────────────────────────────────────

it('renders cancel only for an infolist action, with nothing to submit', function (string $shell) {
    $user = AmfUser::create(['name' => 'Jane']);

    $test = Livewire::test(AmfComponent::class)
        ->call('openActionModal', (string) $user->id, 'inspect'.$shell);

    assertTableFooterButtons($test, ['modal-cancel']);
})->with('modal shells');
