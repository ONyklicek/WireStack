<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Hydration;

use Closure;

/**
 * Pipeline for applying before/after mutations on attribute values.
 */
final class MutationPipeline
{
    /** @var array<int, Closure(mixed, string): mixed> */
    private array $beforeMutations = [];

    /** @var array<int, Closure(mixed, string): mixed> */
    private array $afterMutations = [];

    /**
     * Register mutations to apply before setting a value.
     *
     * @param  Closure(mixed, string): mixed  ...$mutations
     */
    public function before(Closure ...$mutations): self
    {
        foreach ($mutations as $mutation) {
            $this->beforeMutations[] = $mutation;
        }

        return $this;
    }

    /**
     * Register mutations to apply after getting a value.
     *
     * @param  Closure(mixed, string): mixed  ...$mutations
     */
    public function after(Closure ...$mutations): self
    {
        foreach ($mutations as $mutation) {
            $this->afterMutations[] = $mutation;
        }

        return $this;
    }

    /**
     * Apply all registered before-set mutations to a value.
     */
    public function applyBefore(mixed $value, string $attribute): mixed
    {
        foreach ($this->beforeMutations as $mutation) {
            $value = $mutation($value, $attribute);
        }

        return $value;
    }

    /**
     * Apply all registered after-get mutations to a value.
     */
    public function applyAfter(mixed $value, string $attribute): mixed
    {
        foreach ($this->afterMutations as $mutation) {
            $value = $mutation($value, $attribute);
        }

        return $value;
    }
}
