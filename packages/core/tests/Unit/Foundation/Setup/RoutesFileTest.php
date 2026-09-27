<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Setup\RoutesFile;

/*
 * The application's routes/web.php, as something a setup step adds one macro
 * call to — once, and never over anything.
 */

function routesAt(string $contents): RoutesFile
{
    $path = sys_get_temp_dir().'/wire-routes-'.getmypid().'-'.uniqid().'.php';
    file_put_contents($path, $contents);

    register_shutdown_function(static fn () => @unlink($path));

    return new RoutesFile($path);
}

it('knows a macro the file already calls, wherever the application put it', function () {
    $routes = routesAt("<?php\n\nRoute::middleware(['web'])->group(function () {\n    Route::wireResources(only: ['orders']);\n});\n");

    expect($routes->exists())->toBeTrue()
        ->and($routes->calls('wireResources'))->toBeTrue()
        ->and($routes->calls('wireTenants'))->toBeFalse();
});

it('appends, and then knows it did', function () {
    $routes = routesAt("<?php\n");

    expect($routes->append("\nRoute::wireTenants();\n"))->toBeTrue()
        ->and($routes->calls('wireTenants'))->toBeTrue();
});

it('writes nothing where there is no route file', function () {
    $routes = new RoutesFile(sys_get_temp_dir().'/wire-routes-absent-'.uniqid().'.php');

    expect($routes->exists())->toBeFalse()
        ->and($routes->calls('wireResources'))->toBeFalse()
        ->and($routes->append('Route::wireResources();'))->toBeFalse();
});

it('reads the application s own file by default', function () {
    expect(RoutesFile::forApplication())->toEqual(new RoutesFile(base_path('routes/web.php')));
});

it('knows a route group the file places, however it is written', function () {
    $routes = routesAt("<?php\n\nRoute::prefix('admin')->group(fn () => Route::wire(\"panel\", zone: 'admin'));\nRoute::wire( 'tenants' );\n");

    expect($routes->wires('panel'))->toBeTrue()
        ->and($routes->wires('tenants'))->toBeTrue()
        ->and($routes->wires('auth-codes'))->toBeFalse()
        ->and((new RoutesFile(sys_get_temp_dir().'/wire-routes-absent-'.uniqid().'.php'))->wires('panel'))->toBeFalse();
});
