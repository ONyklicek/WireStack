<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireTable\Table;

/*
 * `make:wire-resource` — a resource and its pages, in the shape the pages
 * expect.
 *
 * What is asserted is the shape, not the whitespace: the contracts the resource
 * implements follow the flags, the pages it declares are the classes written,
 * every file parses, and nothing a person already edited is overwritten.
 */

afterEach(function () {
    File::deleteDirectory(app_path('Resources'));
    File::deleteDirectory(app_path('Livewire'));
    File::deleteDirectory(base_path('stubs/wire-panels'));
    File::delete(config_path('wire-core.php'));
});

/** Parse a generated file, so a broken template fails here rather than in an application. */
function mrParses(string $path): bool
{
    exec('php -l '.escapeshellarg($path).' 2>&1', $output, $status);

    return $status === 0;
}

it('writes the resource and its list, create and edit pages', function () {
    $this->artisan('make:wire-resource', ['name' => 'Order'])->assertSuccessful();

    $resource = File::get(app_path('Resources/OrderResource.php'));

    expect($resource)->toContain('final class OrderResource implements DescribesResource, ProvidesPages, ProvidesResourceForm, ProvidesResourceTable')
        ->toContain('return Order::class;')
        ->toContain('use App\Models\Order;')
        ->toContain("'index' => ListOrders::class,")
        ->toContain("'create' => CreateOrder::class,")
        ->toContain("'edit' => EditOrder::class,")
        ->not->toContain('ViewOrder');

    foreach (['ListOrders' => 'ListPage', 'CreateOrder' => 'CreatePage', 'EditOrder' => 'EditPage'] as $page => $base) {
        $path = app_path("Livewire/Resources/Orders/{$page}.php");

        expect(File::get($path))->toContain("class {$page} extends {$base}")
            ->toContain('protected static ?string $resource = OrderResource::class;')
            ->and(mrParses($path))->toBeTrue();
    }

    expect(File::get(app_path('Livewire/Resources/Orders/EditOrder.php')))->toContain('$this->deleteHeaderAction(),')
        ->and(mrParses(app_path('Resources/OrderResource.php')))->toBeTrue();
});

it('takes the name with or without the Resource suffix', function () {
    $this->artisan('make:wire-resource', ['name' => 'InvoiceLineResource'])->assertSuccessful();

    expect(File::exists(app_path('Resources/InvoiceLineResource.php')))->toBeTrue()
        ->and(File::exists(app_path('Livewire/Resources/InvoiceLines/ListInvoiceLines.php')))->toBeTrue();
});

it('adds a view page and an infolist with --view', function () {
    $this->artisan('make:wire-resource', ['name' => 'Order', '--view' => true])->assertSuccessful();

    $resource = File::get(app_path('Resources/OrderResource.php'));

    expect($resource)->toContain('ProvidesResourceInfolist')
        ->toContain('public function infolist(Infolist $infolist): Infolist')
        ->toContain("'view' => ViewOrder::class,")
        ->and(mrParses(app_path('Resources/OrderResource.php')))->toBeTrue()
        ->and(File::get(app_path('Livewire/Resources/Orders/ViewOrder.php')))->toContain('extends ViewPage');
});

it('writes one ManagePage with --simple', function () {
    $this->artisan('make:wire-resource', ['name' => 'Tag', '--simple' => true])->assertSuccessful();

    expect(File::get(app_path('Resources/TagResource.php')))->toContain("'index' => ManageTags::class,")
        ->not->toContain('CreateTag')
        ->and(File::get(app_path('Livewire/Resources/Tags/ManageTags.php')))->toContain('class ManageTags extends ManagePage')
        ->and(File::exists(app_path('Livewire/Resources/Tags/CreateTag.php')))->toBeFalse();
});

it('manages the trash with --soft-deletes', function () {
    $this->artisan('make:wire-resource', ['name' => 'Order', '--soft-deletes' => true])->assertSuccessful();

    expect(File::get(app_path('Resources/OrderResource.php')))->toContain('ManagesTrashedRecords')
        ->and(File::get(app_path('Livewire/Resources/Orders/EditOrder.php')))
        ->toContain('$this->restoreHeaderAction(),')
        ->toContain('$this->forceDeleteHeaderAction(),');
});

