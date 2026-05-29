<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireForms\Components\BelongsToSelect;

class BelongsToSelectRelationshipCompany extends Model
{
    protected $table = 'belongs_to_select_relationship_companies';

    protected $guarded = [];
}

class BelongsToSelectRelationshipUser extends Model
{
    protected $table = 'belongs_to_select_relationship_users';

    protected $guarded = [];

    public function company(): BelongsTo
    {
        return $this->belongsTo(BelongsToSelectRelationshipCompany::class, 'company_id');
    }
}

beforeEach(function () {
    Schema::create('belongs_to_select_relationship_companies', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->boolean('active')->default(true);
        $table->timestamps();
    });

    Schema::create('belongs_to_select_relationship_users', function (Blueprint $table) {
        $table->id();
        $table->foreignId('company_id')->nullable();
        $table->timestamps();
    });

    BelongsToSelectRelationshipCompany::create(['id' => 1, 'name' => 'Acme', 'active' => true]);
    BelongsToSelectRelationshipCompany::create(['id' => 2, 'name' => 'Globex', 'active' => false]);
    BelongsToSelectRelationshipCompany::create(['id' => 3, 'name' => 'Active Labs', 'active' => true]);
});

afterEach(function () {
    Schema::dropIfExists('belongs_to_select_relationship_users');
    Schema::dropIfExists('belongs_to_select_relationship_companies');
});

it('loads preloaded options from the related model', function () {
    $record = new BelongsToSelectRelationshipUser;

    $field = BelongsToSelect::make('company_id')
        ->relationship('company', 'name')
        ->record($record)
        ->preload();

    expect($field->getOptions())->toBe([
        1 => 'Acme',
        2 => 'Globex',
        3 => 'Active Labs',
    ]);
});

it('searches relationship options and applies query modifier', function () {
    $record = new BelongsToSelectRelationshipUser;

    $field = BelongsToSelect::make('company_id')
        ->relationship('company', 'name')
        ->record($record)
        ->modifyOptionsQueryUsing(fn ($query) => $query->where('active', true));

    expect($field->searchOptions('Acme'))->toBe([
        1 => 'Acme',
    ]);
});

it('creates related options using the resolved related model', function () {
    $record = new BelongsToSelectRelationshipUser;

    $field = BelongsToSelect::make('company_id')
        ->relationship('company', 'name')
        ->record($record);

    $created = $field->createOption(['name' => 'New Company', 'active' => true]);

    expect($created)->toBeInstanceOf(BelongsToSelectRelationshipCompany::class)
        ->and(BelongsToSelectRelationshipCompany::query()->where('name', 'New Company')->exists())->toBeTrue();
});
