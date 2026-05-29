<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Query\Pipes\ApplySoftDeletes;
use NyonCode\WireCore\Core\Query\QueryPlan;

beforeEach(function () {
    Schema::create('sd_test_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->softDeletes();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('sd_test_users');
});

it('removes soft delete scope when withSoftDeletes is true', function () {
    $model = new class extends Model
    {
        use SoftDeletes;

        protected $table = 'sd_test_users';
    };

    $plan = new QueryPlan(withSoftDeletes: true);

    $builder = $model->newQuery();
    $pipe = new ApplySoftDeletes;
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    // Without soft delete scope, query should NOT have "deleted_at is null"
    expect($sql)->not->toContain('deleted_at');
});

it('keeps soft delete scope when withSoftDeletes is false', function () {
    $model = new class extends Model
    {
        use SoftDeletes;

        protected $table = 'sd_test_users';
    };

    $plan = new QueryPlan(withSoftDeletes: false);

    $builder = $model->newQuery();
    $pipe = new ApplySoftDeletes;
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    // Should retain the soft delete where clause
    expect($sql)->toContain('deleted_at');
});
