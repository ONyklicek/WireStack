<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Concerns\WithActions;

/*
 * An action modal prefilled from a record whose column is cast to an enum.
 *
 * `fillFormUsing` hands back whatever the attribute holds, and on a cast column
 * that is the enum case object — not its backing value. The seeded bag is
 * Livewire state, so it has to carry the scalar key: it is what travels to the
 * browser, and it is what a <select> compares its <option value> against. An
 * enum left in there fataled the field's own render before anything could
 * compare anything.
 */

enum ActionFormStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Published = 'published';

    public function getLabel(): ?string
    {
        return ucfirst($this->value);
    }
}

class ActionFormArticle extends Model
{
    protected $table = 'action_form_articles';

    protected $guarded = [];

    protected $casts = ['status' => ActionFormStatus::class];
}

class ActionFormEnumHost extends Component
{
    use WithActions;

    public ?ActionFormArticle $record = null;

    public function mount(?int $id = null): void
    {
        $this->record = $id ? ActionFormArticle::query()->find($id) : null;
    }

    protected function actions(): array
    {
        return [$this->editStatusAction()];
    }

    public function editStatusAction(): Action
    {
        return Action::make('editStatus')
            ->form([Select::make('status')->options(ActionFormStatus::class)])
            // Exactly what an app writes: the record's attribute, straight through.
            ->fillFormUsing(fn () => ['status' => $this->record?->status])
            ->action(fn (array $data) => $this->record?->update(['status' => $data['status']]));
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                <x-wire-actions::button :action="$this->editStatusAction()" />
                <x-wire-actions::modal-host :component="$this" />
            </div>
        BLADE;
    }
}

beforeEach(function () {
    Schema::dropIfExists('action_form_articles');
    Schema::create('action_form_articles', function (Blueprint $table): void {
        $table->id();
        $table->string('status')->nullable();
        $table->timestamps();
    });
});

it('seeds an enum-cast attribute into the modal state as its scalar key', function () {
    $article = ActionFormArticle::query()->create(['status' => ActionFormStatus::Published]);

    Livewire::test(ActionFormEnumHost::class, ['id' => $article->id])
        ->call('mountAction', 'editStatus')
        ->assertSet('mountedActions.0.data.status', 'published');
});

it('renders the select for an enum-cast attribute instead of fataling on its type', function () {
    $article = ActionFormArticle::query()->create(['status' => ActionFormStatus::Published]);

    Livewire::test(ActionFormEnumHost::class, ['id' => $article->id])
        ->call('mountAction', 'editStatus')
        ->assertOk()
        ->assertSee('Published');
});

it('saves the choice back through the cast', function () {
    $article = ActionFormArticle::query()->create(['status' => ActionFormStatus::Published]);

    Livewire::test(ActionFormEnumHost::class, ['id' => $article->id])
        ->call('mountAction', 'editStatus')
        ->set('mountedActions.0.data.status', 'draft')
        ->call('callMountedAction');

    expect($article->fresh()->status)->toBe(ActionFormStatus::Draft);
});

it('resolves the selected label from an enum instance handed to it directly', function () {
    // The render path takes `mixed`: a host can also write the bag itself
    // ($set, a public property assigned from a model), so the label lookup
    // normalises rather than trusting the caller.
    $select = Select::make('status')->options(ActionFormStatus::class);

    expect($select->getSelectedOptionLabels(ActionFormStatus::Published))
        ->toBe(['published' => 'Published']);
});
