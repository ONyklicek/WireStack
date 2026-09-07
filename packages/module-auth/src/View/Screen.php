<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use NyonCode\WireModuleAuth\Support\Frame;

/**
 * One signed-out screen: `<x-wire-module-auth::screen :heading="…">`.
 *
 * The seam between a screen and the frame it renders inside, and the reason
 * there is exactly one of them. Seven views each opening with a
 * `<x-dynamic-component>` would be seven copies of the rule in {@see Frame},
 * and the rule is the part that changes.
 *
 * It carries the heading and the sentence under it because those belong to the
 * screen and the frame draws neither: the shell's frame is a card and a brand,
 * which is what makes it usable by an application rendering Fortify's own views
 * or Breeze's beside these.
 */
class Screen extends Component
{
    public function __construct(
        public ?string $title = null,
        public ?string $heading = null,
        public ?string $description = null,
    ) {}

    /**
     * The layout component to render inside.
     *
     * A method rather than a constructor-resolved property: resolution reads
     * config and can throw, and a component whose constructor throws fails
     * during Blade's compilation of the *parent* view, where the message lands
     * nowhere useful.
     */
    public function layout(): string
    {
        return Frame::component();
    }

    public function render(): View
    {
        return view('wire-module-auth::screen');
    }
}
