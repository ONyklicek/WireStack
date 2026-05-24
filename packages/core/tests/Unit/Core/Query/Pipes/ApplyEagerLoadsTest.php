<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Query\Pipes\ApplyEagerLoads;
use NyonCode\WireCore\Core\Query\QueryPlan;

beforeEach(function () {
    Schema::create('eager_test_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('eager_test_posts', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id');
        $table->string('title');
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('eager_test_posts');
    Schema::dropIfExists('eager_test_users');
});

it('applies eager loads to builder', function () {
    $model = new class extends Model
    {
        protected $table = 'eager_test_users';

        public function posts(): HasMany
        {
            return $this->hasMany(self::class, 'user_id');
        }
    };

    $plan = new QueryPlan(eagerLoads: ['posts']);

    $builder = $model->newQuery();
    $pipe = new ApplyEagerLoads;
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $eagerLoads = $result->getEagerLoads();

    expect($eagerLoads)->toHaveKey('posts');
});

it('skips when no eager loads in plan', function () {
    $model = new class extends Model
    {
        protected $table = 'eager_test_users';
    };

    $plan = new QueryPlan;
    $builder = $model->newQuery();
    $pipe = new ApplyEagerLoads;
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    expect($result->getEagerLoads())->toBeEmpty();
});
