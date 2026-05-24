<?php

declare(strict_types=1);

use Illuminate\Contracts\View\View;
use Illuminate\Support\ViewErrorBag;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Config\FormConfig;
use NyonCode\WireForms\Forms\Runtime\FormRuntime;
use NyonCode\WireForms\Forms\Runtime\StateManager;
use NyonCode\WireForms\Rendering\FormRenderer;

test('render returns a View instance', function () {
    $config = new FormConfig(
        schema: [TextInput::make('name')],
    );

    $stateManager = new StateManager;
    $runtime = new FormRuntime($config, $stateManager);
    $renderer = new FormRenderer($config, $runtime);

    $view = $renderer->render();

    expect($view)->toBeInstanceOf(View::class);
});

test('render passes components and statePath to view', function () {
    $schema = [TextInput::make('name'), TextInput::make('email')];

    $config = new FormConfig(
        schema: $schema,
        statePath: 'data',
    );

    $stateManager = new StateManager;
    $runtime = new FormRuntime($config, $stateManager);
    $renderer = new FormRenderer($config, $runtime);

    $view = $renderer->render();
    $data = $view->getData();

    expect($data['components'])->toBe($schema)
        ->and($data['statePath'])->toBe('data');
});

test('render passes null statePath when not set', function () {
    $config = new FormConfig(
        schema: [TextInput::make('name')],
    );

    $stateManager = new StateManager;
    $runtime = new FormRuntime($config, $stateManager);
    $renderer = new FormRenderer($config, $runtime);

    $view = $renderer->render();

    expect($view->getData()['statePath'])->toBeNull();
});

test('toHtml returns string', function () {
    // Share $errors variable as Laravel middleware normally does
    view()->share('errors', new ViewErrorBag);

    $config = new FormConfig(
        schema: [TextInput::make('name')],
    );

    $stateManager = new StateManager;
    $runtime = new FormRuntime($config, $stateManager);
    $renderer = new FormRenderer($config, $runtime);

    $html = $renderer->toHtml();

    expect($html)->toBeString();
});

test('render calls prepare on runtime', function () {
    $input = TextInput::make('name');

    $config = new FormConfig(
        schema: [$input],
        statePath: 'form_data',
    );

    $stateManager = new StateManager;
    $runtime = new FormRuntime($config, $stateManager);
    $renderer = new FormRenderer($config, $runtime);

    $renderer->render();

    // After render, prepare should have been called, setting statePath on components
    expect($input->getStatePath())->toBe('form_data.name');
});
