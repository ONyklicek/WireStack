<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use NyonCode\WireForms\Components\BelongsToSelect;
use NyonCode\WireForms\Components\TextInput;

function renderBelongsToSelect(BelongsToSelect $field): string
{
    view()->share('errors', new ViewErrorBag);

    return view('wire-forms::components.belongs-to-select', ['field' => $field])->render();
}

test('make creates belongs-to-select with name', function () {
    $field = BelongsToSelect::make('company_id');

    expect($field->getName())->toBe('company_id');
});

test('relationship sets relation name and title attribute', function () {
    $field = BelongsToSelect::make('company_id')
        ->relationship('company', 'name');

    expect($field->getRelationship())->toBe('company')
        ->and($field->getTitleAttribute())->toBe('name');
});

test('preload flag defaults to false', function () {
    $field = BelongsToSelect::make('company_id');

    expect($field->isPreload())->toBeFalse();
});

test('preload can be enabled', function () {
    $field = BelongsToSelect::make('company_id')->preload();

    expect($field->isPreload())->toBeTrue();
});

test('create option form can be set', function () {
    $schema = [
        TextInput::make('name'),
    ];

    $field = BelongsToSelect::make('company_id')
        ->createOptionForm($schema);

    expect($field->hasCreateOptionForm())->toBeTrue()
        ->and($field->getCreateOptionFormSchema())->toHaveCount(1);
});

test('has no create option form by default', function () {
    $field = BelongsToSelect::make('company_id');

    expect($field->hasCreateOptionForm())->toBeFalse()
        ->and($field->getCreateOptionFormSchema())->toBeNull();
});

test('inherits searchable from Select', function () {
    $field = BelongsToSelect::make('company_id')->searchable();

    expect($field->isSearchable())->toBeTrue();
});

test('searchable renders the shared combobox with a search input (regression: had its own copy)', function () {
    $field = BelongsToSelect::make('company_id')
        ->options(['1' => 'Acme', '2' => 'Globex'])
        ->searchable();

    $html = renderBelongsToSelect($field);

    expect($html)
        ->toContain('x-teleport')
        ->toContain("\$wire.entangle('company_id')")
        ->toContain('x-ref="searchInput"')
        ->not->toContain('<select');
});

test('non-searchable renders the shared combobox without a search input', function () {
    // Unified design: a non-searchable, non-native select is the same combobox as
    // the searchable one, just without the in-panel search input.
    $field = BelongsToSelect::make('company_id')->options(['1' => 'Acme']);

    expect(renderBelongsToSelect($field))
        ->toContain('x-teleport')
        ->toContain("\$wire.entangle('company_id')")
        ->not->toContain('<select')
        ->not->toContain('x-ref="searchInput"');
});

test('create-option form renders a "create new" footer inside the combobox panel', function () {
    $field = BelongsToSelect::make('company_id')
        ->options(['1' => 'Acme'])
        ->searchable()
        ->createOptionForm([TextInput::make('name')]);

    expect(renderBelongsToSelect($field))
        ->toContain("mountAction('company_id_create_option')")
        ->toContain('Create new');
});

test('inherits options from Select when manually set', function () {
    $field = BelongsToSelect::make('company_id')
        ->options(['1' => 'Acme', '2' => 'Globex']);

    expect($field->getOptions())->toBe(['1' => 'Acme', '2' => 'Globex']);
});

test('returns empty options when no relationship and no manual options', function () {
    $field = BelongsToSelect::make('company_id')
        ->relationship('company', 'name');

    // No record set, so cannot resolve
    expect($field->getOptions())->toBe([]);
});

test('searchable without preload returns empty options for AJAX loading', function () {
    $field = BelongsToSelect::make('company_id')
        ->relationship('company', 'name')
        ->searchable();

    expect($field->getOptions())->toBe([]);
});

test('modify options query callback can be set', function () {
    $callback = fn ($query) => $query->where('active', true);

    $field = BelongsToSelect::make('company_id')
        ->modifyOptionsQueryUsing($callback);

    // Just verify it can be set without error
    expect($field)->toBeInstanceOf(BelongsToSelect::class);
});

test('create option using callback can be set', function () {
    $callback = fn ($data) => null;

    $field = BelongsToSelect::make('company_id')
        ->createOptionUsing($callback);

    expect($field)->toBeInstanceOf(BelongsToSelect::class);
});

test('record can be set for relationship resolution', function () {
    $field = BelongsToSelect::make('company_id');

    // Without a record, this is a no-op
    $field->record(null);

    expect($field)->toBeInstanceOf(BelongsToSelect::class);
});

test('search options returns empty without relationship', function () {
    $field = BelongsToSelect::make('company_id');

    expect($field->searchOptions('test'))->toBe([]);
});

test('view name is belongs-to-select', function () {
    $field = BelongsToSelect::make('company_id');

    // Access via reflection since viewName() is protected
    $reflection = new ReflectionMethod($field, 'viewName');

    expect($reflection->invoke($field))->toBe('wire-forms::components.belongs-to-select');
});

