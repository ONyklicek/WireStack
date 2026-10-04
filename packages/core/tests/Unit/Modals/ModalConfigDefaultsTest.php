<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionHalt;
use NyonCode\WireCore\Modals\ConfirmationDialog;
use NyonCode\WireCore\Modals\Html\Confirmation as HtmlConfirmation;
use NyonCode\WireCore\Modals\Html\Modal as HtmlModal;
use NyonCode\WireCore\Modals\Html\SlideOver as HtmlSlideOver;
use NyonCode\WireCore\Modals\Modal;
use NyonCode\WireCore\Modals\SlideOver;
use NyonCode\WireCore\Modals\View\ConfirmationComponent;
use NyonCode\WireCore\Modals\View\ModalComponent;
use NyonCode\WireCore\Modals\View\SlideOverComponent;
use NyonCode\WireCore\Modals\Wizard;

/*
 * `config/wire-core.php` → `modals` is the default for every modal, whichever way
 * it is drawn: a modal object, an action's own modal, an `ActionHalt`, or a
 * Blade component used directly. An explicit setting still wins.
 */

beforeEach(function () {
    config()->set('wire-core.modals', [
        'default_width' => 'xl',
        'slide_over_width' => '2xl',
        'close_on_click_away' => false,
        'close_on_escape' => false,
    ]);
});

it('gives every dialog object the configured width, read when asked', function () {
    $modal = Modal::make();

    // Set after the object exists: the default is read when asked, not built in.
    config()->set('wire-core.modals.default_width', 'lg');

    expect($modal->getWidth())->toBe('lg')
        ->and(Wizard::make()->getWidth())->toBe('lg')
        ->and(ConfirmationDialog::make()->getWidth())->toBe('lg')
        ->and(ActionHalt::make()->getWidth())->toBe('lg');
});

it('gives a slide-over its own configured width', function () {
    expect(SlideOver::make()->getWidth())->toBe('2xl');
});

it('lets an explicit width win over the configured one', function () {
    expect(Modal::make()->width('sm')->getWidth())->toBe('sm')
        ->and(SlideOver::make()->width('sm')->getWidth())->toBe('sm')
        ->and(ActionHalt::make()->width('sm')->getWidth())->toBe('sm');
});

it('serializes a halt with the configured modal defaults', function () {
    $modal = ActionHalt::make()->toArray()['modal'];

    expect($modal['width'])->toBe('xl')
        ->and($modal['closeOnClickAway'])->toBeFalse()
        ->and($modal['closeOnEscape'])->toBeFalse();
});

it('gives an action\'s own modal the configured defaults', function () {
    $action = Action::make('edit');

    expect($action->getModalWidth())->toBe('xl')
        ->and($action->shouldCloseModalOnClickAway())->toBeFalse()
        ->and($action->shouldCloseModalOnEscape())->toBeFalse();
});

it('gives an action opened as a slide-over the slide-over width', function () {
    expect(Action::make('edit')->slideOver()->getModalWidth())->toBe('2xl');
});

it('lets an action\'s explicit modal settings win', function () {
    $action = Action::make('edit')->modalWidth('sm')->closeModalOnClickAway()->closeModalOnEscape();

    expect($action->getModalWidth())->toBe('sm')
        ->and($action->shouldCloseModalOnClickAway())->toBeTrue()
        ->and($action->shouldCloseModalOnEscape())->toBeTrue();
});

it('takes the defaults over from a modal object handed to an action', function () {
    $action = Action::make('edit')->modal(SlideOver::make());

    expect($action->getModalWidth())->toBe('2xl')
        ->and($action->shouldCloseModalOnEscape())->toBeFalse();
});

it('gives the Blade components the configured defaults when no attribute is passed', function () {
    $modal = new ModalComponent;
    $slideOver = new SlideOverComponent;
    $confirmation = new ConfirmationComponent;

    expect($modal->width)->toBe('xl')
        ->and($modal->closeOnClickAway)->toBeFalse()
        ->and($modal->closeOnEscape)->toBeFalse()
        ->and($modal->style()->widthClass())->toContain('max-w-xl')
        ->and($slideOver->width)->toBe('2xl')
        ->and($slideOver->style()->widthClass())->toContain('max-w-2xl')
        ->and($confirmation->width)->toBe('xl')
        ->and($confirmation->closeOnEscape)->toBeFalse();
});

it('lets a Blade component attribute win over the configured default', function () {
    $modal = new ModalComponent(width: 'sm', closeOnClickAway: true, closeOnEscape: true);

    expect($modal->width)->toBe('sm')
        ->and($modal->closeOnClickAway)->toBeTrue()
        ->and($modal->closeOnEscape)->toBeTrue();
});

it('gives the Htmlable modal objects the configured defaults', function () {
    $modal = new HtmlModal;
    $slideOver = new HtmlSlideOver;
    $confirmation = new HtmlConfirmation;

    expect($modal->width)->toBe('xl')
        ->and($modal->closeOnEscape)->toBeFalse()
        ->and($slideOver->width)->toBe('2xl')
        ->and($slideOver->closeOnClickAway)->toBeFalse()
        ->and($confirmation->width)->toBe('xl')
        ->and($confirmation->closeOnClickAway)->toBeFalse();
});

it('draws a Htmlable modal without its own close handlers when config says so', function () {
    $html = (new HtmlModal(heading: 'Help', id: 'help'))->toHtml();

    expect($html)->not->toContain('keydown.escape.window');
});
