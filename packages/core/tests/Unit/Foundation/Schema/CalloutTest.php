<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Schema\Callout;

it('exposes a fluent color / icon / heading / dismissible API', function () {
    $callout = Callout::make()
        ->heading('Heads up')
        ->content('Something to note.')
        ->warning()
        ->icon('exclamation-triangle')
        ->dismissible();

    expect($callout->getHeading())->toBe('Heads up')
        ->and($callout->getContent())->toBe('Something to note.')
        ->and($callout->getColor())->toBe('warning')
        ->and($callout->getIcon())->toBe('exclamation-triangle')
        ->and($callout->isDismissible())->toBeTrue();
});

it('maps the color shorthands to the canonical alert palette', function () {
    expect(Callout::make()->info()->getColorClasses())->toContain('bg-blue-50')
        ->and(Callout::make()->success()->getColorClasses())->toContain('bg-emerald-50')
        ->and(Callout::make()->warning()->getColorClasses())->toContain('bg-amber-50')
        ->and(Callout::make()->danger()->getColorClasses())->toContain('bg-red-50');
});

it('accepts closures for heading and content', function () {
    $callout = Callout::make()
        ->heading(fn () => 'Dynamic')
        ->content(fn () => 'Body');

    expect($callout->getHeading())->toBe('Dynamic')
        ->and($callout->getContent())->toBe('Body');
});

it('renders the shared callout surface with heading, body and dismiss control', function () {
    $html = Callout::make()
        ->heading('Storage almost full')
        ->content('You have used 95% of your quota.')
        ->warning()
        ->dismissible()
        ->toHtml();

    expect($html)->toContain('rounded-md border')
        ->toContain('bg-amber-50')
        ->toContain('Storage almost full')
        ->toContain('You have used 95% of your quota.')
        ->toContain('role="alert"')
        ->toContain('show = false');
});
