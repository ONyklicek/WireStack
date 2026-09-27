<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use NyonCode\WireAdmin\WireAdminServiceProvider;
use NyonCode\WireCore\Core\Plugin\Hooks\NavigationBuildingPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Foundation\Enums\Hook;

/*
 * The filter over the menu (navigation-surfaces.md § 5a).
 *
 * What the server decides is whether the field is drawn and what it will say;
 * the hiding itself is the browser's and verify-nav-filter drives it.
 */
function nfMenu(int $entries, int $childrenOfFirst = 0): string
{
    app(PluginManager::class)->hook(Hook::NavigationBuilding, function (NavigationBuildingPayload $payload) use ($entries, $childrenOfFirst) {
        foreach (range(1, $entries) as $i) {
            $item = NavigationItem::make("Entry {$i}")->url("/e{$i}");

            if ($i === 1 && $childrenOfFirst > 0) {
                $item->children(array_map(fn (int $c) => NavigationItem::make("Child {$c}")->url("/c{$c}"), range(1, $childrenOfFirst)));
            }

            $payload->items["e{$i}"] = $item;
        }

        return $payload;
    });

    return Blade::render('<x-wire-admin::sidebar />');
}

it('draws no filter over a short menu', function () {
    expect(nfMenu(5))->not->toContain('data-testid="admin-nav-filter"');
});

it('draws it once the menu reaches the threshold, children counted', function () {
    config()->set('wire-admin.navigation.filter_threshold', 8);

    expect(nfMenu(5, 3))->toContain('data-testid="admin-nav-filter"')
        ->toContain('x-data="wireNavFilter"');
});

it('always and never mean what they say', function () {
    config()->set('wire-admin.navigation.filter', 'always');
    expect(nfMenu(1))->toContain('data-testid="admin-nav-filter"');

    config()->set('wire-admin.navigation.filter', 'never');
    expect(nfMenu(40))->not->toContain('data-testid="admin-nav-filter"');
});

it('keeps the field out of the rail', function () {
    config()->set('wire-admin.navigation.filter', 'always');

    expect(nfMenu(2))->toMatch('/<div data-rail-hide class="mb-4">\s*<label class="sr-only" for="wire-admin-nav-filter">/');
});

it('marks every row with its label, lower-cased, and a child as a child', function () {
    config()->set('wire-admin.navigation.filter', 'always');

    $html = nfMenu(2, 1);

    expect($html)->toContain('data-nav-label="entry 1"')
        ->toMatch('/data-nav-row\s+data-nav-child\s+data-nav-label="child 1"/')
        ->toContain('data-nav-group');
});

it('announces each count in the translation own plural', function () {
    config()->set('wire-admin.navigation.filter', 'always');
    app()->setLocale('cs');

    $html = html_entity_decode(nfMenu(5));

    expect($html)->toContain('Odpovídá jedna položka')
        ->toContain('Odpovídají 3 položky')
        ->toContain('Odpovídá 5 položek');
});

it('ships the controller in a bundle the layout already loads', function () {
    $bundle = WireAdminServiceProvider::ASSETS_PATH.'/wire-admin-navigation.js';

    expect(file_get_contents($bundle))->toContain('wireNavFilter');

    $this->get('/wire-admin/assets/navigation.js')->assertOk();
});
