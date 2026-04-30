<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use Illuminate\View\Component;
use NyonCode\WireCore\Foundation\Icons\IconManager;

/**
 * Blade component: <x-wire::icon name="pencil" class="w-5 h-5" />
 */
class Icon extends Component
{
    public string $svg;

    public function __construct(
        public string $name,
        public string $size = 'w-4 h-4',
        public string $class = '',
    ) {
        /** @var IconManager $manager */
        $manager = app(IconManager::class);
        $this->svg = $manager->render($name, $size, $class);
    }

    public function render(): string
    {
        return '{!! $svg !!}';
    }
}
