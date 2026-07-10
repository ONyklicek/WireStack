<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications;

use NyonCode\WireCore\Foundation\Icons\Icon;

/**
 * Immutable value object representing a notification.
 *
 * Carries all metadata a notification driver might need:
 * type, message, title, duration, actions, and arbitrary extra data.
 *
 * Usage in actions:
 *   ->successNotification('Uloženo')
 *   ->successNotification(
 *       Notification::make('success', 'Uloženo')->title('Hotovo')->duration(5000)
 *   )
 *   ->successNotification(
 *       Notification::success('Deleted')->persistent()->action('Undo', 'restore')
 *   )
 */
final class Notification
{
    /**
     * @param  array<string, mixed>  $extra
     * @param  list<NotificationAction>  $actions
     */
    private function __construct(
        public readonly string $type,
        public readonly string $message,
        public readonly ?string $title = null,
        public readonly ?int $duration = null,
        public readonly ?string $icon = null,
        public readonly ?string $position = null,
        public readonly array $extra = [],
        public readonly array $actions = [],
    ) {}

    /**
     * Create a new notification.
     */
    public static function make(string $type, string $message): self
    {
        return new self(type: $type, message: $message);
    }

    // ─── Shortcuts ─────────────────────────────────────────────

    public static function success(string $message): self
    {
        return new self(type: 'success', message: $message);
    }

    public static function error(string $message): self
    {
        return new self(type: 'error', message: $message);
    }

    public static function warning(string $message): self
    {
        return new self(type: 'warning', message: $message);
    }

    public static function info(string $message): self
    {
        return new self(type: 'info', message: $message);
    }

    // ─── Fluent modifiers (return new instance — immutable) ────

    public function title(?string $title): self
    {
        return new self($this->type, $this->message, $title, $this->duration, $this->icon, $this->position,
            $this->extra, $this->actions);
    }

    public function duration(?int $milliseconds): self
    {
        return new self($this->type, $this->message, $this->title, $milliseconds, $this->icon, $this->position,
            $this->extra, $this->actions);
    }

    /**
     * Mark the toast as sticky: it stays until dismissed (duration 0, no
     * countdown bar). Pass false to restore the default auto-dismiss.
     */
    public function persistent(bool $persistent = true): self
    {
        return $this->duration($persistent ? 0 : null);
    }

    public function icon(string|Icon|null $icon): self
    {
        $resolved = $icon instanceof Icon ? $icon->value() : $icon;

        return new self($this->type, $this->message, $this->title, $this->duration, $resolved, $this->position,
            $this->extra, $this->actions);
    }

    public function position(?string $position): self
    {
        return new self($this->type, $this->message, $this->title, $this->duration, $this->icon, $position,
            $this->extra, $this->actions);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function extra(array $extra): self
    {
        return new self($this->type, $this->message, $this->title, $this->duration, $this->icon, $this->position,
            array_merge($this->extra, $extra), $this->actions);
    }

    /**
     * Append an action button. Accepts a NotificationAction or the shorthand
     * label + Livewire event to dispatch on click.
     */
    public function action(NotificationAction|string $action, ?string $event = null): self
    {
        $resolved = $action instanceof NotificationAction
            ? $action
            : NotificationAction::make($action, (string) $event);

        return new self($this->type, $this->message, $this->title, $this->duration, $this->icon, $this->position,
            $this->extra, [...$this->actions, $resolved]);
    }

    /**
     * Replace the action button set.
     *
     * @param  array<array-key, NotificationAction>  $actions
     */
    public function actions(array $actions): self
    {
        return new self($this->type, $this->message, $this->title, $this->duration, $this->icon, $this->position,
            $this->extra, array_values($actions));
    }

    // ─── Serialization ─────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type,
            'message' => $this->message,
            'title' => $this->title,
            'duration' => $this->duration,
            'icon' => $this->icon,
            'position' => $this->position,
            'extra' => $this->extra ?: null,
            'actions' => $this->actions
                ? array_map(fn (NotificationAction $a) => $a->toArray(), $this->actions)
                : null,
        ], fn ($v) => $v !== null);
    }
}
