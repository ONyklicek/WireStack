<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
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
 * A record page whose key reaches no record.
 *
 * It used to render anyway — `find()` answered null and the page drew itself
 * around nothing: an empty view with a 200 for a plain resource, and a 500 from
 * inside a Blade view where an infolist entry needed the record. Found on a
 * freshly installed application, where `/admin/media/1` and `/admin/audit-log/1`
 * were server errors before anybody had uploaded or changed anything.
 */

class MrRow extends Model
{
    protected $table = 'mr_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class MrResource implements DescribesResource, ProvidesResourceForm, ProvidesResourceInfolist
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return MrRow::class;
    }

    public function form(Form $form): Form
    {
        return $form->schema([TextInput::make('name')]);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([TextEntry::make('name')]);
    }
}

class MrView extends ViewPage
{
    protected static ?string $resource = MrResource::class;
}

class MrEdit extends EditPage
{
    protected static ?string $resource = MrResource::class;
}

beforeEach(function () {
    Schema::create('mr_rows', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });

    MrRow::query()->create(['id' => 1, 'name' => 'One']);
});

afterEach(function () {
    Schema::dropIfExists('mr_rows');
});

it('answers a key that reaches nothing with not-found, on the view page and the edit page', function () {
    Livewire::test(MrView::class, ['record' => 999])->assertNotFound();
    Livewire::test(MrEdit::class, ['record' => 999])->assertNotFound();
});

it('still opens a record that exists', function () {
    Livewire::test(MrView::class, ['record' => 1])->assertOk()->assertSee('One');
    Livewire::test(MrEdit::class, ['record' => 1])->assertOk();
});

it('takes a record handed over whole without looking it up again', function () {
    // A model is already the answer; asking the database whether it exists
    // would be a query for something the caller is holding.
    Livewire::test(MrView::class, ['record' => new MrRow(['id' => 555, 'name' => 'Unsaved'])])->assertOk();
});
