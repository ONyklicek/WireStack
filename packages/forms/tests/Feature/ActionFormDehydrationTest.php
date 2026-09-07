<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ModalFooterAction;
use NyonCode\WireCore\Actions\ModalStep;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Concerns\WithActions;

/*
 * A form's state leaves through two doors, and both apply the same transforms.
 *
 * `Form::save()` dehydrated; an action modal did not — it handed the callback
 * the raw wire bag. So the very convention the package documents for itself
 * ("an unselected select stores null, not an empty string") held when a form was
 * saved and quietly lapsed when the same fields were shown in a modal: an enum
 * cast then threw on `''`, and a numeric column got an empty string. The two
 * doors ask the same StateDehydrator now.
 */

enum AfdStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Published = 'published';

    public function getLabel(): ?string
    {
        return ucfirst($this->value);
    }
}

class AfdArticle extends Model
{
    protected $table = 'afd_articles';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['status' => AfdStatus::class];
}

class AfdHost extends Component
{
    use WithActions;

    public ?AfdArticle $record = null;

    /** @var array<string, mixed> What the last action callback was handed. */
    public array $seen = [];

    public function mount(?int $id = null): void
    {
        $this->record = $id ? AfdArticle::query()->find($id) : null;
    }

    protected function actions(): array
    {
        return [
            // The plain case: a select and a number input, both emptied.
            Action::make('edit')
                ->form([
                    Select::make('status')->options(AfdStatus::class)->placeholder('None'),
                    TextInput::make('price')->numeric(),
                ])
                ->fillFormUsing(fn () => ['status' => $this->record?->status, 'price' => $this->record?->price])
                ->action(function (array $data): void {
                    $this->seen = $data;
                    $this->record?->update($data);
                }),

            // The owner's own transform, which the save path runs last and the
            // modal path did not run at all.
            Action::make('editWithCallback')
                ->form([
                    TextInput::make('code')->dehydrateStateUsing(fn (mixed $state): string => strtoupper((string) $state)),
                ])
                // Submits the form without closing, so the frame's bag can be
                // inspected after the callback has been handed its data.
                ->modalFooterActions([
                    ModalFooterAction::make('apply')
                        ->submitsForm()
                        ->action(fn (array $data) => $this->seen = $data),
                    ModalFooterAction::make('peek')
                        ->action(fn (array $data) => $this->seen = $data),
                ])
                ->action(fn (array $data) => $this->seen = $data),

            // A wizard shares one bag across steps while each step's Form knows
            // only its own schema — so every step has to be asked.
            Action::make('onboard')
                ->steps([
                    ModalStep::make('One')->schema([Select::make('status')->options(AfdStatus::class)->placeholder('None')]),
                    ModalStep::make('Two')->schema([TextInput::make('price')->numeric()]),
                ])
                ->action(fn (array $data) => $this->seen = $data),

            // A footer action that submits the form takes the same door.
            Action::make('editWithFooter')
                ->form([Select::make('status')->options(AfdStatus::class)->placeholder('None')])
                ->modalFooterActions([
                    ModalFooterAction::make('apply')
                        ->submitsForm()
                        ->action(fn (array $data) => $this->seen = $data),
                ]),
        ];
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                <x-wire-actions::modal-host :component="$this" />
            </div>
        BLADE;
    }
}

beforeEach(function () {
    Schema::dropIfExists('afd_articles');
    Schema::create('afd_articles', function (Blueprint $table): void {
        $table->id();
        $table->string('status')->nullable();
        $table->decimal('price', 10, 2)->nullable();
    });
});

it('hands the callback null for a cleared select, not an empty string', function () {
    $article = AfdArticle::query()->create(['status' => 'published', 'price' => 10.5]);

    Livewire::test(AfdHost::class, ['id' => $article->id])
        ->call('mountAction', 'edit')
        ->set('mountedActions.0.data.status', '')
        ->call('callMountedAction')
        ->assertSet('seen.status', null, strict: true);
});

it('hands the callback null for a cleared numeric input', function () {
    $article = AfdArticle::query()->create(['status' => 'published', 'price' => 10.5]);

    Livewire::test(AfdHost::class, ['id' => $article->id])
        ->call('mountAction', 'edit')
        ->set('mountedActions.0.data.price', '')
        ->call('callMountedAction')
        ->assertSet('seen.price', null, strict: true);
});

it('lets an enum-cast column be cleared from a modal without the cast throwing', function () {
    $article = AfdArticle::query()->create(['status' => 'published', 'price' => 10.5]);

    Livewire::test(AfdHost::class, ['id' => $article->id])
        ->call('mountAction', 'edit')
        ->set('mountedActions.0.data.status', '')
        ->set('mountedActions.0.data.price', '')
        ->call('callMountedAction');

    expect($article->fresh()->status)->toBeNull();
});

it('still hands over a value the fields did not have to touch', function () {
    $article = AfdArticle::query()->create(['status' => null, 'price' => null]);

    Livewire::test(AfdHost::class, ['id' => $article->id])
        ->call('mountAction', 'edit')
        ->set('mountedActions.0.data.status', 'draft')
        ->set('mountedActions.0.data.price', '9.99')
        ->call('callMountedAction')
        ->assertSet('seen.status', 'draft')
        ->assertSet('seen.price', '9.99');
});

it("runs the owner's dehydrateStateUsing() on the modal path too", function () {
    Livewire::test(AfdHost::class)
        ->call('mountAction', 'editWithCallback')
        ->set('mountedActions.0.data.code', 'abc')
        ->call('callMountedAction')
        ->assertSet('seen.code', 'ABC');
});

it('dehydrates every wizard step, not only the one on screen at submit', function () {
    Livewire::test(AfdHost::class)
        ->call('mountAction', 'onboard')
        ->set('mountedActions.0.data.status', '')
        ->call('nextActionModalStep')
        ->set('mountedActions.0.data.price', '')
        ->call('callMountedAction')
        ->assertSet('seen.status', null, strict: true)
        ->assertSet('seen.price', null, strict: true);
});

it('dehydrates for a footer action that submits the form', function () {
    Livewire::test(AfdHost::class)
        ->call('mountAction', 'editWithFooter')
        ->set('mountedActions.0.data.status', '')
        ->call('callModalFooterAction', 'apply')
        ->assertSet('seen.status', null, strict: true);
});

it('leaves a non-submitting footer action the raw bag, which is what $get/$set see', function () {
    // Not a hand-over: it works on live state, and must not run a transform with
    // a side effect on a click that was never a submit.
    Livewire::test(AfdHost::class)
        ->call('mountAction', 'editWithCallback')
        ->set('mountedActions.0.data.code', 'abc')
        ->call('callModalFooterAction', 'peek')
        ->assertSet('seen.code', 'abc');
});

it('leaves the live state bag alone — the browser is still bound to it', function () {
    // Dehydration happens at the hand-over, not in the frame's data. Writing the
    // result back would re-dehydrate an already-transformed value on the next
    // submit, and would move a field's value under the open modal.
    Livewire::test(AfdHost::class)
        ->call('mountAction', 'editWithCallback')
        ->set('mountedActions.0.data.code', 'abc')
        ->call('callModalFooterAction', 'apply')
        ->assertSet('seen.code', 'ABC')
        ->assertSet('mountedActions.0.data.code', 'abc');
});
