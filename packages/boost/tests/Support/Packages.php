<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Tests\Support;

use NyonCode\WireBoost\Support\WirePackages;

/**
 * The installed-package sets the install tests run against, said once.
 *
 * A class, not functions in a test file: a function declared in one test file
 * exists only once that file has been loaded, so another file calling it passed
 * or failed on the order the runner happened to load them in. Pest loads the
 * root `Pest.php` and not the package's, so it cannot live there either.
 */
final class Packages
{
    /** A WirePackages reporting exactly the named packages as installed. */
    public static function installed(string ...$names): WirePackages
    {
        $versions = [];

        foreach ($names as $name) {
            $versions[WirePackages::composerName($name)] = '2.0.0';
        }

        return new WirePackages($versions);
    }

    /** Everything the stack has — the shape the monorepo itself is in. */
    public static function every(): WirePackages
    {
        return self::installed(...WirePackages::all());
    }
}
