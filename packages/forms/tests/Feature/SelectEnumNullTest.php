<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/*
 * A Select whose options come from an enum, against a nullable column.
 *
 * The empty choice is the one a native <select> submits as '' — and '' is not a
 * valid backing value for any enum, so a model with an enum cast rejects it.
 * Clearing such a field has to reach the database as null.
 */

enum SelectNullStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Published = 'published';

    public function getLabel(): ?string
    {
        return ucfirst($this->value);
    }
}

class SelectNullArticle extends Model
{
    protected $table = 'select_null_articles';

    protected $guarded = [];

    protected $casts = ['status' => SelectNullStatus::class];
}

class SelectNullHost extends Component
{
    use WithForms;

    public ?SelectNullArticle $record = null;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(?int $id = null): void
    {
        $this->record = $id ? SelectNullArticle::query()->find($id) : new SelectNullArticle;
        $this->form->model($this->record)->fill($this->record->attributesToArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model($this->record)
            ->schema([
                Select::make('status')
                    ->options(SelectNullStatus::class)
                    ->placeholder('No status'),
            ]);
    }

    public function save(): void
    {
        $this->form->save();
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

beforeEach(function () {
    Schema::dropIfExists('select_null_articles');
    Schema::create('select_null_articles', function (Blueprint $table): void {
        $table->id();
        $table->string('title')->default('t');
        $table->string('status')->nullable();
        $table->timestamps();
    });
});

it('loads a null enum value as an empty selection', function () {
    $article = SelectNullArticle::query()->create(['status' => null]);

    Livewire::test(SelectNullHost::class, ['id' => $article->id])
        ->assertSet('data.status', null);
});

it('renders the placeholder as the selected option for a null value', function () {
    $article = SelectNullArticle::query()->create(['status' => null]);

    expect(Livewire::test(SelectNullHost::class, ['id' => $article->id])->html())
        ->toContain('No status');
});

// The core of it: a native <select> submits '' for the empty option, and '' is
// not a valid backing value for any enum — the cast throws on save.
it('clears an enum column back to null when the empty option is chosen', function () {
    $article = SelectNullArticle::query()->create(['status' => SelectNullStatus::Published]);

    Livewire::test(SelectNullHost::class, ['id' => $article->id])
        ->assertSet('data.status', 'published')
        ->set('data.status', '')
        ->call('save');

    expect($article->fresh()->status)->toBeNull();
});

it('still saves a real enum choice', function () {
    $article = SelectNullArticle::query()->create(['status' => null]);

    Livewire::test(SelectNullHost::class, ['id' => $article->id])
        ->set('data.status', 'published')
        ->call('save');

    expect($article->fresh()->status)->toBe(SelectNullStatus::Published);
});

it('creates a record with no enum value at all', function () {
    Livewire::test(SelectNullHost::class)
        ->set('data.status', '')
        ->call('save');

    expect(SelectNullArticle::query()->latest('id')->first()->status)->toBeNull();
});
