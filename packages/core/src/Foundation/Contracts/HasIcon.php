<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Contracts;

use Closure;
use NyonCode\WireCore\Foundation\Icons\Icon;

interface HasIcon
{
    public function icon(string|Icon|Closure|null $icon, ?string $position = null): static;

    public function getIcon(): ?string;
}
