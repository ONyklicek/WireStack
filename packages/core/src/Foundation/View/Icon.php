<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use NyonCode\WireCore\Foundation\Icons\IconManager;

/**
 * Blade component: <x-wire::icon name="pencil" class="w-5 h-5" />
 *
 * Pass a `label` to expose the icon to assistive tech (`role="img"`); without
 * one the icon is rendered as decorative (`aria-hidden="true"`). Any extra
 * attribute (Alpine `:class`, `x-show`, `data-*`, `@click`) is forwarded onto
 * the `<svg>` root, so a single `<x-wire::icon>` can carry dynamic behaviour
 * without falling back to a hand-written inline `<svg>`.
 */
class Icon extends Component
{
    public function __construct(
        protected readonly IconManager $manager,
        public string $name,
        public string $size = 'w-4 h-4',
        public string $class = '',
        public string $label = '',
    ) {}

    public function render(): View
    {
        $icon = $this->manager->resolved($this->name);

        return view('wire-core::foundation.icon', [
            'body' => $icon->body,
            'viewBox' => $icon->viewBox,
            'styleAttributes' => $icon->attributes,
            'classes' => trim($this->size.' '.$this->class),
            'label' => $this->label,
        ]);
    }
}
