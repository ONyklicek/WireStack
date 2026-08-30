<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Exceptions;

use NyonCode\WireCore\Foundation\Contracts\WireException;
use RuntimeException;

/**
 * A resource that cannot be put in the registry.
 *
 * Both cases are declaration errors caught at boot, when the config is read —
 * early enough that neither can reach a request.
 *
 * The contract's name arrives as a string rather than being imported: this is
 * L0, which may name nothing above it, and `Core\Resources` is L1. The caller
 * passes `DescribesResource::class`, so the reference stays compile-checked at
 * the end that is allowed to make it.
 */
final class ResourceRegistrationException extends RuntimeException implements WireException
{
    public static function notAResource(string $class, string $contract): self
    {
        return new self(
            "[{$class}] cannot be registered as a resource: it does not implement ".
            "[{$contract}]. A resource declares its key, model and labels through ".
            'that contract, which is what the registry routes on.'
        );
    }

    public static function duplicateResourceKey(string $key, string $existing, string $incoming): self
    {
        return new self(
            "Two resources claim the key [{$key}]: [{$existing}] and [{$incoming}]. ".
            'A key is the config handle, the route segment and the introspection '.
            'name, so the second would silently take over routing for the first. '.
            'Override key() on one of them.'
        );
    }
}
