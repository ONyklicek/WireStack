<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * Searching a relation column joins the relation, and a join can multiply rows.
 * Only singular, joinable relations are ever joined (RelationMetadata::isJoinable
 * excludes morph and to-many), so this pins down what that actually means.
 */

class DupCompany extends Model
{
    protected $table = 'dup_companies';

    protected $guarded = [];

    public $timestamps = false;
}

class DupProfile extends Model
{
    protected $table = 'dup_profiles';

    protected $guarded = [];

    public $timestamps = false;
}

class DupUser extends Model
{
    protected $table = 'dup_users';

    protected $guarded = [];

    public $timestamps = false;

    public function company(): BelongsTo
    {
        return $this->belongsTo(DupCompany::class, 'dup_company_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(DupProfile::class, 'dup_user_id');
    }
}

class BelongsToSearchHost extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(DupUser::class)
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('company.name')->searchable(),
            ])
            ->searchable()
            ->paginated(false);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class HasOneSearchHost extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(DupUser::class)
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('profile.nickname')->searchable(),
            ])
            ->searchable()
            ->paginated(false);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('dup_companies', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    Schema::create('dup_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->foreignId('dup_company_id');
    });

    Schema::create('dup_profiles', function (Blueprint $table) {
        $table->id();
        $table->foreignId('dup_user_id');
        $table->string('nickname');
    });

    $acme = DupCompany::create(['name' => 'Acme']);
    DupCompany::create(['name' => 'Other']);

    DupUser::create(['name' => 'Ada', 'dup_company_id' => $acme->id]);
    DupUser::create(['name' => 'Grace', 'dup_company_id' => $acme->id]);
});

afterEach(function () {
    Schema::dropIfExists('dup_profiles');
    Schema::dropIfExists('dup_users');
    Schema::dropIfExists('dup_companies');
});

it('does not duplicate a row when several rows share one parent', function () {
    // Two users, one company: a belongsTo join is many-to-one and cannot
    // multiply the base row, however many rows point at the same parent.
    $records = Livewire::test(BelongsToSearchHost::class)
        ->set('tableState.search', 'Acme')
        ->viewData('records');

    expect($records->pluck('name')->all())->toBe(['Ada', 'Grace']);
});

it('duplicates a row when a hasOne relation holds more than one child', function () {
    DupProfile::create(['dup_user_id' => 1, 'nickname' => 'countess']);
    DupProfile::create(['dup_user_id' => 1, 'nickname' => 'countess of lovelace']);

    $records = Livewire::test(HasOneSearchHost::class)
        ->set('tableState.search', 'countess')
        ->viewData('records');

    // Documented, not fixed: the relation says one child, the data has two.
    expect($records->pluck('name')->all())->toBe(['Ada', 'Ada']);
});
