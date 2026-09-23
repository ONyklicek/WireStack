<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use NyonCode\WireCore\Foundation\View\ElementHook;

it('renders a name as its attribute', function () {
    expect(ElementHook::render('admin-sidebar'))->toBe(' data-wire="admin-sidebar"');
});

it('drops a name that is not kebab-case', function () {
    // A hook nobody can predict the spelling of is not a contract. Dropping it
    // is louder than escaping it: the stylesheet matches nothing either way, and
    // this way the markup says so.
    expect(ElementHook::render('Admin Sidebar'))->toBe('')
        ->and(ElementHook::render('adminSidebar'))->toBe('')
        ->and(ElementHook::render('admin_sidebar'))->toBe('')
        ->and(ElementHook::render('-admin'))->toBe('')
        ->and(ElementHook::render(''))->toBe('');
});

it('refuses a name that could end the attribute', function () {
    expect(ElementHook::render('a" onclick="x'))->toBe('')
        ->and(ElementHook::render('a><script'))->toBe('');
});

it('takes the shapes the framework\'s own names are written in', function () {
    foreach (['admin-nav-badge-dot', 'table-search', 'form-field', 'a1', 'x-2-y'] as $name) {
        expect(ElementHook::render($name))->toBe(' data-wire="'.$name.'"');
    }
});

it('is what the directive compiles to', function () {
    // The directive is the only way this is called from a view, so the wiring is
    // worth pinning: a typo in the provider would leave every hook unrendered
    // and nothing else would fail.
    // The attribute carries its own leading space, so a tag keeps rendering
    // whether the name is usable or not; the source's own space makes two.
    expect(Blade::render("<div @wireEl('table-toolbar')></div>"))
        ->toBe('<div  data-wire="table-toolbar"></div>');
});

it('leaves the tag alone when the name is unusable', function () {
    expect(Blade::render('<div @wireEl($name)></div>', ['name' => 'Not A Name']))
        ->toBe('<div ></div>');
});

/**
 * The name shape became public when something other than the renderer needed to
 * ask about it: a tour step targets a hook rather than emitting one, and a
 * targeted name that is not a hook produces a selector matching nothing — which
 * a tour would then skip, indistinguishably from an element the page genuinely
 * does not have.
 *
 * Extracted rather than copied. Two regexes for one contract disagree at the
 * first edit, and the one that drifts is the copy nobody is looking at.
 */
it('answers whether a name is usable without rendering it', function () {
    expect(ElementHook::isValidName('table-search'))->toBeTrue()
        ->and(ElementHook::isValidName('a1'))->toBeTrue()
        ->and(ElementHook::isValidName('Table-Search'))->toBeFalse()
        ->and(ElementHook::isValidName('table_search'))->toBeFalse()
        ->and(ElementHook::isValidName('table--search'))->toBeFalse()
        ->and(ElementHook::isValidName(''))->toBeFalse();
});

it('agrees with what it renders', function () {
    foreach (['table-search', 'Table-Search', '', 'a><script', 'x-2-y'] as $name) {
        expect(ElementHook::isValidName($name))->toBe(ElementHook::render($name) !== '');
    }
});

it('builds the selector that matches what it wrote', function () {
    $rendered = ElementHook::render('table-search');

    expect(ElementHook::selector('table-search'))->toBe('[data-wire="table-search"]')
        ->and($rendered)->toContain(ElementHook::ATTRIBUTE.'="table-search"');
});
