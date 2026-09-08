<?php

declare(strict_types=1);

use NyonCode\WireForms\Components\Display\Alert;
use NyonCode\WireForms\Components\Display\Html;
use NyonCode\WireForms\Components\Display\Placeholder;
use NyonCode\WireForms\Components\Display\ViewField;

// ─── Placeholder ───────────────────────────────────────────────

test('placeholder with content', function () {
    $field = Placeholder::make('info')->content('Some text');

    expect($field->getContent())->toBe('Some text')
        ->and($field->isHtmlContent())->toBeFalse();
});

test('placeholder html content', function () {
    $field = Placeholder::make('info')->html('<strong>Bold</strong>');

    expect($field->getContent())->toBe('<strong>Bold</strong>')
        ->and($field->isHtmlContent())->toBeTrue();
});

test('placeholder content via closure', function () {
    $field = Placeholder::make('info')->content(fn () => 'Dynamic');

    expect($field->getContent())->toBe('Dynamic');
});

// ─── Alert ─────────────────────────────────────────────────────

test('alert default color is info', function () {
    $alert = Alert::make('msg')->content('Hello');

    expect($alert->getColor())->toBe('info')
        ->and($alert->getContent())->toBe('Hello');
});

test('alert color variants', function () {
    expect(Alert::make('a')->success()->getColor())->toBe('success')
        ->and(Alert::make('a')->warning()->getColor())->toBe('warning')
        ->and(Alert::make('a')->danger()->getColor())->toBe('danger')
        ->and(Alert::make('a')->info()->getColor())->toBe('info');
});

test('alert color classes resolve through the canonical palette', function () {
    expect(Alert::make('a')->success()->getColorClasses())->toContain('bg-emerald-50')
        ->and(Alert::make('a')->warning()->getColorClasses())->toContain('bg-amber-50')
        ->and(Alert::make('a')->danger()->getColorClasses())->toContain('bg-red-50')
        ->and(Alert::make('a')->info()->getColorClasses())->toContain('bg-blue-50');
});

test('alert title and icon', function () {
    $alert = Alert::make('msg')
        ->title('Warning!')
        ->icon('exclamation')
        ->content('Something happened');

    expect($alert->getTitle())->toBe('Warning!')
        ->and($alert->getIcon())->toBe('exclamation');
});

test('alert dismissible', function () {
    $alert = Alert::make('msg')->dismissible();

    expect($alert->isDismissible())->toBeTrue();
});

test('alert message alias', function () {
    $alert = Alert::make('msg')->message('Content');

    expect($alert->getContent())->toBe('Content');
});

// ─── Html ──────────────────────────────────────────────────────

test('html with raw content', function () {
    $html = Html::make()->content('<div>Raw</div>');

    expect($html->getContent())->toBe('<div>Raw</div>');
});

/*
 * The four factories ship framework markup, so it comes out of a Blade partial
 * rather than a PHP string. These pin the output byte for byte — that move is
 * only free if the partial emits exactly what the concatenation did, whitespace
 * included, and a stray newline in a template is invisible to a toContain().
 */
test('html divider factory', function () {
    expect(Html::divider()->getContent())
        ->toBe('<hr class="my-4 border-gray-200 dark:border-gray-700">');
});

test('html spacer factory', function () {
    expect(Html::spacer('8')->getContent())->toBe('<div class="h-8"></div>');
});

test('html heading factory', function () {
    expect(Html::heading('Title', 2)->getContent())
        ->toBe('<h2 class="text-xl font-semibold text-gray-900 dark:text-white">Title</h2>')
        ->and(Html::heading('Title', 1)->getContent())->toContain('<h1 class="text-2xl font-bold ')
        ->and(Html::heading('Title', 7)->getContent())->toContain('<h7 class="text-base font-medium ');
});

test('html paragraph factory', function () {
    expect(Html::paragraph('Some text')->getContent())
        ->toBe('<p class="text-sm text-gray-600 dark:text-gray-400">Some text</p>');
});

test('the escaping factories still escape', function () {
    // heading() and paragraph() take prose, not markup — that is what separates
    // them from content(), where the caller supplies trusted HTML of their own.
    expect(Html::paragraph('<script>x</script>')->getContent())->toContain('&lt;script&gt;')
        ->and(Html::heading('a & b')->getContent())->toContain('a &amp; b');
});

// ─── ViewField ─────────────────────────────────────────────────

test('view field with blade view', function () {
    $field = ViewField::make('custom')->view('components.custom');

    expect($field->getView())->toBe('components.custom');
});

test('view field with view data', function () {
    $field = ViewField::make('stats')->viewData(['count' => 42]);

    expect($field->getViewData())->toBe(['count' => 42]);
});

test('view field with content', function () {
    $field = ViewField::make('text')->content('Plain text');

    expect($field->getContent())->toBe('Plain text')
        ->and($field->isHtmlContent())->toBeFalse();
});
