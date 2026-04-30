<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;

it('has no loading indicator by default', function () {
    $action = Action::make('test');

    expect($action->hasLoadingIndicator())->toBeFalse()
        ->and($action->getLoadingText())->toBeNull();
});

it('can enable loading indicator', function () {
    $action = Action::make('test')->loadingIndicator();

    expect($action->hasLoadingIndicator())->toBeTrue();
});

it('can set loading text', function () {
    $action = Action::make('test')->loadingIndicator('Saving...');

    expect($action->hasLoadingIndicator())->toBeTrue()
        ->and($action->getLoadingText())->toBe('Saving...');
});

it('can set debounce', function () {
    $action = Action::make('test')->debounce(500);

    expect($action->getDebounceMs())->toBe(500);
});

it('has no debounce by default', function () {
    expect(Action::make('test')->getDebounceMs())->toBeNull();
});

it('can set timeout', function () {
    $action = Action::make('test')->timeout(30);

    expect($action->getTimeoutSeconds())->toBe(30);
});

it('has no timeout by default', function () {
    expect(Action::make('test')->getTimeoutSeconds())->toBeNull();
});

it('generates wire click modifiers', function () {
    $action = Action::make('test');

    // Without debounce or timeout, modifiers should be empty or minimal
    $modifiers = $action->getWireClickModifiers();
    expect($modifiers)->toBeString();
});

it('generates wire click modifiers with debounce', function () {
    $action = Action::make('test')->debounce(300);

    $modifiers = $action->getWireClickModifiers();
    expect($modifiers)->toContain('300');
});

it('returns loading state data', function () {
    $action = Action::make('test')->loadingIndicator('Wait...');

    $data = $action->getLoadingStateData();

    expect($data)->toBeArray()
        ->and($data['showLoading'])->toBeTrue()
        ->and($data['loadingText'])->toBe('Wait...');
});
