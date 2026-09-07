<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Foundation\Concerns\HasSize;

/**
 * Blade component: <x-wire::button color="danger" size="sm">Delete</x-wire::button>
 *
 * The plain button, for Blade that is not rendering an action. Same vocabulary
 * as everything else — `primary`, `danger`, `success`, and the hue names beside
 * them — because it resolves through the canonical owners rather than carrying a
 * palette of its own: {@see HasColor} for the colour, {@see HasSize} for the
 * padding and the type scale, which is what the action button renderer reaches
 * for too. Named in prose rather than linked, deliberately: this file is in the
 * lowest layer and must not point at one above it (ADR 0025).
 *
 * `color`, `size` and `outlined` used to be constructor arguments that reached
 * the view and were never read, so `color="danger"` rendered white text on
 * nothing — invisible, on precisely the buttons where being seen matters most.
 * The props were documented; the resolution was missing.
 */
class Button extends Component
{
    use HasColor;

    public function __construct(
        public string $color = 'primary',
        public string $size = 'sm',
        public bool $outlined = false,
        public ?string $icon = null,
        public ?string $iconPosition = 'before',
        public ?string $href = null,
        public bool $disabled = false,
        public string $type = 'button',
    ) {}

    /**
     * What {@see HasColor} asks the host for when no colour is passed.
     *
     * The trait is written for the fluent components, where the colour is set
     * through `->color()` and read back through this. Here it is a constructor
     * prop, and this is the one line that makes the same resolvers work over it.
     */
    public function getColor(): string
    {
        return $this->color;
    }

    /**
     * The colour and size classes, resolved once per render.
     *
     * Deliberately not merged into the view's own `class` default: a caller that
     * passes `class="w-full"` must add to these, and Blade's attribute merge is
     * what does that.
     */
    public function classes(): string
    {
        $color = $this->outlined
            ? $this->getOutlinedColorClasses()
            : $this->getSolidColorClasses();

        // The size resolver reached statically rather than by taking the trait:
        // HasSize declares a `$size` property of its own, and this component's is
        // a public constructor prop. The same static call the action renderer
        // makes.
        return trim($color.' '.HasSize::getButtonSizeClasses($this->size, iconOnly: false));
    }

    public function render(): View
    {
        return view('wire-core::foundation.button');
    }
}
