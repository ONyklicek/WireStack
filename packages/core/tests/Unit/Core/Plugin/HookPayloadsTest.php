<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use NyonCode\WireCore\Core\Actions\ActionContext;
use NyonCode\WireCore\Core\Actions\ActionResult;
use NyonCode\WireCore\Core\Plugin\Hooks\ActionExecutedPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\ActionExecutingPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\FormSavedPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\FormSavingPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\TableConfiguringPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\TableQueriedPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\TableQueryingPayload;
use NyonCode\WireCore\Core\Query\QueryPlan;
use NyonCode\WireForms\Forms\Config\FormConfig;

it('serializes table configuring payloads', function () {
    $table = new stdClass;
    $columns = [(object) ['name' => 'email']];
    $filters = [(object) ['name' => 'status']];

    $payload = new TableConfiguringPayload($table, $columns, $filters);

    expect($payload->toArray())->toBe([
        'table' => $table,
        'columns' => $columns,
        'filters' => $filters,
    ]);
});

it('serializes table querying payloads', function () {
    // No sort override in here, and not by omission: this payload is built after
    // the plan is, so the override goes on the array hook that runs before the
    // planner. The two properties that used to sit here were filled by nothing
    // and read by nothing — removed in 2.0.
    $table = new stdClass;
    $plan = new QueryPlan;
    $query = Mockery::mock(Builder::class);

    $payload = new TableQueryingPayload(
        table: $table,
        plan: $plan,
        query: $query,
    );

    expect($payload->toArray())->toBe([
        'table' => $table,
        'plan' => $plan,
        'query' => $query,
    ]);
});

it('serializes table queried payloads', function () {
    $table = new stdClass;
    $query = Mockery::mock(Builder::class);
    $plan = new QueryPlan;

    $payload = new TableQueriedPayload($table, $query, $plan);

    expect($payload->toArray())->toBe([
        'table' => $table,
        'query' => $query,
        'plan' => $plan,
    ]);
});

it('serializes form saving payloads', function () {
    $config = new FormConfig;
    $data = ['name' => 'Jane'];

    $payload = new FormSavingPayload($config, $data);

    expect($payload->toArray())->toBe([
        'config' => $config,
        'data' => $data,
    ]);
});

it('serializes form saved payloads', function () {
    $config = new FormConfig;
    $record = (object) ['id' => 1];

    $payload = new FormSavedPayload($config, $record);

    expect($payload->toArray())->toBe([
        'config' => $config,
        'record' => $record,
    ]);
});

it('serializes action executing payloads', function () {
    $context = new ActionContext(actionName: 'archive');
    $component = new stdClass;

    $payload = new ActionExecutingPayload(
        actionName: 'archive',
        context: $context,
        actionType: 'row',
        component: $component,
    );

    expect($payload->toArray())->toBe([
        'actionName' => 'archive',
        'context' => $context,
        'actionType' => 'row',
        'component' => $component,
    ]);
});

it('serializes action executed payloads', function () {
    $context = new ActionContext(actionName: 'archive');
    $result = ActionResult::success();
    $component = new stdClass;

    $payload = new ActionExecutedPayload(
        actionName: 'archive',
        context: $context,
        result: $result,
        actionType: 'row',
        component: $component,
    );

    expect($payload->toArray())->toBe([
        'actionName' => 'archive',
        'context' => $context,
        'result' => $result,
        'actionType' => 'row',
        'component' => $component,
    ]);
});
