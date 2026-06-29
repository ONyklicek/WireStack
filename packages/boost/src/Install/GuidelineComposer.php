<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Install;

use Illuminate\Support\Facades\Blade;
use Symfony\Component\Finder\Finder;

/**
 * Gathers the wireStack guideline files (those shipped with the package plus any
 * project overrides in .ai/guidelines), renders them, and merges the resulting
 * block into an agent guideline file between stable markers so re-running the
 * installer is idempotent.
 */
class GuidelineComposer
{
    private const START_MARKER = '<!-- wire-boost:guidelines:start -->';

    private const END_MARKER = '<!-- wire-boost:guidelines:end -->';

    /**
     * @param  array<int, string>  $directories
     */
    public function __construct(private array $directories) {}

    public static function default(): self
    {
        return new self([
            dirname(__DIR__, 2).'/resources/boost/guidelines',
            base_path('.ai/guidelines'),
        ]);
    }

    /**
     * Render and concatenate every guideline document.
     */
    public function compose(): string
    {
        $directories = array_values(array_filter($this->directories, 'is_dir'));

        if ($directories === []) {
            return '';
        }

        $sections = [];

        foreach (Finder::create()->files()->in($directories)->name(['*.md', '*.blade.php'])->sortByName() as $file) {
            $contents = (string) file_get_contents($file->getPathname());

            $sections[] = str_ends_with($file->getFilename(), '.blade.php')
                ? trim(Blade::render($contents))
                : trim($contents);
        }

        return implode("\n\n", array_filter($sections));
    }

    /**
     * Merge the composed guidelines into a target file between markers.
     */
    public function installInto(string $path): void
    {
        $block = self::START_MARKER."\n".$this->compose()."\n".self::END_MARKER;
        $existing = is_file($path) ? (string) file_get_contents($path) : '';

        if ($existing !== '' && str_contains($existing, self::START_MARKER) && str_contains($existing, self::END_MARKER)) {
            $merged = (string) preg_replace(
                '/'.preg_quote(self::START_MARKER, '/').'.*?'.preg_quote(self::END_MARKER, '/').'/s',
                $block,
                $existing,
            );
        } else {
            $merged = $existing === '' ? $block."\n" : rtrim($existing)."\n\n".$block."\n";
        }

        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $merged);
    }
}
