<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Actions\ActionContext;
use NyonCode\WireCore\Core\Actions\ActionResult;
use NyonCode\WireCore\Core\Actions\Stages\AfterCallbacksStage;
use NyonCode\WireCore\Core\Actions\Stages\NotificationStage;
use NyonCode\WireCore\Core\Actions\Stages\RedirectStage;

// ─── AfterCallbacksStage ───────────────────────────────────────

it('runs after callbacks with context and result', function () {
    $log = [];
    $context = new ActionContext(arguments: [
        'afterCallbacks' => [
            function (ActionContext $ctx, ActionResult $res) use (&$log) {
                $log[] = $res->success;
            },
        ],
    ]);

    $result = (new AfterCallbacksStage)->handle($context, fn () => ActionResult::success('ok'));

    expect($result->success)->toBeTrue()
        ->and($log)->toBe([true]);
});

it('handles an absence of after callbacks', function () {
    $result = (new AfterCallbacksStage)->handle(new ActionContext, fn () => ActionResult::success());

    expect($result->success)->toBeTrue();
});

// ─── NotificationStage ─────────────────────────────────────────

it('stores notification data in context when present', function () {
    $context = new ActionContext;

    (new NotificationStage)->handle($context, fn () => new ActionResult(success: true, notification: 'Saved', notificationType: 'warning'));

    expect($context->get('notification'))->toBe(['message' => 'Saved', 'type' => 'warning']);
});

it('defaults the notification type to success', function () {
    $context = new ActionContext;

    (new NotificationStage)->handle($context, fn () => new ActionResult(success: true, notification: 'Saved'));

    expect($context->get('notification'))->toBe(['message' => 'Saved', 'type' => 'success']);
});

it('stores nothing when there is no notification', function () {
    $context = new ActionContext;

    (new NotificationStage)->handle($context, fn () => ActionResult::success());

    expect($context->get('notification'))->toBeNull();
});

// ─── RedirectStage ─────────────────────────────────────────────

it('stores the redirect url in context when redirecting', function () {
    $context = new ActionContext;

    (new RedirectStage)->handle($context, fn () => ActionResult::redirect('/home'));

    expect($context->get('redirect'))->toBe('/home');
});

it('stores no redirect when the result does not redirect', function () {
    $context = new ActionContext;

    (new RedirectStage)->handle($context, fn () => ActionResult::success());

    expect($context->get('redirect'))->toBeNull();
});
