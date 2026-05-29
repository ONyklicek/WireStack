<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Concerns;

use Closure;
use NyonCode\WireCore\Actions\ActionHalt;

/**
 * Trait HasLifecycle
 *
 * Provides before/after hooks with halt support for action lifecycle management.
 *
 * The execution flow is:
 *   1. before() hooks run (can halt with $action->halt())
 *   2. action() callback runs (can halt with $halt() as before)
 *   3. after() hooks run (can halt with $action->halt())
 *
 * Halt at any stage stops the pipeline and shows a halt modal.
 *
 * Usage:
 *   Action::make('approve')
 *       ->before(function ($record, $action) {
 *           if ($record->is_locked) {
 *               $action->halt()
 *                   ->modalHeading('Záznam je uzamčen')
 *                   ->modalDescription('Tento záznam nelze schválit, protože je uzamčen.')
 *                   ->informative();
 *           }
 *       })
 *       ->action(fn ($record) => $record->update(['status' => 'approved']))
 *       ->after(function ($record, $action) {
 *           $action->sendSuccessNotification('Záznam byl schválen.');
 *       })
 *       ->successNotification('Schváleno')
 *       ->successRedirect(fn ($record) => route('records.show', $record))
 */
trait HasLifecycle
{
    /**
     * Before hooks - run before the main action callback.
     * Each receives ($record, $action, $data, $confirmed, $component).
     *
     * @var Closure[]
     */
    protected array $beforeCallbacks = [];

    /**
     * After hooks - run after the main action callback.
     * Each receives ($record, $action, $data, $result, $component).
     *
     * @var Closure[]
     */
    protected array $afterCallbacks = [];

    /**
     * Notification settings.
     */
    protected ?string $successNotificationMessage = null;

    protected ?string $failureNotificationMessage = null;

    protected ?Closure $successNotificationCallback = null;

    protected ?Closure $failureNotificationCallback = null;

    /**
     * Redirect after success.
     */
    protected string|Closure|null $successRedirectUrl = null;

    /**
     * Internal halt state - set by $action->halt() inside before/after hooks.
     */
    protected ?ActionHalt $pendingHalt = null;

    // ─── Fluent API ─────────────────────────────────────────────

    /**
     * Register a before hook. Can be called multiple times - hooks run in order.
     *
     * The callback receives named parameters (like action callbacks):
     *   - $record (Model) - the record being acted on
     *   - $action (Action) - this action instance (call $action->halt() to stop)
     *   - $data (array) - form data if modal was shown
     *   - $confirmed (bool) - whether this is a confirmed execution
     *   - $component (mixed) - the Livewire component
     *
     * To halt execution, call $action->halt() which returns an ActionHalt instance:
     *   $action->halt()->modalHeading('Stop!')->danger();
     */
    public function before(Closure $callback): static
    {
        $this->beforeCallbacks[] = $callback;

        return $this;
    }

    /**
     * Register an after hook. Can be called multiple times - hooks run in order.
     *
     * The callback receives named parameters:
     *   - $record (Model) - the record being acted on
     *   - $action (Action) - this action instance (call $action->halt() to stop)
     *   - $data (array) - form data if modal was shown
     *   - $result (mixed) - return value of the main action callback
     *   - $component (mixed) - the Livewire component
     */
    public function after(Closure $callback): static
    {
        $this->afterCallbacks[] = $callback;

        return $this;
    }

    /**
     * Set success notification message (shown after successful execution).
     */
    public function successNotification(string|Closure|null $message): static
    {
        if ($message instanceof Closure) {
            $this->successNotificationCallback = $message;
        } else {
            $this->successNotificationMessage = $message;
        }

        return $this;
    }

    /**
     * Set failure notification message (shown when action fails or is halted).
     */
    public function failureNotification(string|Closure|null $message): static
    {
        if ($message instanceof Closure) {
            $this->failureNotificationCallback = $message;
        } else {
            $this->failureNotificationMessage = $message;
        }

        return $this;
    }

    /**
     * Set redirect URL after successful execution.
     */
    public function successRedirect(string|Closure|null $url): static
    {
        $this->successRedirectUrl = $url;

        return $this;
    }

    // ─── Halt API (called from within before/after hooks) ───────

