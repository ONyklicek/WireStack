<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;
use NyonCode\WireTable\Columns\Column;

enum ColTestStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Published = 'published';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
        };
    }
}

// ─── Factory & Name ─────────────────────────────────────────────────────────

it('can be created via make()', function () {
    $column = Column::make('name');

    expect($column)->toBeInstanceOf(Column::class)
        ->and($column->getName())->toBe('name');
});

// ─── Label ──────────────────────────────────────────────────────────────────

it('generates label from name using Str::headline', function () {
    expect(Column::make('first_name')->getLabel())->toBe('First Name')
        ->and(Column::make('created_at')->getLabel())->toBe('Created At');
});

it('can set custom label', function () {
    expect(Column::make('name')->label('Jméno')->getLabel())->toBe('Jméno');
});

// ─── Sortable ───────────────────────────────────────────────────────────────

it('is not sortable by default', function () {
    expect(Column::make('name')->isSortable())->toBeFalse();
});

it('can be set to sortable', function () {
    expect(Column::make('name')->sortable()->isSortable())->toBeTrue();
});

it('can set custom sort callback', function () {
    $callback = fn ($query, $direction) => $query->orderBy('name', $direction);
    $column = Column::make('name')->sortable(query: $callback);

    expect($column->isSortable())->toBeTrue()
        ->and($column->getSortCallback())->toBe($callback);
});

it('sortUsing sets sortable and callback', function () {
    $callback = fn ($query, $dir) => $query;
    $column = Column::make('name')->sortUsing($callback);

    expect($column->isSortable())->toBeTrue()
        ->and($column->getSortCallback())->toBe($callback);
});

// ─── Searchable ─────────────────────────────────────────────────────────────

it('is not searchable by default', function () {
    expect(Column::make('name')->isSearchable())->toBeFalse();
});

it('can be set to searchable', function () {
    expect(Column::make('name')->searchable()->isSearchable())->toBeTrue();
});

it('can set explicit search columns', function () {
    $column = Column::make('full_name')->searchable(['first_name', 'last_name']);

    expect($column->isSearchable())->toBeTrue()
        ->and($column->getSearchColumns())->toBe(['first_name', 'last_name']);
});

it('can set custom search callback', function () {
    $callback = fn ($query, $search) => $query;
    $column = Column::make('name')->searchable(query: $callback);

    expect($column->getSearchCallback())->toBe($callback);
});

it('searchUsing sets searchable and callback', function () {
    $callback = fn ($query, $search) => $query;
    $column = Column::make('name')->searchUsing($callback);

    expect($column->isSearchable())->toBeTrue()
        ->and($column->getSearchCallback())->toBe($callback);
});

// ─── Hidden ─────────────────────────────────────────────────────────────────

it('is not hidden by default', function () {
    expect(Column::make('name')->isHidden())->toBeFalse();
});

it('can be hidden', function () {
    expect(Column::make('name')->hidden()->isHidden())->toBeTrue();
});

// ─── Relation ───────────────────────────────────────────────────────────────

it('parses relation from dot notation', function () {
    $column = Column::make('author.name');

    expect($column->getRelation())->toBe('author');
});

it('parses nested relation', function () {
    $column = Column::make('author.profile.avatar');

    expect($column->getRelation())->toBe('author.profile');
});

it('has no relation for simple columns', function () {
    expect(Column::make('name')->getRelation())->toBeNull();
});

// ─── Pivot ──────────────────────────────────────────────────────────────────

it('is not pivot by default', function () {
    expect(Column::make('name')->isPivot())->toBeFalse();
});

it('can be set as pivot', function () {
    expect(Column::make('name')->pivot()->isPivot())->toBeTrue();
});

// ─── Display Formatting ─────────────────────────────────────────────────────

it('can set prefix and suffix', function () {
    $column = Column::make('price')->prefix('$')->suffix(' USD');

    // We can test the prefix/suffix are stored (getState applies them)
    expect($column)->toBeInstanceOf(Column::class);
});

it('can set placeholder', function () {
    expect(Column::make('name')->placeholder('N/A')->getPlaceholder())->toBe('N/A');
});

it('has default placeholder', function () {
    expect(Column::make('name')->getPlaceholder())->toBe('-');
});

it('can set width', function () {
    $column = Column::make('id')->width('80px');

    expect($column)->toBeInstanceOf(Column::class);
});

it('can set alignment', function () {
    $column = Column::make('price')->alignment('right');

    expect($column)->toBeInstanceOf(Column::class);
});

it('can enable text wrap', function () {
    $column = Column::make('description')->wrap();

    expect($column)->toBeInstanceOf(Column::class);
});

it('can set character limit', function () {
    $column = Column::make('description')->limit(100);

    expect($column)->toBeInstanceOf(Column::class);
});

it('can enable copyable', function () {
    $column = Column::make('email')->copyable()->copyMessage('Zkopírováno!');

    expect($column)->toBeInstanceOf(Column::class);
});

// ─── Responsive Visibility ──────────────────────────────────────────────────

it('has no responsive visibility by default', function () {
    expect(Column::make('name')->hasResponsiveVisibility())->toBeFalse();
});

