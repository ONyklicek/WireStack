<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

use NyonCode\WireCore\Foundation\Contracts\WritableStateBag;

/**
 * Canonical owner of "write this value at this dot-path on this host".
 *
 * The whole reason it is not `data_set()` is the nested-bag case: inside a
 * table action modal the state under the path is a `WritableStateBag`, and
 * `data_set()` walks straight past it and sets a property on the bag object,
 * so the write is silently lost. Every state write in the framework therefore
 * goes through here.
 *
 * This lived on `Core\State\StateContainer::writeInto()` and still answers
 * there — that is the name six call sites in wire-forms and the boost
 * guidelines know, and it stays the public entry point. What moved is the walk
 * itself, because `Foundation\Concerns\InteractsWithState` needs it for every
 * `$set()` in every evaluated closure, and Foundation may not import from the
 * engine. Asking for the contract instead of the container is what makes that
 * legal, and the behaviour is unchanged: the bag decides how to store the rest
 * of the path, exactly as before.
 */
final class StateWriter
{
    /**
     * Write `$value` at `$path` on `$host`, handing off to the first
     * `WritableStateBag` found along the way.
     *
     * A path that stops exactly at a bag replaces the whole bag; a non-array
     * value there is coerced to an empty array, since a bag's contents are
     * keyed state and nothing else.
     */
    public static function write(object $host, string $path, mixed $value): void
    {
        $segments = explode('.', $path);
        $current = $host;

        foreach ($segments as $index => $segment) {
            $child = match (true) {
                is_object($current) => $current->{$segment} ?? null,
                is_array($current) => $current[$segment] ?? null,
                default => null,
            };

            if ($child instanceof WritableStateBag) {
                $subPath = implode('.', array_slice($segments, $index + 1));

                if ($subPath === '') {
                    $child->replace(is_array($value) ? $value : []);
                } else {
                    $child->set($subPath, $value);
                }

                return;
            }

            $current = $child;
        }

        data_set($host, $path, $value);
    }
}
