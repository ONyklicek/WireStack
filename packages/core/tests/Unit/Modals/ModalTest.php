<?php

declare(strict_types=1);

use NyonCode\WireCore\Modals\Modal;

// ─── Factory ───────────────────────────────────────────────────────────

it('can be created with make', function () {
    $modal = Modal::make();

    expect($modal)->toBeInstanceOf(Modal::class);
});

// ─── Heading ───────────────────────────────────────────────────────────

it('has no heading by default', function () {
    expect(Modal::make()->getHeading())->toBeNull();
});

it('can set heading', function () {
    expect(Modal::make()->heading('Test Heading')->getHeading())->toBe('Test Heading');
});

it('supports dynamic heading via closure', function () {
    $modal = Modal::make()->heading(fn ($ctx) => "Hello {$ctx->name}");
    $context = (object) ['name' => 'World'];

    expect($modal->getHeading($context))->toBe('Hello World');
});

// ─── Description ───────────────────────────────────────────────────────

it('has no description by default', function () {
    expect(Modal::make()->getDescription())->toBeNull();
});

it('can set description', function () {
    expect(Modal::make()->description('Some info')->getDescription())->toBe('Some info');
});

it('supports dynamic description via closure', function () {
    $modal = Modal::make()->description(fn ($ctx) => "Editing {$ctx->name}");
    $context = (object) ['name' => 'Item'];

    expect($modal->getDescription($context))->toBe('Editing Item');
});

// ─── Width ─────────────────────────────────────────────────────────────

it('has default md width', function () {
    expect(Modal::make()->getWidth())->toBe('md');
});

it('can set width', function () {
    expect(Modal::make()->width('xl')->getWidth())->toBe('xl');
});

// ─── Icon ──────────────────────────────────────────────────────────────

it('has no icon by default', function () {
    expect(Modal::make()->getIcon())->toBeNull();
});

it('can set icon with color', function () {
    $modal = Modal::make()->icon('pencil', 'primary');

    expect($modal->getIcon())->toBe('pencil')
        ->and($modal->getIconColor())->toBe('primary');
});

it('has gray default icon color', function () {
    expect(Modal::make()->getIconColor())->toBe('gray');
});

// ─── Color ─────────────────────────────────────────────────────────────

it('has no color by default', function () {
    expect(Modal::make()->getColor())->toBeNull();
});

it('can set color', function () {
    expect(Modal::make()->color('primary')->getColor())->toBe('primary');
});

// ─── Close Behavior ────────────────────────────────────────────────────

it('closes on click away by default', function () {
    expect(Modal::make()->shouldCloseOnClickAway())->toBeTrue();
});

it('closes on escape by default', function () {
    expect(Modal::make()->shouldCloseOnEscape())->toBeTrue();
});

it('can disable close on click away', function () {
    expect(Modal::make()->closeOnClickAway(false)->shouldCloseOnClickAway())->toBeFalse();
});

it('can disable close on escape', function () {
    expect(Modal::make()->closeOnEscape(false)->shouldCloseOnEscape())->toBeFalse();
});

// ─── Mobile ────────────────────────────────────────────────────────────

it('is not full screen on mobile by default', function () {
    expect(Modal::make()->isFullScreenOnMobile())->toBeFalse();
});

it('can be full screen on mobile', function () {
    expect(Modal::make()->fullScreenOnMobile()->isFullScreenOnMobile())->toBeTrue();
});

it('can set mobile width', function () {
    expect(Modal::make()->mobileWidth('sm')->getMobileWidth())->toBe('sm');
});

// ─── Max Height ────────────────────────────────────────────────────────

it('has no max height by default', function () {
    expect(Modal::make()->getMaxHeight())->toBeNull();
});

it('can set max height', function () {
    expect(Modal::make()->maxHeight('60vh')->getMaxHeight())->toBe('60vh');
});

// ─── Sticky Footer/Header ─────────────────────────────────────────────

it('does not have sticky footer by default', function () {
    expect(Modal::make()->hasStickyFooter())->toBeFalse();
});

it('can set sticky footer', function () {
    expect(Modal::make()->stickyFooter()->hasStickyFooter())->toBeTrue();
});

it('can set sticky header', function () {
    expect(Modal::make()->stickyHeader()->hasStickyHeader())->toBeTrue();
});

// ─── Footer Labels ─────────────────────────────────────────────────────

it('has default submit label from translation', function () {
    expect(Modal::make()->getSubmitLabel())->toBe('Confirm');
});

it('has default cancel label from translation', function () {
    expect(Modal::make()->getCancelLabel())->toBe('Cancel');
});

it('can set custom submit label', function () {
    expect(Modal::make()->submitLabel('Save')->getSubmitLabel())->toBe('Save');
});

it('can set custom cancel label', function () {
    expect(Modal::make()->cancelLabel('Dismiss')->getCancelLabel())->toBe('Dismiss');
});

// ─── ID ────────────────────────────────────────────────────────────────

it('has no id by default', function () {
    expect(Modal::make()->getId())->toBeNull();
});

it('can set id', function () {
    expect(Modal::make()->id('my-modal')->getId())->toBe('my-modal');
});

// ─── Serialization ─────────────────────────────────────────────────────

it('serializes to array with all properties', function () {
    $modal = Modal::make()
        ->heading('Edit')
        ->description('Edit the record')
        ->icon('pencil', 'primary')
        ->color('primary')
        ->width('lg')
        ->maxHeight('60vh')
        ->closeOnClickAway(false)
        ->closeOnEscape(false)
        ->fullScreenOnMobile()
        ->mobileWidth('sm')
        ->submitLabel('Save')
        ->cancelLabel('Dismiss')
        ->stickyFooter()
        ->stickyHeader()
        ->id('edit-modal');

    $array = $modal->toArray();

    expect($array['id'])->toBe('edit-modal')
        ->and($array['heading'])->toBe('Edit')
        ->and($array['description'])->toBe('Edit the record')
        ->and($array['icon'])->toBe('pencil')
        ->and($array['iconColor'])->toBe('primary')
        ->and($array['color'])->toBe('primary')
        ->and($array['width'])->toBe('lg')
        ->and($array['maxHeight'])->toBe('60vh')
        ->and($array['closeOnClickAway'])->toBeFalse()
        ->and($array['closeOnEscape'])->toBeFalse()
        ->and($array['fullScreenOnMobile'])->toBeTrue()
        ->and($array['mobileWidth'])->toBe('sm')
        ->and($array['submitLabel'])->toBe('Save')
        ->and($array['cancelLabel'])->toBe('Dismiss')
        ->and($array['stickyFooter'])->toBeTrue()
        ->and($array['stickyHeader'])->toBeTrue();
});

// ─── Fluent API ────────────────────────────────────────────────────────

it('supports fluent chaining', function () {
    $modal = Modal::make()
        ->heading('Test')
        ->description('Desc')
        ->width('lg')
        ->icon('edit')
        ->color('primary');

    expect($modal)->toBeInstanceOf(Modal::class)
        ->and($modal->getHeading())->toBe('Test');
});
