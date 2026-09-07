<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\ViewException;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Data\ArrayRecord;
use NyonCode\WireCore\Core\Data\EloquentRecord;
use NyonCode\WireCore\Core\Data\RecordContract;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Contracts\ProvidesResourceInfolist;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Contracts\ProvidesResourceForm;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WirePanels\Resources\Pages\EditPage;
use NyonCode\WirePanels\Resources\Pages\ViewPage;

/*
 * A page over a record that is not an Eloquent model.
 *
 * `resolveRecord()` promised this in its docblock — "override for a non-Eloquent
 * source" — while returning `?Model`, so the override could not return what it
 * found. Both halves are pinned here: what now works, and what still refuses,
 * because the write path is Eloquent all the way down and pretending otherwise
 * would fail inside the save instead of at the page.
 */

class NerReportResource implements DescribesResource, ProvidesResourceForm, ProvidesResourceInfolist
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([TextEntry::make('title')]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([TextInput::make('title')]);
    }
}

/** A page fed by something that is not a database. */
class NerViewReport extends ViewPage
{
    protected static ?string $resource = NerReportResource::class;

    protected function resolveRecord(): RecordContract
    {
        return new ArrayRecord(['id' => 7, 'title' => 'Quarterly close'], 'id');
    }
}

class NerEditReport extends EditPage
{
    protected static ?string $resource = NerReportResource::class;

    protected function resolveRecord(): RecordContract
    {
        return new ArrayRecord(['id' => 7, 'title' => 'Quarterly close'], 'id');
    }
}

it('renders a read-only page over a record that is not a model', function () {
    // The infolist takes `mixed` and reads through the contract, so this path
    // was one return type away from working all along.
    Livewire::test(NerViewReport::class, ['record' => 7])
        ->assertOk()
        ->assertSee('Quarterly close');
});

it('refuses to bind a form to a record that cannot be a model, and says what to do', function () {
    // Not a limitation being hidden: saving is Eloquent — relationship
    // repeaters, optimistic locking, $model->save() — so the refusal names the
    // seam a non-Eloquent source writes through instead.
    // `->html()` rather than the bare test(): the form is composed when the page
    // renders, which is where the refusal has to happen — before a save exists
    // to fail inside.
    // The refusal is raised while the page renders its form, so it arrives
    // wrapped — the same shape every other page refusal in these tests has, and
    // the reason the TestCase carries a helper for reading the message.
    expect(fn () => Livewire::test(NerEditReport::class, ['record' => 7])->html())
        ->toThrow(ViewException::class, 'Form::using()');
});

class NerOrder extends Model
{
    protected $table = 'ner_orders';

    protected $guarded = [];

    public $timestamps = false;
}

class NerOrderResource implements DescribesResource, ProvidesResourceForm
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return NerOrder::class;
    }

    public function form(Form $form): Form
    {
        return $form->schema([TextInput::make('number')]);
    }
}

/** A page whose source hands back a contract that *does* wrap a model. */
class NerEditOrder extends EditPage
{
    protected static ?string $resource = NerOrderResource::class;

    protected function resolveRecord(): RecordContract
    {
        return new EloquentRecord(NerOrder::query()->findOrFail($this->record));
    }
}

it('unwraps a contract that holds a model and binds the form to it', function () {
    // The other half of the refusal: a source may wrap Eloquent for its own
    // reasons — a tenant-scoped source does exactly that — and a page over it
    // must behave like any other edit page rather than being turned away.
    Schema::create('ner_orders', function (Blueprint $table) {
        $table->id();
        $table->string('number');
    });

    NerOrder::create(['number' => 'ORD-9']);

    Livewire::test(NerEditOrder::class, ['record' => 1])
        ->assertOk()
        ->assertSet('data.number', 'ORD-9');
});
