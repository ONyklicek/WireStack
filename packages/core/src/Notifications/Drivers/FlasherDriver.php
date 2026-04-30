<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications\Drivers;

use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Notification;

class FlasherDriver implements NotificationDriver
{
    public function __construct(
        protected ?string $adapter = null,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function send(Notification $notification, mixed $livewireComponent = null): void
    {
        if (! function_exists('flash')) {
            // Flasher not installed — silent fallback to session
            session()->flash('table-notification', $notification->toArray());

            return;
        }

        $flasher = $this->adapter ? flash()->{$this->adapter}() : flash();

        $type = $this->mapType($notification->type);

        $options = [];

        if ($notification->duration !== null) {
            $options['timeout'] = $notification->duration;
        }

        if ($notification->position !== null) {
            $options['position'] = $notification->position;
        }

        if ($notification->title !== null) {
            $flasher->option('title', $notification->title);
        }

        if (! empty($options)) {
            $flasher->options($options);
        }

        $flasher->addFlash($type, $notification->message);
    }

    /**
     * Map notification types to Flasher types.
     */
    protected function mapType(string $type): string
    {
        return match ($type) {
            'success' => 'success',
            'error', 'danger' => 'error',
            'warning' => 'warning',
            'info' => 'info',
            default => 'info',
        };
    }
}
