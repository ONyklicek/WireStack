<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Icons\Icon;

/**
 * Immutable value object representing a notification.
 *
 * Carries all metadata a notification driver might need:
 * type, message, title, duration, actions, and arbitrary extra data.
 *
 * ─── Who it is for ────────────────────────────────────────────
 *
 * Nobody, usually: the transient drivers deliver to the request being rendered,
 * and the recipient-aware ones ask {@see Contracts\ResolvesNotifiable} — whoever
 * is authenticated. `->to($user)` is for the case that cannot answer, which is
 * also the case the stored drivers exist for: a queued job finishing at three in
 * the morning has nobody logged in, and the user it concerns is an argument it
 * was given rather than a session it is inside.
 *
 *   Notification::success('Your export is ready')
 *       ->to($this->user)
 *       ->action(NotificationAction::link('Download', $url));
 *
 * The recipient is deliberately **not** part of {@see toArray()}. That array is
 * the stored payload — what the notification says — while the recipient is which
 * row it is stored in, and mixing the two would put a user id inside the JSON
 * every reader treats as content.
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
        public readonly ?string $url = null,
        public readonly ?Model $notifiable = null,
    ) {}

    /**
     * One field changed, everything else carried over.
     *
     * Every modifier below used to re-list all eight constructor arguments
     * positionally, which is fine until there are ten of them: adding one means
     * editing ten call sites correctly, and the one that is wrong swaps two
     * arguments of the same type and passes every test that does not happen to
     * set both. Named arguments spread from an array, so this is the same
     * construction with the mistake taken out of it.
     *
     * @param  mixed  ...$overrides  keyed by constructor parameter name
     */
    private function copy(mixed ...$overrides): self
    {
        return new self(...array_replace([
            'type' => $this->type,
            'message' => $this->message,
            'title' => $this->title,
            'duration' => $this->duration,
            'icon' => $this->icon,
            'position' => $this->position,
            'extra' => $this->extra,
            'actions' => $this->actions,
            'url' => $this->url,
            'notifiable' => $this->notifiable,
        ], $overrides));
    }

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
        return $this->copy(title: $title);
    }

    public function duration(?int $milliseconds): self
    {
        return $this->copy(duration: $milliseconds);
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
        return $this->copy(icon: $icon instanceof Icon ? $icon->value() : $icon);
    }

    public function position(?string $position): self
    {
        return $this->copy(position: $position);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function extra(array $extra): self
    {
        return $this->copy(extra: array_merge($this->extra, $extra));
    }

    /**
     * Where this notification points — the invoice, not the notification.
     *
     * Read by any surface that can navigate: the bell's panel makes the row a
     * link to it, and opening it marks the notification read on the way past.
     * Stored with the payload, because a notification whose link died with the
     * request that raised it is a notification nobody can act on.
     */
    public function url(?string $url): self
    {
        return $this->copy(url: $url);
    }

    /**
     * Who this notification is for — see the class note.
     *
     * Ignored by the transient drivers, which have exactly one recipient: the
     * page being rendered. `DatabaseDriver` and `BroadcastDriver` prefer it over
     * the resolver, so one job can address several people in a loop without
     * rebinding anything.
     */
    public function to(?Model $notifiable): self
    {
        return $this->copy(notifiable: $notifiable);
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

        return $this->copy(actions: [...$this->actions, $resolved]);
    }

    /**
     * Replace the action button set.
     *
     * @param  array<array-key, NotificationAction>  $actions
     */
    public function actions(array $actions): self
    {
        return $this->copy(actions: array_values($actions));
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
            'url' => $this->url,
            'extra' => $this->extra ?: null,
            'actions' => $this->actions
                ? array_map(fn (NotificationAction $a) => $a->toArray(), $this->actions)
                : null,
        ], fn ($v) => $v !== null);
    }
}