    /**
     * Halt the action pipeline and return an ActionHalt for modal configuration.
     *
     * Usage inside before/after hooks:
     *   $action->halt()->modalHeading('Pozor!')->danger();
     */
    public function halt(): ActionHalt
    {
        $this->pendingHalt = ActionHalt::make();

        return $this->pendingHalt;
    }

    /**
     * Send a success notification programmatically from within a hook.
     *
     * Uses the pluggable notification system via service container resolution.
     * Falls back silently if Notifications module is not available.
     *
     * For component-aware notifications, use $component->sendNotification() instead.
     */
    public function sendSuccessNotification(?string $message = null): void
    {
        $msg = $message ?? $this->successNotificationMessage;
        if ($msg) {
            $this->dispatchNotification('success', $msg);
        }
    }

    /**
     * Send a failure notification programmatically from within a hook.
     *
     * @see sendSuccessNotification() for details on the notification system.
     */
    public function sendFailureNotification(?string $message = null): void
    {
        $msg = $message ?? $this->failureNotificationMessage;
        if ($msg) {
            $this->dispatchNotification('error', $msg);
        }
    }

    /**
     * Send a warning notification programmatically from within a hook.
     */
    public function sendWarningNotification(string $message): void
    {
        $this->dispatchNotification('warning', $message);
    }

    /**
     * Send an info notification programmatically from within a hook.
     */
    public function sendInfoNotification(string $message): void
    {
        $this->dispatchNotification('info', $message);
    }

    /**
     * Send a rich notification object.
     *
     * Usage inside a hook:
     *   $action->sendNotification(
     *       \NyonCode\WireCore\Notifications\Notification::success('Vytvořeno')
     *           ->title('Nový záznam')
     *           ->duration(5000)
     *   );
     *
     * @param  object  $notification  A Notification instance from the Notifications module
     */
    public function sendNotification(object $notification): void
    {
        $managerClass = self::resolveNotificationManagerClass();
        if ($managerClass === null) {
            return;
        }

        $managerClass::send($notification);
    }

    /**
     * Dispatch a notification via the Notifications module.
     *
     * Communicates with Notifications through late-static class resolution,
     * avoiding direct imports. Falls back silently if the Notifications module
     * is not available, ensuring Actions work independently.
     */
    protected function dispatchNotification(string $type, string $message): void
    {
        $managerClass = self::resolveNotificationManagerClass();
        if ($managerClass === null) {
            return;
        }

        $managerClass::$type($message);
    }

    /**
     * Resolve the NotificationManager class name if available.
     *
     * @return class-string|null
     */
    protected static function resolveNotificationManagerClass(): ?string
    {
        $class = 'NyonCode\\WireCore\\Notifications\\NotificationManager';

        if (! class_exists($class)) {
            return null;
        }

        return $class;
    }

    // ─── Getters ────────────────────────────────────────────────

    /** @return Closure[] */
    public function getBeforeCallbacks(): array
    {
        return $this->beforeCallbacks;
    }

    /** @return Closure[] */
    public function getAfterCallbacks(): array
    {
        return $this->afterCallbacks;
    }

    public function hasBeforeCallbacks(): bool
    {
        return ! empty($this->beforeCallbacks);
    }

    public function hasAfterCallbacks(): bool
    {
        return ! empty($this->afterCallbacks);
    }

    public function getSuccessNotificationMessage(mixed $context = null): ?string
    {
        if ($this->successNotificationCallback) {
            return ($this->successNotificationCallback)($context);
        }

        return $this->successNotificationMessage;
    }

    public function getFailureNotificationMessage(mixed $context = null): ?string
    {
        if ($this->failureNotificationCallback) {
            return ($this->failureNotificationCallback)($context);
        }

        return $this->failureNotificationMessage;
    }

    public function getSuccessRedirectUrl(mixed $context = null): ?string
    {
        if ($this->successRedirectUrl instanceof Closure) {
            return ($this->successRedirectUrl)($context);
        }

        return $this->successRedirectUrl;
    }

    /**
     * Check if a halt was requested and return it (consuming the pending halt).
     */
    public function consumePendingHalt(): ?ActionHalt
    {
        $halt = $this->pendingHalt;
        $this->pendingHalt = null;

        return $halt;
    }

    /**
     * Check if there's a pending halt without consuming it.
     */
    public function hasPendingHalt(): bool
    {
        return $this->pendingHalt !== null;
    }
}
