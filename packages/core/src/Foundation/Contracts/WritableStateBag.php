<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Contracts;

/**
 * A nested state bag that owns its own writes.
 *
 * `data_set()` cannot write through one of these — it would walk past the bag
 * and set a property on it, silently dropping the value instead of storing it.
 * So anything writing into a host by dot-path has to stop when it meets a bag
 * and hand the rest of the path over. That is what this contract is for, and
 * `Foundation\Support\StateWriter` is what consults it.
 *
 * The only implementation today is `Core\State\StateContainer`, the engine's
 * synthesized state bag. The contract exists so that the write logic does not
 * have to name it: the walk belongs to Foundation, the bag belongs to the
 * engine, and Foundation may not import from the engine.
 */
interface WritableStateBag
{
    /**
     * Write a value at a dot-path relative to this bag.
     */
    public function set(string $path, mixed $value): void;

    /**
     * Replace the bag's entire contents.
     *
     * @param  array<string, mixed>  $state
     */
    public function replace(array $state): void;
}
