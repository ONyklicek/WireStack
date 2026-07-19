<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Modals\Html;

use Illuminate\Contracts\Support\Htmlable;
use NyonCode\WireCore\Modals\Support\SlideOverStyle;

/**
 * Htmlable slide-over panel.
 *
 * The framework's Rule-5 way to render a slide-over without the
 * `<x-wire-modals::slide-over>` Blade component: build it in PHP and echo it with
 * `{{ $slideOver }}`. It implements {@see Htmlable} and owns exactly one Blade
 * view (Modal Best Practices Rule 5); the consumer-facing
 * `<x-wire-modals::slide-over>` tag stays available and renders the same shell +
 * {@see SlideOverStyle}.
 *
 * Body / footer accept a pre-rendered `string`/`Htmlable` or a partial + data to
 * `@include`. Lives in `Modals\Html\` — the Htmlable *render* objects — distinct
 * from the `Modals\SlideOver` *config* object and the
 * `Modals\View\SlideOverComponent` Blade component.
 */
final class SlideOver implements Htmlable
{
    /**
     * @param  array<string, mixed>  $bodyData
     * @param  array<string, mixed>  $footerData
     */
    public function __construct(
        public ?string $heading = null,
        public ?string $description = null,
        public string $width = 'md',
        public string $position = 'right',
        public ?string $maxHeight = null,
        public bool $closeOnClickAway = true,
        public bool $closeOnEscape = true,
        public bool $stickyFooter = false,
        public bool $stickyHeader = false,
        public ?string $id = null,
        public ?string $closeAction = null,
        public bool $bottomSheetOnMobile = false,
        public ?string $breakpoint = null,
        public ?int $zIndex = null,
        public ?string $wireModel = null,
        public string|Htmlable|null $body = null,
        public ?string $bodyView = null,
        public array $bodyData = [],
        public string|Htmlable|null $footer = null,
        public ?string $footerView = null,
        public array $footerData = [],
    ) {}

    public function toHtml(): string
    {
        return view('wire-core::modals.slide-over', [
            'style' => new SlideOverStyle(
                width: $this->width,
                position: $this->position,
                bottomSheetOnMobile: $this->bottomSheetOnMobile,
                breakpoint: $this->breakpoint,
            ),
            'heading' => $this->heading,
            'description' => $this->description,
            'maxHeight' => $this->maxHeight,
            'closeOnClickAway' => $this->closeOnClickAway,
            'closeOnEscape' => $this->closeOnEscape,
            'stickyFooter' => $this->stickyFooter,
            'stickyHeader' => $this->stickyHeader,
            'id' => $this->id,
            'closeAction' => $this->closeAction,
            'zIndex' => $this->zIndex,
            'wireModel' => $this->wireModel,
            'body' => $this->body instanceof Htmlable ? $this->body->toHtml() : $this->body,
            'bodyView' => $this->bodyView,
            'bodyData' => $this->bodyData,
            'footer' => $this->footer instanceof Htmlable ? $this->footer->toHtml() : $this->footer,
            'footerView' => $this->footerView,
            'footerData' => $this->footerData,
        ])->render();
    }

    public function __toString(): string
    {
        return $this->toHtml();
    }
}
