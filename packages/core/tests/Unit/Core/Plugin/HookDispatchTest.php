<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use NyonCode\WireCore\Core\Plugin\HookDispatch;
use NyonCode\WireCore\Core\Plugin\Hooks\CellUpdatingPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\ExportConfiguringPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\FormFillingPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\ImportConfiguringPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\InfolistConfiguringPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\NavigationBuildingPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\PageMountingPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\SearchQueryingPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\WidgetConfiguringPayload;
use NyonCode\WireCore\Core\Plugin\HookTarget;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Foundation\Enums\Hook;

/*
 * The guard eight dispatch sites share.
 *
 * Two sites wrote it by hand and six more were about to, which is the point at
 * which "check the container, check somebody is listening, only then build the
 * payload" stops being three lines and becomes a rule with nowhere to live. The
 * property worth pinning is the third clause: the payload closure must not run
 * when nothing would receive it, because building one means reading a table's
 * columns or a dashboard's widgets.
 */

it('builds no payload when nothing is listening', function () {
    $built = false;

    $result = HookDispatch::typed(Hook::WidgetConfiguring, function () use (&$built) {
        $built = true;

        return new stdClass;
    });

    expect($result)->toBeNull()
        ->and($built)->toBeFalse();
});

it('builds the payload and returns what the callbacks left', function () {
    app(PluginManager::class)->hook(
        Hook::WidgetConfiguring,
        function (WidgetConfiguringPayload $payload): WidgetConfiguringPayload {
            $payload->widgets[] = 'added';

            return $payload;
        },
    );

    $result = HookDispatch::typed(Hook::WidgetConfiguring, fn () => new WidgetConfiguringPayload(
        host: new stdClass,
        widgets: ['declared'],
    ));

    expect($result)->toBeInstanceOf(WidgetConfiguringPayload::class)
        ->and($result->widgets)->toBe(['declared', 'added']);
});

it('answers null when the application has no container binding at all', function () {
    // A package used without its service provider, and every test that builds a
    // form or a table by hand. `app(PluginManager::class)` there would throw
    // rather than return an empty hook list.
    $original = Container::getInstance();

    Container::setInstance(new Container);

    try {
        expect(HookDispatch::typed(Hook::FormConfiguring, fn () => new stdClass))->toBeNull();
    } finally {
        Container::setInstance($original);
    }
});

it('accepts a bare string as readily as the enum', function () {
    app(PluginManager::class)->hook(
        'navigation.building',
        fn (NavigationBuildingPayload $payload): NavigationBuildingPayload => $payload,
    );

    expect(HookDispatch::typed(Hook::NavigationBuilding, fn () => new NavigationBuildingPayload(items: [])))
        ->toBeInstanceOf(NavigationBuildingPayload::class);
});

// ─── Payload shapes ──────────────────────────────────────────────────────────

/*
 * Every payload says where it came from and can flatten itself, because the six
 * that predate them do. A callback that logs a payload should not have to learn
 * which generation of hook it is looking at.
 */

it('carries a target and flattens to an array, on every new payload', function () {
    $target = new HookTarget(surface: 'test', key: 'invoices');
    $host = new stdClass;
    $query = new stdClass;

    $payloads = [
        new InfolistConfiguringPayload(infolist: $host, schema: ['entry'], target: $target),
        new WidgetConfiguringPayload(host: $host, widgets: ['widget'], target: $target),
        new ExportConfiguringPayload(export: $host, query: $query, columns: ['column'], target: $target),
        new NavigationBuildingPayload(items: ['orders' => 'item'], zone: 'admin', target: $target),
        new PageMountingPayload(page: $host, title: 'Orders', zone: 'admin', target: $target),
        new SearchQueryingPayload(query: $query, term: 'inv', resource: 'OrderResource', target: $target),
        new ImportConfiguringPayload(import: $host, columns: ['column'], path: '/tmp/rows.csv', target: $target),
        new CellUpdatingPayload(
            column: $host,
            columnName: 'total',
            record: $query,
            value: 10,
            oldValue: 5,
            target: $target,
        ),
        new FormFillingPayload(form: $host, data: ['number' => 'INV-1'], target: $target),
    ];

    foreach ($payloads as $payload) {
        expect($payload->hookTarget())->toBe($target)
            ->and($payload->toArray())->toBeArray()->not->toBeEmpty();
    }

    expect($payloads[3]->toArray())->toBe(['items' => ['orders' => 'item'], 'zone' => 'admin'])
        ->and($payloads[4]->toArray())->toBe(['page' => $host, 'title' => 'Orders', 'zone' => 'admin'])
        ->and($payloads[5]->toArray())->toBe(['query' => $query, 'term' => 'inv', 'resource' => 'OrderResource'])
        ->and($payloads[0]->toArray())->toBe(['infolist' => $host, 'schema' => ['entry']])
        ->and($payloads[1]->toArray())->toBe(['host' => $host, 'widgets' => ['widget']])
        ->and($payloads[2]->toArray())->toBe(['export' => $host, 'query' => $query, 'columns' => ['column']])
        ->and($payloads[6]->toArray())->toBe(['import' => $host, 'columns' => ['column'], 'path' => '/tmp/rows.csv'])
        ->and($payloads[7]->toArray())->toBe([
            'column' => $host,
            'columnName' => 'total',
            'record' => $query,
            'value' => 10,
            'oldValue' => 5,
            'refusal' => null,
        ])
        ->and($payloads[8]->toArray())->toBe(['form' => $host, 'data' => ['number' => 'INV-1']]);
});

it('hands back the manager only when a legacy name has a listener', function () {
    // The legacy half of the same guard. `hasHook()` is the part the four
    // double-dispatched sites never had: they built both payloads whenever a
    // manager was bound, which on `table.configuring` is once per table per
    // render in an application that registered nothing.
    expect(HookDispatch::manager(Hook::TableConfiguring))->toBeNull();

    app(PluginManager::class)->hook(Hook::TableConfiguring, fn (array $payload): array => $payload);

    expect(HookDispatch::manager(Hook::TableConfiguring))->toBeInstanceOf(PluginManager::class);
});

it('leaves a payload targetless when nothing named a host', function () {
    expect((new NavigationBuildingPayload(items: []))->hookTarget())->toBeNull();
});
