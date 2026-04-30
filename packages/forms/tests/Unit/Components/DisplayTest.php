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

test('html divider factory', function () {
    $html = Html::divider();

    expect($html->getContent())->toContain('<hr');
});

test('html spacer factory', function () {
    $html = Html::spacer('8');

    expect($html->getContent())->toContain('h-8');
});

test('html heading factory', function () {
    $html = Html::heading('Title', 2);

    expect($html->getContent())->toContain('<h2')
        ->and($html->getContent())->toContain('Title');
});

test('html paragraph factory', function () {
    $html = Html::paragraph('Some text');

    expect($html->getContent())->toContain('<p')
        ->and($html->getContent())->toContain('Some text');
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
