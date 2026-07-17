<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Tests\Fixtures;

/**
 * A table() that says nothing about itself — no return type, no typed parameter.
 * Nothing here identifies a wire builder, so it must not be treated as one.
 */
class LooseTable
{
    public function table($anything)
    {
        return $anything;
    }
}