test('create option returns null without record or relationship', function () {
    $field = BelongsToSelect::make('company_id');

    expect($field->createOption(['name' => 'Acme']))->toBeNull();
});

test('create option uses custom callback when set', function () {
    $called = false;
    $passedData = null;

    $field = BelongsToSelect::make('company_id')
        ->createOptionUsing(function (array $data) use (&$called, &$passedData) {
            $called = true;
            $passedData = $data;

            return null; // simulate return
        });

    $field->createOption(['name' => 'New Company']);

    expect($called)->toBeTrue()
        ->and($passedData)->toBe(['name' => 'New Company']);
});

test('preload with searchable returns empty options without record', function () {
    $field = BelongsToSelect::make('company_id')
        ->relationship('company', 'name')
        ->searchable()
        ->preload();

    // No record set, so resolveRelatedModel returns null → empty options
    expect($field->getOptions())->toBe([]);
});

test('search options returns empty without title attribute', function () {
    $field = BelongsToSelect::make('company_id');
    $field->record(null);

    expect($field->searchOptions('acme'))->toBe([]);
});

test('manual options take precedence over relationship', function () {
    $field = BelongsToSelect::make('company_id')
        ->relationship('company', 'name')
        ->options(['1' => 'Manual Co']);

    expect($field->getOptions())->toBe(['1' => 'Manual Co']);
});

test('preload defaults to false and is toggleable', function () {
    $field = BelongsToSelect::make('company_id');

    expect($field->isPreload())->toBeFalse();

    $field->preload(true);
    expect($field->isPreload())->toBeTrue();

    $field->preload(false);
    expect($field->isPreload())->toBeFalse();
});

// ─── Relationship-driven remote search (regression: inherited remote API was dead) ───

class BtsCompany extends Model
{
    protected $table = 'bts_companies';

    protected $guarded = [];

    public $timestamps = false;
}

class BtsAuthor extends Model
{
    protected $table = 'bts_authors';

    protected $guarded = [];

    public $timestamps = false;

    public function company(): BelongsTo
    {
        return $this->belongsTo(BtsCompany::class);
    }
}

function makeRemoteBelongsToSelect(): BelongsToSelect
{
    Schema::create('bts_companies', function ($table) {
        $table->id();
        $table->string('name');
    });
    Schema::create('bts_authors', function ($table) {
        $table->id();
        $table->foreignId('company_id')->nullable();
    });

    return BelongsToSelect::make('company_id')
        ->relationship('company', 'name')
        ->searchable()
        ->record(new BtsAuthor);
}

test('a searchable relationship select is remote search (regression: searchable without preload rendered an empty, dead combobox)', function () {
    $field = BelongsToSelect::make('company_id')->relationship('company', 'name')->searchable();

    expect($field->isRemoteSearch())->toBeTrue()
        ->and($field->hasSearchResultsCallback())->toBeTrue();
});

test('preload keeps a searchable relationship select client-side', function () {
    $field = BelongsToSelect::make('company_id')->relationship('company', 'name')->searchable()->preload();

    expect($field->isRemoteSearch())->toBeFalse();
});

test('a non-searchable relationship select is not remote search', function () {
    expect(BelongsToSelect::make('company_id')->relationship('company', 'name')->isRemoteSearch())->toBeFalse();
});

test('getSearchResults falls back to the relationship search', function () {
    $field = makeRemoteBelongsToSelect();
    BtsCompany::create(['name' => 'Acme']);
    BtsCompany::create(['name' => 'Globex']);

    expect($field->getSearchResults('Ac'))->toBe([1 => 'Acme']);
});

test('an explicit getSearchResultsUsing callback wins over the relationship search', function () {
    $field = makeRemoteBelongsToSelect()->getSearchResultsUsing(fn (string $search) => ['x' => 'X-'.$search]);

    expect($field->getSearchResults('foo'))->toBe(['x' => 'X-foo']);
});

test('getOptionLabel resolves a remote selection with a single keyed lookup', function () {
    $field = makeRemoteBelongsToSelect();
    $company = BtsCompany::create(['name' => 'Acme']);

    // Remote mode ships no option list…
    expect($field->getOptions())->toBe([])
        // …but the selected value still resolves its label.
        ->and($field->getOptionLabel($company->id))->toBe('Acme')
        ->and($field->getOptionLabel(999))->toBeNull();
});

test('remote relationship select renders the remote combobox with the seeded selection label', function () {
    $field = makeRemoteBelongsToSelect();
    BtsCompany::create(['name' => 'Acme']);

    $html = renderBelongsToSelect($field);

    expect($html)
        ->toContain('searchSelectOptions')
        ->toContain('remote: true');
});

test('non-remote relationship select renders without remote wiring', function () {
    $field = BelongsToSelect::make('company_id')->options(['1' => 'Acme']);

    expect(renderBelongsToSelect($field))->toContain('remote: false');
});
