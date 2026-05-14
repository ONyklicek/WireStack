<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use NyonCode\WireCore\Core\Actions\ActionContext;
use NyonCode\WireCore\Core\Actions\ActionPipeline;
use NyonCode\WireCore\Core\Actions\ActionRegistry;
use NyonCode\WireCore\Core\Actions\ActionResult;

// --- ActionResult ---

it('creates a success result', function () {
    $result = ActionResult::success('Record saved.', ['id' => 1]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->shouldRedirect())->toBeFalse()
        ->and($result->shouldHalt())->toBeFalse()
        ->and($result->notification)->toBe('Record saved.')
        ->and($result->data)->toBe(['id' => 1]);
});

it('creates a failure result', function () {
    $result = ActionResult::failure('Something went wrong.', ['code' => 500]);

    expect($result->isSuccess())->toBeFalse()
        ->and($result->success)->toBeFalse()
        ->and($result->notification)->toBe('Something went wrong.')
        ->and($result->data)->toBe(['code' => 500]);
});

it('creates a redirect result', function () {
    $result = ActionResult::redirect('/dashboard', 'Redirecting...');

    expect($result->shouldRedirect())->toBeTrue()
        ->and($result->redirect)->toBe('/dashboard')
        ->and($result->notification)->toBe('Redirecting...');
});

it('creates a halt result', function () {
    $result = ActionResult::halt();

    expect($result->shouldHalt())->toBeTrue()
        ->and($result->halt)->toBeTrue();
});

it('creates a success result without notification', function () {
    $result = ActionResult::success();

    expect($result->isSuccess())->toBeTrue()
        ->and($result->notification)->toBeNull();
});

// --- ActionContext ---

it('detects a bulk context', function () {
    $records = new Collection([new class extends Model {}]);
    $context = new ActionContext(records: $records);

    expect($context->isBulk())->toBeTrue()
        ->and($context->getRecords())->toBe($records);
});

it('detects a single record context', function () {
    $record = new class extends Model {};
    $context = new ActionContext(record: $record);

    expect($context->isBulk())->toBeFalse()
        ->and($context->getRecord())->toBe($record);
});

it('provides get and set for arbitrary data', function () {
    $context = new ActionContext(formData: ['title' => 'Test']);

    expect($context->getFormData())->toBe(['title' => 'Test'])
        ->and($context->get('missing', 'default'))->toBe('default');

    $context->set('custom', 'value');

    expect($context->get('custom'))->toBe('value');
});

// --- ActionRegistry ---

it('registers and retrieves action handlers', function () {
    $registry = new ActionRegistry;
    $handler = fn () => ActionResult::success();

    $registry->register('delete', $handler);

    expect($registry->has('delete'))->toBeTrue()
        ->and($registry->get('delete'))->toBe($handler);
});

it('lists all registered actions', function () {
    $registry = new ActionRegistry;
    $registry->register('create', fn () => ActionResult::success());
    $registry->register('update', fn () => ActionResult::success());

    expect($registry->all())->toHaveKeys(['create', 'update']);
});

it('removes a registered action', function () {
    $registry = new ActionRegistry;
    $registry->register('delete', fn () => ActionResult::success());
    $registry->remove('delete');

    expect($registry->has('delete'))->toBeFalse()
        ->and($registry->get('delete'))->toBeNull();
});

// --- ActionPipeline ---

it('executes an action through the pipeline', function () {
    $pipeline = new ActionPipeline;
    $context = new ActionContext(actionName: 'test');

    $result = $pipeline->execute($context, function (ActionContext $ctx) {
        return ActionResult::success('Done');
    });

    expect($result->isSuccess())->toBeTrue()
        ->and($result->notification)->toBe('Done');
});

it('executes an action through a pipeline with custom stage', function () {
    $log = [];

    $stage = new class($log)
    {
        public function __construct(private array &$log) {}

        public function __invoke(ActionContext $context, Closure $next): ActionResult
        {
            $this->log[] = 'before';
            $result = $next($context);
            $this->log[] = 'after';

            return $result;
        }
    };

    $pipeline = new ActionPipeline([$stage]);
    $context = new ActionContext;

    $result = $pipeline->execute($context, function () use (&$log) {
        $log[] = 'action';

        return ActionResult::success();
    });

    expect($result->isSuccess())->toBeTrue()
        ->and($log)->toBe(['before', 'action', 'after']);
});

it('returns a new instance when piping a stage (immutable)', function () {
    $pipeline = new ActionPipeline;
    $newPipeline = $pipeline->pipe(fn (ActionContext $ctx, Closure $next) => $next($ctx));

    expect($newPipeline)->not->toBe($pipeline);
});
