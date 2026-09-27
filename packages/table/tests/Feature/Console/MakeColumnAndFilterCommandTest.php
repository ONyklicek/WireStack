<?php

declare(strict_types=1);

use App\Tables\Columns\PriceColumn;
use App\Tables\Filters\RegionFilter;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/*
 * make:wire-column and make:wire-filter.
 *
 * The generated class is loaded and used, not just read: whether it names a
 * view that exists, and whether that view renders against the base class it
 * extends, is a question only running it answers.
 */
afterEach(function () {
    File::deleteDirectory(app_path('Tables'));
    File::deleteDirectory(resource_path('views/tables'));
});

it('writes a column whose cells render its own view', function () {
    $this->artisan('make:wire-column', ['name' => 'price'])
        ->expectsOutputToContain('Created [App\\Tables\\Columns\\PriceColumn]')
        ->expectsOutputToContain("PriceColumn::make('price')")
        ->assertSuccessful();

    expect(resource_path('views/tables/columns/price.blade.php'))->toBeFile();

    require_once app_path('Tables/Columns/PriceColumn.php');

    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->setRawAttributes(['price' => '12.50']);

    $column = PriceColumn::make('price');

    expect($column->renderCell($record))->toContain('12.50')
        ->and($column->renderCellFast($record))->toContain('12.50')
        ->and(PriceColumn::make('price')->placeholder('none')->renderCell(tap(clone $record)->setRawAttributes([])))->toContain('none');
});

it('keeps an edited view and says so, and overwrites the class only when forced', function () {
    $this->artisan('make:wire-column', ['name' => 'PriceColumn'])->assertSuccessful();

    File::put(resource_path('views/tables/columns/price.blade.php'), 'edited');
    File::put(app_path('Tables/Columns/PriceColumn.php'), '<?php // edited');

    $this->artisan('make:wire-column', ['name' => 'Price'])
        ->expectsOutputToContain('left as it is')
        ->expectsOutputToContain('left alone')
        ->assertSuccessful();

    expect(File::get(app_path('Tables/Columns/PriceColumn.php')))->toBe('<?php // edited');

    $this->artisan('make:wire-column', ['name' => 'Price', '--force' => true])->assertSuccessful();

    expect(File::get(app_path('Tables/Columns/PriceColumn.php')))->toContain('class PriceColumn extends Column')
        ->and(File::get(resource_path('views/tables/columns/price.blade.php')))->toBe('edited');
});

it('writes a filter that renders its own control and always narrows through apply()', function () {
    $this->artisan('make:wire-filter', ['name' => 'Region'])
        ->expectsOutputToContain('Created [App\\Tables\\Filters\\RegionFilter]')
        ->assertSuccessful();

    require_once app_path('Tables/Filters/RegionFilter.php');

    Schema::create('regions_filtered', fn ($table) => $table->string('region'));

    $model = new class extends Model
    {
        protected $table = 'regions_filtered';
    };

    $filter = RegionFilter::make('region');

    expect($filter->bypassesPlanner())->toBeTrue()
        ->and($filter->render(['value' => 'EU']))
        ->toContain('wire:model.live.debounce.500ms="tableState.filters.region.value"')
        ->toContain('value="EU"')
        ->and($filter->apply($model->newQuery(), 'EU')->toRawSql())->toContain("where \"region\" = 'EU'")
        ->and($filter->apply($model->newQuery(), '')->toRawSql())->not->toContain('where');
});

it('draws the package control for no filter, whatever it is called', function () {
    // Named like the shipped select filter: the view it writes is
    // tables.filters.select, which wire-table:: also has. The class renders
    // its own view by name, so the package's is never the one drawn.
    $this->artisan('make:wire-filter', ['name' => 'Select'])->assertSuccessful();
    File::put(resource_path('views/tables/filters/select.blade.php'), 'mine');

    require_once app_path('Tables/Filters/SelectFilter.php');

    expect(SelectFilter::make('x')->render())->toBe('mine');
});
