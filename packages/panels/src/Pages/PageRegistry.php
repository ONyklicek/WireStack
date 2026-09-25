<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Pages;

use NyonCode\WireCore\Foundation\Registration\ClassDiscovery;
use NyonCode\WireCore\Foundation\Registration\Contracts\RegistrySource;
use NyonCode\WirePanels\Exceptions\PageRegistrationException;

/**
 * The application's own pages, registered the way resources are.
 *
 * A source of the catalogue, so a registered {@see Page} is what a resource is to
 * the three readers: `Route::wireResources()` gives it a URL, the menu an entry,
 * `wire:resources` a line. Filled from `config('wire-panels.pages')` and from the
 * folders `config('wire-core.discover.pages')` names, **on the first read** —
 * not at boot — because core registers config-declared routes from its own boot,
 * before this package's has run, and a registry filled later would be empty
 * when it is asked.
 */
final class PageRegistry implements RegistrySource
{
    /** @var array<string, class-string<Page>> */
    private array $pages = [];

    private bool $loaded = false;

    public function __construct(private readonly ClassDiscovery $discovery) {}

    /**
     * @param  class-string  $page
     *
     * @throws PageRegistrationException When it is not a page, or its key is another page's.
     */
    public function register(string $page): void
    {
        if (! is_subclass_of($page, Page::class)) {
            throw PageRegistrationException::notAPage($page);
        }

        $key = $page::key();
        $existing = $this->pages[$key] ?? null;

        // Twice is a no-op — a page listed and discovered — but two pages on one
        // key is the later silently taking the earlier's URL and menu entry.
        if ($existing !== null && $existing !== $page) {
            throw PageRegistrationException::duplicateKey($key, $existing, $page);
        }

        $this->pages[$key] = $page;
    }

    /** Register whatever a config list held; a malformed entry is skipped, not fatal. */
    public function registerMany(mixed $pages): void
    {
        foreach (is_array($pages) ? $pages : [] as $page) {
            if (is_string($page) && $page !== '') {
                $this->register($page);
            }
        }
    }

    /** @return array<string, class-string<Page>> */
    public function all(): array
    {
        $this->load();

        return $this->pages;
    }

    /** @return array<string, class-string<Page>> */
    public function registeredClasses(): array
    {
        return $this->all();
    }

    /** The configured and discovered pages, once. */
    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        $this->registerMany(config('wire-panels.pages', []));

        $folders = config('wire-core.discover.pages', []);

        foreach (is_array($folders) ? $folders : [] as $namespace => $directory) {
            if (is_string($namespace) && is_string($directory)) {
                $this->registerMany($this->discovery->in($directory, $namespace, Page::class));
            }
        }
    }
}
