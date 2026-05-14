<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Modals\Concerns;

use NyonCode\WireCore\Core\Support\Trans;

/**
 * Provides submit/cancel button labels and sticky footer support.
 */
trait HasFooterActions
{
    protected ?string $submitLabel = null;

    protected ?string $cancelLabel = null;

    protected bool $stickyFooter = false;

    protected bool $stickyHeader = false;

    public function submitLabel(?string $label): static
    {
        $this->submitLabel = $label;

        return $this;
    }

    public function cancelLabel(?string $label): static
    {
        $this->cancelLabel = $label;

        return $this;
    }

    public function stickyFooter(bool $sticky = true): static
    {
        $this->stickyFooter = $sticky;

        return $this;
    }

    public function stickyHeader(bool $sticky = true): static
    {
        $this->stickyHeader = $sticky;

        return $this;
    }

    public function getSubmitLabel(): string
    {
        return $this->submitLabel ?? Trans::get('wire-core::actions.confirm_submit');
    }

    public function getCancelLabel(): string
    {
        return $this->cancelLabel ?? Trans::get('wire-core::actions.confirm_cancel');
    }

    public function hasStickyFooter(): bool
    {
        return $this->stickyFooter;
    }

    public function hasStickyHeader(): bool
    {
        return $this->stickyHeader;
    }
}
