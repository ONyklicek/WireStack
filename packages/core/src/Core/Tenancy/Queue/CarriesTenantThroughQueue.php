<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Tenancy\Queue;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Queue;
use NyonCode\WireCore\Core\Tenancy\CurrentTenant;
use NyonCode\WireCore\Exceptions\TenancyException;

/**
 * Queued work runs in the tenant it was dispatched in (ADR 0040 §9).
 *
 * A job dispatched inside a tenant carries it in its payload — the model's
 * class and key, nothing else — and the worker enters it before `handle()`. A
 * job dispatched outside any tenant runs outside one, and the fail-safe
 * applies: no rows, never somebody else's.
 *
 * **Restored, not left, afterwards.** A `sync` job runs inside the request that
 * dispatched it; leaving the tenant after it would take the request's tenant
 * away mid-request. So what was current before the job is kept and put back —
 * on a worker that is nothing, since the worker forgets scoped instances before
 * it takes the next job.
 *
 * A tenant that no longer exists fails the job loudly rather than running it
 * in no tenant, where it would quietly do nothing.
 */
final class CarriesTenantThroughQueue
{
    public const PAYLOAD = 'wireTenant';

    /** @var array<int, Model|null> What was current before each job still running. */
    private array $previous = [];

    public function register(Dispatcher $events): void
    {
        Queue::createPayloadUsing(static function (): array {
            $tenant = app(CurrentTenant::class)->get();

            return $tenant === null ? [] : [self::PAYLOAD => ['class' => $tenant::class, 'key' => $tenant->getKey()]];
        });

        $events->listen(JobProcessing::class, fn (JobProcessing $event) => $this->enter($event->job->payload()));

        foreach ([JobProcessed::class, JobFailed::class, JobExceptionOccurred::class] as $finished) {
            $events->listen($finished, fn () => $this->restore());
        }
    }

    /** @param  array<string, mixed>  $payload */
    private function enter(array $payload): void
    {
        $current = app(CurrentTenant::class);
        $this->previous[] = $current->get();

        $carried = $payload[self::PAYLOAD] ?? null;

        if (! is_array($carried)) {
            $current->leave();

            return;
        }

        $class = $carried['class'] ?? null;
        $tenant = is_string($class) && is_a($class, Model::class, true)
            ? $class::query()->withoutGlobalScopes()->find($carried['key'] ?? null)
            : null;

        if (! $tenant instanceof Model) {
            throw TenancyException::tenantGone(is_string($class) ? $class : 'unknown', $carried['key'] ?? null);
        }

        $current->enter($tenant);
    }

    private function restore(): void
    {
        // Nothing recorded pops as null, which leaves — the safe answer.
        $previous = array_pop($this->previous);
        $current = app(CurrentTenant::class);

        $previous === null ? $current->leave() : $current->enter($previous);
    }
}
