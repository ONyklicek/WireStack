<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Query\AggregateClause;
use NyonCode\WireCore\Core\Query\Pipes\ApplyAggregates;
use NyonCode\WireCore\Core\Query\QueryPlan;
use NyonCode\WireCore\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::create('agg_test_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('agg_test_orders', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('agg_test_user_id');
        $table->decimal('total', 10, 2);
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('agg_test_orders');
    Schema::dropIfExists('agg_test_users');
});

it('applies withCount aggregate', function () {
    $model = new AggTestUser;

    $plan = new QueryPlan(
        aggregates: [
            new AggregateClause('orders', 'count', alias: 'orders_count'),
        ],
    );

    $builder = $model->newQuery();
    $pipe = new ApplyAggregates;
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    expect($sql)->toContain('orders_count');
});

it('applies withSum aggregate', function () {
    $model = new AggTestUser;

    $plan = new QueryPlan(
        aggregates: [
            new AggregateClause('orders', 'sum', column: 'total', alias: 'orders_sum_total'),
        ],
    );

    $builder = $model->newQuery();
    $pipe = new ApplyAggregates;
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    expect($sql)->toContain('orders_sum_total');
});

it('skips when no aggregates in plan', function () {
    $model = new class extends Model
    {
        protected $table = 'agg_test_users';
    };

    $plan = new QueryPlan;
    $builder = $model->newQuery();
    $pipe = new ApplyAggregates;
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    expect($sql)->toBe('select * from "agg_test_users"');
});

// Concrete model classes needed for Eloquent relations
class AggTestUser extends Model
{
    protected $table = 'agg_test_users';

    public function orders(): HasMany
    {
        return $this->hasMany(AggTestOrder::class, 'agg_test_user_id');
    }
}

class AggTestOrder extends Model
{
    protected $table = 'agg_test_orders';
}
