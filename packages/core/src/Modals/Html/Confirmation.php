<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Modals\Html;

use Illuminate\Contracts\Support\Htmlable;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireCore\Modals\Support\ConfirmationStyle;

/**
 * Htmlable confirmation dialog.
 *
 * The framework's Rule-5 way to render a confirmation without the
 * `<x-wire-modals::confirmation>` Blade component: build it in PHP and echo it
 * with `{{ $confirmation }}`. It implements {@see Htmlable} and owns exactly one
 * Blade view (Modal Best Practices Rule 5), so the modal is a first-class value
 * object — no `<x-*>` dependency in the framework's own render paths. The
 * consumer-facing `<x-wire-modals::confirmation>` tag stays available and renders
 * the same shell + {@see ConfirmationStyle}.
 *
 * Lives in `Modals\Html\` — the Htmlable *render* objects — distinct from the
 * `Modals\*` modal *config* objects (`Modals\Modal`, a `ModalContract`) and the
 * `Modals\View\*Component` Blade components.
 */
final class Confirmation implements Htmlable
{
    public function __construct(
        public ?string $heading = null,
        public ?string $description = null,
        public string $width = 'md',
        public ?string $icon = null,
        public string $iconColor = 'warning',
        public ?string $submitLabel = null,
        public ?string $cancelLabel = null,
        public ?string $color = null,
        public bool $isDanger = false,
        public bool $isInformative = false,
        public bool $closeOnClickAway = true,
        public bool $closeOnEscape = true,
        public ?string $id = null,
        public ?string $closeAction = null,
        public ?int $zIndex = null,
        public ?string $wireModel = null,
        public ?string $wireClick = null,
        public string|Htmlable|null $body = null,
        /** @var array<int, array<string, mixed>> Additional footer actions (Action API). */
        public array $footerActions = [],
    ) {
        $this->submitLabel ??= Trans::get('wire-core::actions.confirm_submit');
        $this->cancelLabel ??= Trans::get('wire-core::actions.confirm_cancel');

        if ($this->isDanger && $this->color === null) {
            $this->color = 'danger';
        }
    }

    public function toHtml(): string
    {
        return view('wire-core::modals.confirmation', [
            'style' => new ConfirmationStyle($this->width, $this->iconColor, $this->color),
            'heading' => $this->heading,
            'description' => $this->description,
            'icon' => $this->icon,
            'isInformative' => $this->isInformative,
            'submitLabel' => $this->submitLabel,
            'cancelLabel' => $this->cancelLabel,
            'closeOnClickAway' => $this->closeOnClickAway,
            'closeOnEscape' => $this->closeOnEscape,
            'id' => $this->id,
            'closeAction' => $this->closeAction,
            'zIndex' => $this->zIndex,
            'wireModel' => $this->wireModel,
            'wireClick' => $this->wireClick,
            'body' => $this->body instanceof Htmlable ? $this->body->toHtml() : $this->body,
            'footerActions' => $this->footerActions,
        ])->render();
    }

    public function __toString(): string
    {
        return $this->toHtml();
    }
}