it('can set visibleFrom breakpoint', function () {
    $column = Column::make('email')->visibleFrom('md');

    expect($column->hasResponsiveVisibility())->toBeTrue()
        ->and($column->getResponsiveClasses())->toContain('hidden')
        ->and($column->getResponsiveClasses())->toContain('md:table-cell');
});

it('can set hiddenFrom breakpoint', function () {
    $column = Column::make('email')->hiddenFrom('lg');

    expect($column->hasResponsiveVisibility())->toBeTrue()
        ->and($column->getResponsiveClasses())->toContain('lg:hidden');
});

it('onlyOnMobile hides from md', function () {
    $column = Column::make('name')->onlyOnMobile();

    expect($column->getResponsiveClasses())->toContain('md:hidden');
});

it('onlyOnDesktop shows from md', function () {
    $column = Column::make('name')->onlyOnDesktop();

    expect($column->getResponsiveClasses())->toContain('hidden')
        ->and($column->getResponsiveClasses())->toContain('md:table-cell');
});

it('mobileOnly is alias for onlyOnMobile', function () {
    $a = Column::make('name')->mobileOnly()->getResponsiveClasses();
    $b = Column::make('name')->onlyOnMobile()->getResponsiveClasses();

    expect($a)->toBe($b);
});

it('desktopOnly is alias for onlyOnDesktop', function () {
    $a = Column::make('name')->desktopOnly()->getResponsiveClasses();
    $b = Column::make('name')->onlyOnDesktop()->getResponsiveClasses();

    expect($a)->toBe($b);
});

it('onlyOnTabletAndUp sets visibleFrom sm', function () {
    $column = Column::make('name')->onlyOnTabletAndUp();

    expect($column->getResponsiveClasses())->toContain('sm:table-cell');
});

it('onlyOnLargeScreens sets visibleFrom lg', function () {
    $column = Column::make('name')->onlyOnLargeScreens();

    expect($column->getResponsiveClasses())->toContain('lg:table-cell');
});

// ─── Column Filtering ───────────────────────────────────────────────────────

it('is not filterable by default', function () {
    expect(Column::make('name')->isFilterable())->toBeFalse();
});

it('can be set to filterable', function () {
    expect(Column::make('status')->filterable()->isFilterable())->toBeTrue();
});

it('expands an enum class into filter options', function () {
    expect(Column::make('status')->filterAsSelect(ColTestStatus::class)->getFilter()->getOptions())
        ->toBe(['draft' => 'Draft', 'published' => 'Published']);

    expect(Column::make('status')->filterable(type: 'select', options: ColTestStatus::class)->getFilter()->getOptions())
        ->toBe(['draft' => 'Draft', 'published' => 'Published']);
});

it('ignores a non-scalar text filter value instead of throwing (regression)', function () {
    Schema::create('col_filter_items', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
    DB::table('col_filter_items')->insert([['name' => 'a'], ['name' => 'b']]);

    $model = new class extends Model
    {
        protected $table = 'col_filter_items';

        public $timestamps = false;

        protected $guarded = [];
    };

    // A crafted/stale array value used to cause "Array to string conversion"
    // when building the LIKE clause; the text filter must now ignore it and
    // return all rows.
    $result = Column::make('name')->filterable()->applyFilter($model->newQuery(), ['x', 'y']);

    expect($result->count())->toBe(2);

    Schema::dropIfExists('col_filter_items');
});

// ─── Inline Editing ─────────────────────────────────────────────────────────

it('is not editable by default', function () {
    expect(Column::make('name')->isEditable())->toBeFalse();
});

it('can be set to editable', function () {
    expect(Column::make('name')->editable()->isEditable())->toBeTrue();
});

it('expands an enum class into editable select options', function () {
    expect(Column::make('status')->editable(type: 'select', options: ColTestStatus::class)->getEditableOptions())
        ->toBe(['draft' => 'Draft', 'published' => 'Published']);
});

// ─── Toggleable ─────────────────────────────────────────────────────────────

it('is toggleable by default', function () {
    expect(Column::make('name')->isToggleable())->toBeTrue();
});

// ─── URL ────────────────────────────────────────────────────────────────────

it('can set url callback', function () {
    $column = Column::make('title')->actionUrl(fn ($record) => '/posts/'.$record->slug);

    $record = Mockery::mock(Model::class);
    $record->shouldReceive('getAttribute')->with('slug')->andReturn('hello-world');

    expect($column->getUrl($record))->toBe('/posts/hello-world');
});

it('can set url to open in new tab', function () {
    $column = Column::make('link')->actionUrl(fn () => '/test', openInNewTab: true);

    expect($column->shouldOpenUrlInNewTab())->toBeTrue();
});

// ─── Text Styling ───────────────────────────────────────────────────────────

it('can set description', function () {
    $column = Column::make('name')
        ->description('Popis sloupce', 'above');

    expect($column)->toBeInstanceOf(Column::class);
});

// ─── State ──────────────────────────────────────────────────────────────────

it('can set custom state callback', function () {
    $column = Column::make('full_name')
        ->state(fn ($record) => $record->first_name.' '.$record->last_name);

    expect($column)->toBeInstanceOf(Column::class);
});
