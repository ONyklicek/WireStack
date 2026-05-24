<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets\Contracts;

interface HasWidgets
{
    /**
     * @return array<int, \NyonCode\WireCore\Widgets\Widget>
     */
    public function getWidgets(): array;
}
