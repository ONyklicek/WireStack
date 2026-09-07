<?php

declare(strict_types=1);

namespace NyonCode\Wire\Install;

/**
 * One installable part of the stack, as the installer sees it.
 *
 * A value object rather than an array, because three questions are asked of
 * every part and an array answers them by convention: what is it called, is it
 * here, and what installs it.
 */
final readonly class Component
{
    /**
     * @param  string  $package  The composer name, which is what a person types to get it.
     * @param  string  $marker  A class that exists only when the package is installed.
     * @param  string|null  $command  Its own installer, when it has one.
     */
    public function __construct(
        public string $package,
        public string $label,
        public string $description,
        public string $marker,
        public ?string $command = null,
        public bool $core = false,
    ) {}

    public function installed(): bool
    {
        return class_exists($this->marker);
    }
}
