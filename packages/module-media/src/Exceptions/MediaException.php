<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Exceptions;

use NyonCode\WireCore\Foundation\Contracts\WireException;
use RuntimeException;

/**
 * Something the library was asked to do and will not.
 *
 * Every case is about the state of the tree rather than a bad argument — a
 * folder moved inside itself, a name already taken beside it, a folder deleted
 * while it still holds files — so `RuntimeException` is the base, per ADR 0022.
 *
 * These are thrown rather than returned as false, and the manager catches them
 * to raise a notification. That split is the point: the model refuses in one
 * place and says why, and every surface that drives it — the component, a
 * console command, someone else's code — gets the same refusal instead of each
 * re-deriving the rule from a boolean.
 */
final class MediaException extends RuntimeException implements WireException
{
    public static function folderNameTaken(string $name): self
    {
        return new self(
            "A folder called [{$name}] is already here. ".
            'Two folders with one name in one place cannot be told apart in a breadcrumb, '.
            'so the name has to differ from its siblings — not from every folder in the library.'
        );
    }

    public static function folderMovedIntoItself(string $name): self
    {
        return new self(
            "[{$name}] cannot be moved inside itself or one of its own subfolders. ".
            'The tree would have no root from that branch down, and nothing in it could be reached again.'
        );
    }

    public static function folderNotEmpty(string $name, int $folders, int $files): self
    {
        return new self(
            "[{$name}] still holds {$folders} folder(s) and {$files} file(s), so it was not deleted. ".
            'Move or delete what is inside it first — deleting a folder must not be a way to lose files '.
            'without being asked about them.'
        );
    }

    public static function folderNameEmpty(): self
    {
        return new self(
            'A folder needs a name. An unnamed row would draw an empty rung in every breadcrumb below it.'
        );
    }
}
