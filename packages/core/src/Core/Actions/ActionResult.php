<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Actions;

/**
 * Immutable result of action execution.
 */
final readonly class ActionResult
{
    /**
     * @param  bool  $success  Whether the action was successful
     * @param  string|null  $notification  Notification message to display
     * @param  string|null  $notificationType  Type of notification ('success'|'warning'|'error')
     * @param  string|null  $redirect  URL to redirect to after action
     * @param  bool  $halt  Whether to halt further pipeline processing
     * @param  array<string, mixed>  $data  Additional data returned by the action
     */
    public function __construct(
        public bool $success,
        public ?string $notification = null,
        public ?string $notificationType = null,
        public ?string $redirect = null,
        public bool $halt = false,
        public array $data = [],
    ) {}

    /**
     * Create a successful result.
     *
     * @param  string|null  $notification  Optional success notification
     * @param  array<string, mixed>  $data  Additional data
     */
    public static function success(?string $notification = null, array $data = []): self
    {
        return new self(
            success: true,
            notification: $notification,
            notificationType: $notification !== null ? 'success' : null,
            data: $data,
        );
    }

    /**
     * Create a failure result.
     *
     * @param  string  $notification  Error notification message
     * @param  array<string, mixed>  $data  Additional data
     */
    public static function failure(string $notification, array $data = []): self
    {
        return new self(
            success: false,
            notification: $notification,
            notificationType: 'error',
            data: $data,
        );
    }

    /**
     * Create a redirect result.
     *
     * @param  string  $url  URL to redirect to
     * @param  string|null  $notification  Optional notification to show before redirect
     */
    public static function redirect(string $url, ?string $notification = null): self
    {
        return new self(
            success: true,
            notification: $notification,
            notificationType: $notification !== null ? 'success' : null,
            redirect: $url,
        );
    }

    /**
     * Create a halt result that stops pipeline processing.
     */
    public static function halt(): self
    {
        return new self(
            success: false,
            halt: true,
        );
    }

    /**
     * Determine if the result should trigger a redirect.
     */
    public function shouldRedirect(): bool
    {
        return $this->redirect !== null;
    }

    /**
     * Determine if the result should halt pipeline processing.
     */
    public function shouldHalt(): bool
    {
        return $this->halt;
    }

    /**
     * Determine if the action was successful.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }
}
