<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Widgets\ListItem;

// ─── Construction ────────────────────────────────────────────────────────────

it('carries a title and nothing else by default', function () {
    $item = ListItem::make('Order #1042');

    expect($item->getTitle())->toBe('Order #1042')
        ->and($item->getDescription())->toBeNull()
        ->and($item->getMeta())->toBeNull()
        ->and($item->getIcon())->toBeNull()
        ->and($item->getUrl())->toBeNull()
        ->and($item->opensInNewTab())->toBeFalse();
});

it('carries a description, an aside and extra attributes', function () {
    $item = ListItem::make('Order #1042')
        ->description('Acme s.r.o.')
        ->meta('2 minutes ago')
        ->extraAttributes(['data-testid' => 'order-1042']);

    expect($item->getDescription())->toBe('Acme s.r.o.')
        ->and($item->getMeta())->toBe('2 minutes ago')
        ->and($item->getExtraAttributes())->toBe(['data-testid' => 'order-1042']);
});

// ─── Linking ─────────────────────────────────────────────────────────────────

it('remembers a url and whether it opens elsewhere', function () {
    $item = ListItem::make('Order #1042')->url('/orders/1042')->newTab();

    expect($item->getUrl())->toBe('/orders/1042')
        ->and($item->opensInNewTab())->toBeTrue()
        ->and(ListItem::make('a')->newTab(false)->opensInNewTab())->toBeFalse();
});

// ─── Presentation ────────────────────────────────────────────────────────────

it('accepts an Icon case as well as a name', function () {
    expect(ListItem::make('a')->icon('outline:inbox')->getIcon())->toBe('outline:inbox')
        ->and(ListItem::make('a')->icon(Icon::inbox)->getIcon())->toBe(Icon::inbox->value())
        ->and(ListItem::make('a')->icon(null)->getIcon())->toBeNull();
});

it('tints the icon disc from the canonical palette and never verbatim', function () {
    expect(ListItem::make('a')->color('danger')->getIconBackgroundClass())->toContain('bg-')
        ->and(ListItem::make('a')->color('danger')->getIconColorClass())->toContain('text-')
        ->and(ListItem::make('a')->color('not-a-colour')->getIconBackgroundClass())
        ->not->toContain('not-a-colour')
        ->and(ListItem::make('a')->color('not-a-colour')->getIconColorClass())
        ->not->toContain('not-a-colour');
});

it('falls back to primary on both halves of the disc', function () {
    $bare = ListItem::make('a');
    $primary = ListItem::make('b')->color('primary');

    expect($bare->getIconBackgroundClass())->toBe($primary->getIconBackgroundClass())
        ->and($bare->getIconColorClass())->toBe($primary->getIconColorClass());
});
