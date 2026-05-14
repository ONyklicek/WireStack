<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Actions;

use Closure;
use NyonCode\WireCore\Core\Actions\Stages\ActionExecutionStage;
use NyonCode\WireCore\Core\Actions\Stages\ActionStage;
use NyonCode\WireCore\Core\Actions\Stages\AfterCallbacksStage;
use NyonCode\WireCore\Core\Actions\Stages\BeforeCallbacksStage;
use NyonCode\WireCore\Core\Actions\Stages\NotificationStage;
use NyonCode\WireCore\Core\Actions\Stages\RedirectStage;

/**
 * Pipeline for action execution through stages.
 *
 * Each stage receives the context and a closure to call the next stage,
 * following the middleware pattern.
 */
final class ActionPipeline
{
    /**
     * @var array<callable|ActionStage>
     */
    private array $stages;

    /**
     * @param  array<callable|ActionStage>  $stages  Pipeline stages to execute
     */
    public function __construct(array $stages = [])
    {
        $this->stages = $stages;
    }

    /**
     * Add a stage to the pipeline.
     *
     * Returns a new instance (immutable builder pattern).
     */
    public function pipe(callable|ActionStage $stage): self
    {
        $new = new self($this->stages);
        $new->stages[] = $stage;

        return $new;
    }

    /**
     * Execute the pipeline with the given context and action.
     *
     * @param  ActionContext  $context  The action context
     * @param  Closure  $action  The main action closure to execute
     */
    public function execute(ActionContext $context, Closure $action): ActionResult
    {
        $stages = $this->resolveStages($action);

        $terminal = function (ActionContext $ctx) use ($action): ActionResult {
            $result = $action($ctx);

            return $result instanceof ActionResult ? $result : ActionResult::success();
        };

        $pipeline = array_reduce(
            array_reverse($stages),
            fn (Closure $next, callable|ActionStage $stage) => fn (ActionContext $ctx) => $stage instanceof ActionStage
                ? $stage->handle($ctx, $next)
                : $stage($ctx, $next),
            $terminal,
        );

        return $pipeline($context);
    }

    /**
     * Resolve the stages to use, applying defaults if none provided.
     *
     * @return array<Closure|ActionStage>
     */
    private function resolveStages(Closure $action): array
    {
        if ($this->stages !== []) {
            return $this->stages;
        }

        return [
            new BeforeCallbacksStage,
            new ActionExecutionStage($action),
            new AfterCallbacksStage,
            new NotificationStage,
            new RedirectStage,
        ];
    }
}
