<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Modals\Contracts;

/**
 * Contract for modal configuration objects.
 *
 * Implemented by Modal, ConfirmationDialog, SlideOver, and Wizard
 * to provide a consistent configuration API.
 */
interface ModalContract
{
    public function getHeading(): ?string;

    public function getDescription(): ?string;

    public function getWidth(): string;

    public function shouldCloseOnClickAway(): bool;

    public function shouldCloseOnEscape(): bool;

    /**
     * Serialize modal configuration for frontend rendering.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
