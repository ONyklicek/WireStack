<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;

/**
 * Thrown when an asset is asked for a URL it cannot build, or looked up under a
 * package/id nothing was registered under.
 *
 * Both are wiring mistakes made once at boot, so the messages name the call that
 * fixes them — the caller has no other feedback loop.
 */
final class AssetRegistrationException extends InvalidArgumentException implements WireException
{
    public static function notRegistered(string $id): self
    {
        return new self(
            "Asset [{$id}] has no owning package, so its URL cannot be resolved. Register "
            .'it first — app(AssetManager::class)->register([$asset], \'wire-core\') — which '
            .'binds the asset to the package whose asset route serves it.'
        );
    }

    public static function unknown(string $package, string $id): self
    {
        return new self(
            "No asset [{$id}] is registered for package [{$package}]. Register it in the "
            ."package provider's bootedPackage() callback, e.g. register([Js::make('{$id}', "
            ."\$path)], '{$package}')."
        );
    }
}
