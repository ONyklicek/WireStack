<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Contracts;

use Closure;

interface HasVisibility
{
    public function visible(bool|Closure $condition = true): static;

    public function hidden(bool|Closure $condition = true): static;

    public function disabled(bool|Closure $condition = true): static;

    public function isHidden(): bool;

    public function isVisible(): bool;

    public function isDisabled(): bool;
}
