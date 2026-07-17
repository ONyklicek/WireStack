<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Tests\Fixtures;

use NyonCode\WireCore\Foundation\Enums\Size;

/**
 * Every shape of parameter default the reflector has to render.
 */
class ApiSurface
{
    /**
     * Documented method.
     */
    public function documented(): static
    {
        return $this;
    }

    public function undocumented(): static
    {
        return $this;
    }

    public function defaults(
        ?string $nothing = null,
        bool $yes = true,
        bool $no = false,
        string $text = 'hello',
        int $number = 5,
        float $ratio = 1.5,
        array $empty = [],
        array $filled = ['a'],
        Size $size = Size::Md,
    ): static {
        return $this;
    }

    public function required(string $name): static
    {
        return $this;
    }

    public function variadic(string ...$names): static
    {
        return $this;
    }

    public function untyped($whatever = 'x'): static
    {
        return $this;
    }

    public function notFluent(): string
    {
        return 'nope';
    }
}
