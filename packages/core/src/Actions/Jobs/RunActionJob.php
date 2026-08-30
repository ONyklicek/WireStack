<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Jobs;

use Illuminate\Bus\Queueable as BusQueueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use NyonCode\WireCore\Actions\Concerns\HasLifecycle;
use NyonCode\WireCore\Core\Actions\ActionContext;
use NyonCode\WireCore\Core\Actions\ActionPipeline;
use NyonCode\WireCore\Core\Actions\ActionResult;
use NyonCode\WireCore\Exceptions\QueuedActionException;

/**
 * Runs one action on a worker.
 *
 * **What it carries is names and keys, never objects.** The host class, the
 * action's name, the record keys, the submitted form data — all scalars. Not the
 * action (it holds closures), not the models (they would be stale by the time
 * the job runs, and a bulk action over ten thousand of them would be a
 * megabyte of payload). The job rebuilds the host, asks it for the action by
 * name, and resolves the records fresh. That is ADR 0019's key-not-model rule
 * applied to the write path.
 *
 * **A queued action has no browser**, and that is stated rather than worked
 * around. The synchronous path hands a callback `$set`, `$close`, `$replace` and
 * the rest — every one an operation on a live modal stack. Here they are bound
 * to throw {@see QueuedActionException}, because a no-op would look like it
 * worked and the developer would find out from a user reporting that the modal
 * never closed.
 *
 * What a queued action reports with is a notification. By the time it finishes
 * the request that started it is gone, which is exactly what the database
 * notification driver is for.
 */
class RunActionJob implements ShouldQueue
{
    use BusQueueable;
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    /**
     * @param  class-string  $host  The component that declares the action.
     * @param  string  $actionName  Looked up on the rebuilt host.
     * @param  array<int, mixed>  $recordKeys  Keys, not models — resolved fresh in the job.
     * @param  array<string, mixed>  $formData  Whatever the modal submitted.
     * @param  string|null  $completionMessage  Sent when the action finishes, if set.
     */
    public function __construct(
        public readonly string $host,
        public readonly string $actionName,
        public readonly array $recordKeys = [],
        public readonly array $formData = [],
        public readonly ?string $completionMessage = null,
    ) {}

    public function handle(): void
    {
        $host = $this->resolveHost();
        $action = $this->resolveAction($host);

        $context = new ActionContext(
            records: collect($this->resolveRecords($host)),
            formData: $this->formData,
            actionName: $this->actionName,
        );

        // A single record is still handed over as `record`, so a callback typed
        // for the synchronous path sees what it expects.
        if (count($this->recordKeys) === 1) {
            $context->record = $context->records?->first();
            $context->records = null;
        }

        $context->set('queued', true);
        $context->set('component', $host);

        $result = app(ActionPipeline::class)->execute(
            $context,
            fn (ActionContext $ctx): mixed => $this->runCallback($action, $ctx),
        );

        $this->reportCompletion($result);
    }

    private function resolveHost(): object
    {
        if (! class_exists($this->host)) {
            throw QueuedActionException::unresolvableHost($this->host);
        }

        try {
            return app($this->host);
        } catch (\Throwable) {
            throw QueuedActionException::unresolvableHost($this->host);
        }
    }

    private function resolveAction(object $host): object
    {
        // The host's own lookup, deliberately: a second copy here would drift
        // from whatever the host counts as a declared action — grouped actions
        // and behaviour-only record actions included.
        if (! method_exists($host, 'resolveActionByName')) {
            throw QueuedActionException::unresolvableHost($this->host);
        }

        $action = $host->resolveActionByName($this->actionName);

        if ($action === null) {
            throw QueuedActionException::actionGone($this->actionName, $this->host);
        }

        return $action;
    }

    /**
     * @return array<int, Model>
     */
    private function resolveRecords(object $host): array
    {
        if ($this->recordKeys === [] || ! method_exists($host, 'resolveRecordsByKey')) {
            return [];
        }

        return $host->resolveRecordsByKey($this->recordKeys);
    }

    private function runCallback(object $action, ActionContext $context): mixed
    {
        $callback = method_exists($action, 'getActionCallback') ? $action->getActionCallback() : null;

        if ($callback === null) {
            return ActionResult::success();
        }

        return app()->call($callback, array_merge(
            $this->browserlessBindings(),
            array_filter([
                'record' => $context->record,
                'records' => $context->records,
                'data' => $context->formData,
                'action' => $action,
            ], fn (mixed $v): bool => $v !== null),
        ));
    }

    /**
     * The bindings a queued action does not get, bound to say so.
     *
     * @return array<string, callable>
     */
    private function browserlessBindings(): array
    {
        $refuse = fn (string $binding): callable => function () use ($binding): never {
            throw QueuedActionException::noBrowser($this->actionName, $binding);
        };

        return [
            'set' => $refuse('set'),
            'setParent' => $refuse('setParent'),
            'setFrame' => $refuse('setFrame'),
            'close' => $refuse('close'),
            'replace' => $refuse('replace'),
            'halt' => $refuse('halt'),
        ];
    }

    /**
     * Say it finished.
     *
     * Reaches the Notifications module by class name rather than by import: both
     * live at L2, which may not see each other (ADR 0025), and this is the same
     * soft seam {@see HasLifecycle::resolveNotificationManagerClass()}
     * already uses. It also degrades the way that one does — a build without the
     * module simply reports nothing.
     */
    private function reportCompletion(ActionResult $result): void
    {
        if ($this->completionMessage === null) {
            return;
        }

        $manager = 'NyonCode\\WireCore\\Notifications\\NotificationManager';

        if (! class_exists($manager)) {
            return;
        }

        $manager::{$result->isSuccess() ? 'success' : 'error'}($this->completionMessage);
    }
}
