<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Routing\RoutePage;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Contracts\ProvidesResourceInfolist;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WirePanels\Resources\Pages\ListPage;
use NyonCode\WirePanels\Resources\Pages\ViewPage;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;

/*
 * make:wire-page, make:wire-relation-manager and wire:resources.
 *
 * A generated record page is mounted, not just parsed: it composes three traits
 * over `Page`, and whether their properties and methods agree is a question PHP
 * answers only when the class is loaded.
 */
class PcNote extends Model
{
    protected $table = 'pc_notes';

    protected $guarded = [];

    public $timestamps = false;
}

class PcNoteResource implements DescribesResource, ProvidesPages, ProvidesResourceInfolist, ProvidesResourceTable
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return PcNote::class;
    }

    public static function pages(): array
    {
        return [
            'index' => PcListNotes::class,
            'view' => PcViewNote::class,
            'edit' => RoutePage::make(PcViewNote::class)->uri('{record}/edit')->permission('notes.update'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('body')]);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([TextEntry::make('body')]);
    }
}

class PcListNotes extends ListPage
{
    protected static ?string $resource = PcNoteResource::class;
}

class PcViewNote extends ViewPage
{
    protected static ?string $resource = PcNoteResource::class;
}

afterEach(function () {
    File::deleteDirectory(app_path('Livewire'));
    File::deleteDirectory(resource_path('views/livewire'));
});

it('writes a page of the application own and its view', function () {
    $this->artisan('make:wire-page', ['name' => 'TaskBoard'])
        ->expectsOutputToContain("'task-board' => RoutePage::make(")
        ->assertSuccessful();

    $page = File::get(app_path('Livewire/Pages/TaskBoard.php'));

    expect($page)->toContain('class TaskBoard extends Page')
        ->toContain("protected static string \$view = 'livewire.pages.task-board';")
        ->toContain("protected ?string \$title = 'Task Board';")
        ->and(File::exists(resource_path('views/livewire/pages/task-board.blade.php')))->toBeTrue();

    require_once app_path('Livewire/Pages/TaskBoard.php');

    expect(Livewire::test('App\Livewire\Pages\TaskBoard')->html())->toContain('Task Board');
});

it('writes a record page that mounts against the record and draws its tabs', function () {
    Schema::create('pc_notes', function (Blueprint $table) {
        $table->id();
        $table->string('body');
    });
    PcNote::query()->create(['body' => 'First note']);

    $this->artisan('make:wire-page', ['name' => 'History', '--resource' => PcNoteResource::class])
        ->expectsOutputToContain("->uri('{record}/history')")
        ->assertSuccessful();

    $path = app_path('Livewire/Resources/PcNotes/History.php');

    expect(File::get($path))->toContain('class History extends Page implements ProvidesBreadcrumbs')
        ->toContain('use ResolvesOneRecord;')
        ->toContain('protected static ?string $resource = PcNoteResource::class;');

    require_once $path;

    $html = Livewire::test('App\Livewire\Resources\PcNotes\History', ['record' => 1])->html();

    expect($html)->toContain('History')->toContain('Pc Notes');
});

it('writes a relation manager and says how to embed it', function () {
    $this->artisan('make:wire-relation-manager', ['resource' => 'Order', 'relationship' => 'line_items'])
        ->expectsOutputToContain('relationManagers()')
        ->assertSuccessful();

    $path = app_path('Livewire/Resources/Orders/LineItemsRelationManager.php');

    expect(File::get($path))->toContain('class LineItemsRelationManager extends RelationManager')
        ->toContain("protected string \$relationship = 'lineItems';")
        ->toContain("protected ?string \$title = 'Line Items';");

    exec('php -l '.escapeshellarg($path), $output, $status);
    expect($status)->toBe(0);
});

it('leaves an existing page alone without --force', function () {
    $this->artisan('make:wire-page', ['name' => 'TaskBoard'])->assertSuccessful();
    File::put(app_path('Livewire/Pages/TaskBoard.php'), '<?php // mine');

    $this->artisan('make:wire-page', ['name' => 'TaskBoard'])->expectsOutputToContain('already exists')->assertSuccessful();

    expect(File::get(app_path('Livewire/Pages/TaskBoard.php')))->toBe('<?php // mine');
});

it('lists the registered resources, their surfaces and how many routes they have', function () {
    app(ResourceRegistry::class)->register(PcNoteResource::class);
    Route::middleware('web')->group(fn () => Route::wireResources(only: ['pc-notes']));

    Artisan::call('wire:resources');
    $output = Artisan::output();

    expect($output)->toContain('pc-notes')
        ->toContain('PcNoteResource')
        ->toContain('table, infolist')
        ->toContain('index, view, edit');
});

it('describes one resource page by page, with its routes and permissions', function () {
    app(ResourceRegistry::class)->register(PcNoteResource::class);
    Route::middleware('web')->group(fn () => Route::wireResources(only: ['pc-notes']));

    Artisan::call('wire:resources', ['key' => 'pc-notes']);
    $output = Artisan::output();

    expect($output)->toContain('wire.pc-notes.edit')
        ->toContain('/pc-notes/{record}/edit')
        ->toContain('notes.update');
});

it('says a page is not routed', function () {
    app(ResourceRegistry::class)->register(PcNoteResource::class);

    Artisan::call('wire:resources', ['key' => 'pc-notes']);

    expect(Artisan::output())->toContain('not routed');
});

it('refuses a key nothing is registered under', function () {
    $this->artisan('wire:resources', ['key' => 'nothing'])->assertFailed();
});

it('says when nothing is registered', function () {
    $this->artisan('wire:resources')->expectsOutputToContain('No resources are registered')->assertSuccessful();
});

it('reads a short --resource as the application resource of that name', function () {
    $this->artisan('make:wire-page', ['name' => 'Timeline', '--resource' => 'Order'])->assertSuccessful();

    expect(File::get(app_path('Livewire/Resources/Orders/Timeline.php')))->toContain('use App\Resources\OrderResource;');
});

it('leaves an existing relation manager alone without --force', function () {
    $this->artisan('make:wire-relation-manager', ['resource' => 'Order', 'relationship' => 'items'])->assertSuccessful();

    $this->artisan('make:wire-relation-manager', ['resource' => 'Order', 'relationship' => 'items'])
        ->expectsOutputToContain('already exists')
        ->assertSuccessful();
});
