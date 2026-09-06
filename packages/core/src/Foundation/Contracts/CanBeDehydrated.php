<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Contracts;

/**
 * Marks a component that decides whether its state reaches the record at all.
 *
 * The question {@see DehydratesState} does not answer: that one shapes a value
 * on the way out, this one says whether there is a way out. A component keyed by
 * something that is not a column — a relation name, a confirmation input, a
 * value computed only to drive a sibling — has a name in the form payload and no
 * column behind it, and writing it fatals on the first save.
 *
 * Declared rather than enumerated: before this contract the save handler carried
 * one hardcoded branch per known case (relationship repeaters, relationship
 * tags, morph selects, fields that save themselves), so a field from a package
 * the handler had never heard of had no way to opt out.
 */
interface CanBeDehydrated
{
    /** Whether this component's state is written to the record when the form saves. */
    public function isDehydrated(): bool;
}
