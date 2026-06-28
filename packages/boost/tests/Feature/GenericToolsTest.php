<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use NyonCode\WireBoost\Mcp\Tools\BrowserLogs;
use NyonCode\WireBoost\Mcp\Tools\DatabaseConnections;
use NyonCode\WireBoost\Mcp\Tools\DatabaseQuery;
use NyonCode\WireBoost\Mcp\Tools\DatabaseSchema;
use NyonCode\WireBoost\Mcp\Tools\GetAbsoluteUrl;
use NyonCode\WireBoost\Mcp\Tools\LastError;
use NyonCode\WireBoost\Mcp\Tools\ListArtisanCommands;
use NyonCode\WireBoost\Mcp\Tools\ListRoutes;
use NyonCode\WireBoost\Mcp\Tools\ReadLogEntries;
use NyonCode\WireBoost\Mcp\Tools\Tinker;
use NyonCode\WireBoost\Mcp\WireBoostServer;

it('lists database connections', function () {
    WireBoostServer::tool(DatabaseConnections::class)
        ->assertOk()
        ->assertSee('sqlite')
        ->assertSee('testing');
});

it('reads the database schema', function () {
    Schema::create('widgets', function ($table) {
        $table->id();
        $table->string('label')->nullable();
    });

    WireBoostServer::tool(DatabaseSchema::class)
        ->assertOk()
        ->assertSee('widgets')
        ->assertSee('label');

    WireBoostServer::tool(DatabaseSchema::class, ['table' => 'widgets'])
        ->assertOk()
        ->assertSee('widgets');

    WireBoostServer::tool(DatabaseSchema::class, ['connection' => 'testing'])
        ->assertOk()
        ->assertSee('widgets');

    WireBoostServer::tool(DatabaseSchema::class, ['connection' => 'bogus'])
        ->assertOk()
        ->assertSee('error');
});

it('guards the database-query tool behind a config flag', function () {
    config()->set('wire-boost.tools.database_query', false);

    WireBoostServer::tool(DatabaseQuery::class, ['query' => 'select 1'])
        ->assertOk()
        ->assertSee('disabled');
});

it('runs read-only queries when enabled', function () {
    config()->set('wire-boost.tools.database_query', true);

    WireBoostServer::tool(DatabaseQuery::class, ['query' => 'select 1 as n'])
        ->assertOk()
        ->assertSee('"n"');

    WireBoostServer::tool(DatabaseQuery::class, ['query' => 'with x as (select 1 as n) select * from x'])
        ->assertOk();
});

it('blocks write queries', function () {
    config()->set('wire-boost.tools.database_query', true);

    WireBoostServer::tool(DatabaseQuery::class, ['query' => 'delete from widgets'])
        ->assertOk()
        ->assertSee('read-only');

    WireBoostServer::tool(DatabaseQuery::class, ['query' => 'with t as (select 1) delete from widgets'])
        ->assertOk()
        ->assertSee('read-only');

    WireBoostServer::tool(DatabaseQuery::class, ['query' => 'select * from nope_missing'])
        ->assertOk();
});

it('converts a relative path to an absolute url', function () {
    WireBoostServer::tool(GetAbsoluteUrl::class, ['path' => '/users'])
        ->assertOk()
        ->assertSee('/users');
});

it('reads log entries and the last error', function () {
    $log = sys_get_temp_dir().'/wire-boost-'.uniqid().'.log';
    file_put_contents($log, "[2026-06-27] testing.INFO: hello\n[2026-06-27] testing.ERROR: kaboom\n");
    config()->set('logging.channels.single.path', $log);

    WireBoostServer::tool(ReadLogEntries::class, ['entries' => 5])
        ->assertOk()
        ->assertSee('kaboom');

    WireBoostServer::tool(LastError::class)
        ->assertOk()
        ->assertSee('kaboom');

    unlink($log);
});

it('reports when there is no error and no log file', function () {
    $log = sys_get_temp_dir().'/wire-boost-'.uniqid().'.log';
    file_put_contents($log, "[2026-06-27] testing.INFO: all good\n");
    config()->set('logging.channels.single.path', $log);

    WireBoostServer::tool(LastError::class)
        ->assertOk()
        ->assertSee('No error entries');

    unlink($log);

    config()->set('logging.channels.single.path', $log);
    WireBoostServer::tool(ReadLogEntries::class)
        ->assertOk()
        ->assertSee('"count":0');
});

it('lists artisan commands with a filter', function () {
    WireBoostServer::tool(ListArtisanCommands::class)
        ->assertOk()
        ->assertSee('wire-boost:mcp');

    WireBoostServer::tool(ListArtisanCommands::class, ['filter' => 'wire-boost'])
        ->assertOk()
        ->assertSee('wire-boost:install');
});

it('lists routes with a filter', function () {
    app('router')->get('boost-test-route', fn () => 'ok')->name('boost.test');

    WireBoostServer::tool(ListRoutes::class)
        ->assertOk()
        ->assertSee('boost-test-route');

    WireBoostServer::tool(ListRoutes::class, ['filter' => 'boost-test-route'])
        ->assertOk()
        ->assertSee('boost.test');
});

it('guards the tinker tool behind a config flag', function () {
    config()->set('wire-boost.tools.tinker', false);

    WireBoostServer::tool(Tinker::class, ['code' => '1 + 1'])
        ->assertOk()
        ->assertSee('disabled');
});

it('evaluates code when tinker is enabled', function () {
    config()->set('wire-boost.tools.tinker', true);

    WireBoostServer::tool(Tinker::class, ['code' => '1 + 1'])
        ->assertOk()
        ->assertSee('2');

    WireBoostServer::tool(Tinker::class, ['code' => '"hello"'])
        ->assertOk()
        ->assertSee('hello');

    WireBoostServer::tool(Tinker::class, ['code' => '[1, 2, 3]'])
        ->assertOk()
        ->assertSee('[1,2,3]');

    WireBoostServer::tool(Tinker::class, ['code' => 'null'])
        ->assertOk()
        ->assertSee('NULL');

    WireBoostServer::tool(Tinker::class, ['code' => 'print "out"'])
        ->assertOk()
        ->assertSee('out');
});

it('rejects empty or invalid tinker code', function () {
    config()->set('wire-boost.tools.tinker', true);

    WireBoostServer::tool(Tinker::class, ['code' => '  '])
        ->assertOk()
        ->assertSee('No code');

    WireBoostServer::tool(Tinker::class, ['code' => 'this is not valid php $$'])
        ->assertOk()
        ->assertSee('error');
});

it('reads browser logs', function () {
    config()->set('wire-boost.tools.browser_logs', false);
    WireBoostServer::tool(BrowserLogs::class)
        ->assertOk()
        ->assertSee('disabled');

    config()->set('wire-boost.tools.browser_logs', true);
    $path = sys_get_temp_dir().'/wire-boost-browser-'.uniqid().'.log';
    config()->set('wire-boost.browser_logs.path', $path);

    WireBoostServer::tool(BrowserLogs::class)
        ->assertOk()
        ->assertSee('No browser log');

    file_put_contents($path, "console.error: oops\nconsole.warn: careful\n");

    WireBoostServer::tool(BrowserLogs::class, ['entries' => 5])
        ->assertOk()
        ->assertSee('oops');

    unlink($path);
});
