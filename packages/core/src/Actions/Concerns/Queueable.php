<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Concerns;

use NyonCode\WireCore\Actions\Jobs\RunActionJob;

/**
 * An action that runs on a queue instead of in the request.
 *
 * Opt-in per action, because most actions should stay synchronous: a user who
 * clicks "Delete" expects the row gone when the page comes back, and moving that
 * to a worker buys nothing but a race. What this is for is the long tail —
 * a bulk action over ten thousand rows, an export that would time out.
 *
 *   Action::make('recalculate')->queue()
 *   Action::make('recalculate')->queue()->onQueue('reports')
 *   Action::make('recalculate')->queue()->onConnection('redis')
 *
 * The cost is stated rather than hidden: a queued action runs **without a
 * browser**. It has no modal to write into, nothing to close, and no redirect to
 * follow, so the bindings a synchronous callback gets for those
 * (`$set`, `$close`, `$replace`, …) are absent — see
 * {@see RunActionJob}, which refuses them
 * loudly rather than passing no-ops that would look like they worked.
 *
 * What a queued action reports back with is a notification, which is why the
 * database driver exists: by the time the job finishes, the request that started
 * it is long gone.
 */
trait Queueable
{
    protected bool $shouldQueue = false;

    protected ?string $queueName = null;

    protected ?string $queueConnection = null;

    /** Run this action on a queue instead of in the request. */
    public function queue(bool $condition = true): static
    {
        $this->shouldQueue = $condition;

        return $this;
    }

    /** Name the queue. Implies {@see queue()} — naming one is asking for it. */
    public function onQueue(?string $queue): static
    {
        $this->queueName = $queue;
        $this->shouldQueue = true;

        return $this;
    }

    /** Name the connection. Implies {@see queue()} for the same reason. */
    public function onConnection(?string $connection): static
    {
        $this->queueConnection = $connection;
        $this->shouldQueue = true;

        return $this;
    }

    public function isQueued(): bool
    {
        return $this->shouldQueue;
    }

    public function getQueueName(): ?string
    {
        return $this->queueName;
    }

    public function getQueueConnection(): ?string
    {
        return $this->queueConnection;
    }
}
