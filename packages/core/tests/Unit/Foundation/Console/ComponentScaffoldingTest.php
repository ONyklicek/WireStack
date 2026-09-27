<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use NyonCode\WireCore\Foundation\Console\Support\ComponentBlueprint;
use NyonCode\WireCore\Foundation\Console\Support\PublishedStubs;

/*
 * The one rule every generator's templates follow, and the naming every
 * custom-component generator derives from what was typed.
 */
afterEach(function () {
    File::deleteDirectory(base_path('stubs'));
});

it('prefers the stub published under the package, then stubs/, then its own', function () {
    $stubs = new PublishedStubs('wire-demo', '/package/stubs/');

    expect($stubs->path('thing.stub'))->toBe('/package/stubs/thing.stub');

    File::ensureDirectoryExists(base_path('stubs/wire-demo'));
    File::put(base_path('stubs/thing.stub'), 'loose');

    expect($stubs->path('thing.stub'))->toBe(base_path('stubs/thing.stub'));

    File::put(base_path('stubs/wire-demo/thing.stub'), 'published');

    expect($stubs->path('thing.stub'))->toBe(base_path('stubs/wire-demo/thing.stub'));
});

it('derives the class, the base name and the view from what was typed', function () {
    $column = new ComponentBlueprint(new PublishedStubs('wire-demo', '/x'), 'Tables\Columns', 'Column', 'c.stub', 'v.stub', 'tables.columns');

    expect($column->className('money'))->toBe('MoneyColumn')
        ->and($column->className('MoneyColumn'))->toBe('MoneyColumn')
        ->and($column->className('Sub/unitPrice'))->toBe('UnitPriceColumn')
        ->and($column->baseName('UnitPriceColumn'))->toBe('UnitPrice')
        ->and($column->viewName('UnitPrice'))->toBe('tables.columns.unit-price')
        // A class named only the suffix keeps it, rather than becoming ''.
        ->and($column->baseName('Column'))->toBe('Column');

    $field = new ComponentBlueprint(new PublishedStubs('wire-demo', '/x'), 'Forms\Components', '', 'f.stub', 'v.stub', 'forms.components');
    $action = new ComponentBlueprint(new PublishedStubs('wire-demo', '/x'), 'Wire\Actions', 'Action', 'a.stub');

    expect($field->className('moneyInput'))->toBe('MoneyInput')
        ->and($field->viewName('MoneyInput'))->toBe('forms.components.money-input')
        ->and($action->viewName('Archive'))->toBeNull();
});
