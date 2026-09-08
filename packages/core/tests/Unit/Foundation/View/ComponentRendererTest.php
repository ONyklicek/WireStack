<?php

declare(strict_types=1);

use Illuminate\Support\HtmlString;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Core\Resources\View\Breadcrumbs;
use NyonCode\WireCore\Foundation\View\Button;
use NyonCode\WireCore\Foundation\View\ComponentRenderer;
use NyonCode\WireCore\Foundation\View\FileThumb;

/*
 * Rule 5's escape hatch for the view components.
 *
 * `<x-wire::button>` and friends are the consumer-facing API; the framework's
 * own partials must draw without them, and used to reach for the tag anyway
 * because the alternative was hand-written utilities. This renders the same
 * class through the same view, so what is asserted here is the two things Blade
 * would otherwise have supplied — the slot and the attribute bag — plus the part
 * of the contract a naive renderer drops.
 */

it('renders a component with its slot and merged attributes', function () {
    $html = ComponentRenderer::render(
        new Button(size: 'md', type: 'submit'),
        'Save',
        ['class' => 'w-full', 'data-testid' => 'save'],
    );

    expect($html)->toContain('type="submit"')
        ->toContain('Save')
        ->toContain('data-testid="save"')
        // The caller's class is merged onto the component's own, not replacing it.
        ->toContain('w-full')
        ->toContain('inline-flex items-center');
});

it('escapes a plain-string slot and takes markup as it is', function () {
    expect(ComponentRenderer::render(new Button, '<b>x</b>'))->toContain('&lt;b&gt;x&lt;/b&gt;')
        ->and(ComponentRenderer::render(new Button, new HtmlString('<b>x</b>')))
        ->toContain('<b>x</b>');
});

it('honours shouldRender, which is what keeps an empty nav off a list page', function () {
    // Breadcrumbs draws nothing for a trail of one: that is the page saying "you
    // are here", which its own heading already says.
    $one = ComponentRenderer::render(new Breadcrumbs([NavigationItem::make('Invoices')]));
    $two = ComponentRenderer::render(new Breadcrumbs([
        NavigationItem::make('Invoices')->url('/invoices'),
        NavigationItem::make('Invoice detail'),
    ]));

    expect($one)->toBe('')
        ->and($two)->toContain('data-testid="breadcrumbs"')
        ->toContain('Invoice detail');
});

it('keeps the data a component passes its own view', function () {
    // FileThumb resolves the wordmark and the colour in its constructor; none of
    // that reaches the view unless the component's own view data survives the
    // merge.
    $html = ComponentRenderer::render(new FileThumb(name: 'prices.xlsx', size: 'md'));

    expect($html)->toContain('XLSX');
});