it('reads fields and columns off the model table with --generate', function () {
    Schema::create('mr_articles', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('body')->nullable();
        $table->boolean('published')->default(false);
        $table->date('published_on')->nullable();
        $table->integer('views')->default(0);
        $table->timestamps();
    });

    // A model in the application's namespace, which a test cannot autoload from a
    // file: the generator reads `App\Models\{Name}` by default, and this is the
    // one place that default is exercised. The string is a literal of this test.
    eval('namespace App\Models; class MrArticle extends \Illuminate\Database\Eloquent\Model { protected $table = "mr_articles"; }');

    $this->artisan('make:wire-resource', ['name' => 'MrArticle', '--generate' => true, '--view' => true])->assertSuccessful();

    $path = app_path('Resources/MrArticleResource.php');
    $resource = File::get($path);

    expect($resource)->toContain("TextInput::make('title')->required(),")
        ->toContain("Textarea::make('body'),")
        ->toContain("Toggle::make('published'),")
        ->toContain("DateTimePicker::make('published_on')->asDate(),")
        ->toContain("TextInput::make('views')->numeric(),")
        ->toContain("BooleanColumn::make('published'),")
        ->toContain("TextEntry::make('published_on')->date(),")
        ->not->toContain("'created_at'")
        ->not->toContain("make('id')")
        ->and(mrParses($path))->toBeTrue();

    // And it runs: every generated call names a method that exists.
    foreach (File::allFiles(app_path('Livewire/Resources/MrArticles')) as $page) {
        require_once $page->getPathname();
    }
    require_once $path;

    $instance = app('App\Resources\MrArticleResource');

    expect($instance->form(Form::make())->getSchema())->toHaveCount(5)
        ->and($instance->table(Table::make())->getColumns())->toHaveCount(5)
        ->and($instance->infolist(Infolist::make())->getSchema())->toHaveCount(5);
});

it('writes without generated fields when the model does not exist yet', function () {
    $this->artisan('make:wire-resource', ['name' => 'Nothing', '--generate' => true])
        ->expectsOutputToContain('does not exist yet')
        ->assertSuccessful();

    expect(File::get(app_path('Resources/NothingResource.php')))->toContain("// TextInput::make('name')->required(),");
});

it('never overwrites a file without --force', function () {
    $this->artisan('make:wire-resource', ['name' => 'Order'])->assertSuccessful();
    File::put(app_path('Resources/OrderResource.php'), '<?php // mine');

    $this->artisan('make:wire-resource', ['name' => 'Order'])
        ->expectsOutputToContain('already exists')
        ->assertSuccessful();

    expect(File::get(app_path('Resources/OrderResource.php')))->toBe('<?php // mine');

    $this->artisan('make:wire-resource', ['name' => 'Order', '--force' => true])->assertSuccessful();

    expect(File::get(app_path('Resources/OrderResource.php')))->toContain('class OrderResource');
});

it('registers the resource in a published config with --register', function () {
    File::put(config_path('wire-core.php'), "<?php\n\nreturn [\n    'resources' => [\n    ],\n];\n");

    $this->artisan('make:wire-resource', ['name' => 'Order', '--register' => true])
        ->expectsOutputToContain('Registered in config/wire-core.php')
        ->assertSuccessful();

    expect(File::get(config_path('wire-core.php')))->toContain('\App\Resources\OrderResource::class,');

    $this->artisan('make:wire-resource', ['name' => 'Order', '--register' => true, '--force' => true])
        ->expectsOutputToContain('Already registered')
        ->assertSuccessful();
});

it('says how to register it where there is no config to write into', function () {
    $this->artisan('make:wire-resource', ['name' => 'Order', '--register' => true])
        ->expectsOutputToContain("config('wire-core.resources')")
        ->assertSuccessful();
});

it('prefers a published stub', function () {
    File::ensureDirectoryExists(base_path('stubs/wire-panels'));
    File::put(base_path('stubs/wire-panels/resource-page.stub'), "<?php\n\nnamespace {{ namespace }};\n\n// custom\nclass {{ class }} {}\n");

    $this->artisan('make:wire-resource', ['name' => 'Order'])->assertSuccessful();

    expect(File::get(app_path('Livewire/Resources/Orders/ListOrders.php')))->toContain('// custom');
});
