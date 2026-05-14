<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Query\Pipes\ApplyScopes;
use NyonCode\WireCore\Core\Query\QueryPlan;
use NyonCode\WireCore\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::create('scope_test_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('status')->default('active');
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('scope_test_users');
});

it('applies model scopes', function () {
    $model = new class extends Model
    {
        protected $table = 'scope_test_users';

        public function scopeActive(Builder $query): Builder
        {
            return $query->where('status', 'active');
        }
    };

    $plan = new QueryPlan(scopes: ['active']);

    $builder = $model->newQuery();
    $pipe = new ApplyScopes;
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    expect($sql)->toContain("'active'");
});

it('skips when no scopes in plan', function () {
    $model = new class extends Model
    {
        protected $table = 'scope_test_users';
    };

    $plan = new QueryPlan;
    $builder = $model->newQuery();
    $pipe = new ApplyScopes;
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    expect($sql)->not->toContain('where');
});
