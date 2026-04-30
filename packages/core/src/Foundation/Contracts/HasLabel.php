<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Contracts;

use Closure;

interface HasLabel
{
    public function label(string|Closure|null $label): static;

    public function getLabel(): ?string;
}
