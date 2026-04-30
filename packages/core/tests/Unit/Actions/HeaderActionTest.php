<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\HeaderAction;

it('can be created', function () {
    $action = HeaderAction::make('create');

    expect($action)->toBeInstanceOf(HeaderAction::class)
        ->and($action->getName())->toBe('create');
});

// ─── URL ────────────────────────────────────────────────────────────────────

it('can set url', function () {
    $action = HeaderAction::make('create')->url('/users/create');

    expect($action->getUrl())->toBe('/users/create');
});

it('can open url in new tab', function () {
    $action = HeaderAction::make('docs')->url('/docs', openInNewTab: true);

    expect($action->shouldOpenUrlInNewTab())->toBeTrue();
});

// ─── Badge ──────────────────────────────────────────────────────────────────

it('has no badge by default', function () {
    expect(HeaderAction::make('test')->hasBadge())->toBeFalse();
});

it('can set static badge count', function () {
    $action = HeaderAction::make('inbox')->badge(5);

    expect($action->hasBadge())->toBeTrue()
        ->and($action->getBadgeCount())->toBe(5);
});

it('can set dynamic badge count via closure', function () {
    $action = HeaderAction::make('inbox')->badge(fn () => 42);

    expect($action->getBadgeCount())->toBe(42);
});

it('has no badge when count is zero', function () {
    expect(HeaderAction::make('test')->badge(0)->hasBadge())->toBeFalse();
});

it('has no badge when count is null', function () {
    expect(HeaderAction::make('test')->badge(null)->hasBadge())->toBeFalse();
});

it('has default danger badge color', function () {
    expect(HeaderAction::make('test')->getBadgeColor())->toBe('danger');
});

it('can set custom badge color', function () {
    $action = HeaderAction::make('test')->badge(3)->badgeColor('success');

    expect($action->getBadgeColor())->toBe('success');
});

it('caps badge display at 99+', function () {
    $action = HeaderAction::make('inbox')->badge(150);
    $html = $action->getBadgeHtml();

    expect($html)->toContain('99+');
});

it('returns empty badge html when no badge', function () {
    expect(HeaderAction::make('test')->getBadgeHtml())->toBe('');
});
