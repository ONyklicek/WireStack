<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Contracts;

use Closure;

interface HasIcon
{
    public function icon(string|Closure|null $icon, ?string $position = null): static;

    public function getIcon(): ?string;
}
