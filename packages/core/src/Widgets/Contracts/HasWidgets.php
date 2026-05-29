<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets\Contracts;

use NyonCode\WireCore\Widgets\Widget;

interface HasWidgets
{
    /**
     * @return array<int, Widget>
     */
    public function getWidgets(): array;
}
