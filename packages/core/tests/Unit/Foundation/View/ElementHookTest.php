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
