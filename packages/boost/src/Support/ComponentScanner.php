<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Support;

use Livewire\Component as LivewireComponent;
use NyonCode\WireForms\Forms\WithForms;
use NyonCode\WireTable\Concerns\WithTable;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Scans the application for Livewire components that build wireStack tables,
 * forms or infolists, or that extend a wire widget. Each match is classified by
 * the wire capability it exposes so an agent can locate the relevant component.
 */
class ComponentScanner
{
    /**
     * Discover wire-powered components beneath the configured scan paths.
     *
     * @param  array<int, string>|null  $paths
     * @return array<int, array{class: class-string, file: string, kinds: array<int, string>}>
     */
    public function scan(?array $paths = null): array
    {
        $paths = array_values(array_filter(
            $paths ?? (array) config('wire-boost.scan.paths', []),
            static fn (string $path): bool => is_dir($path),
        ));

        if ($paths === []) {
            return [];
        }

        $components = [];

        foreach (Finder::create()->files()->in($paths)->name('*.php') as $file) {
            $class = $this->classFromFile($file);

            if ($class === null || ! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(LivewireComponent::class)) {
                continue;
            }

            $kinds = $this->kinds($reflection);

            if ($kinds === []) {
                continue;
            }

            $components[] = [
                'class' => $class,
                'file' => $file->getRealPath() ?: $file->getPathname(),
                'kinds' => $kinds,
            ];
        }

        usort($components, static fn (array $a, array $b): int => strcmp($a['class'], $b['class']));

        return $components;
    }

    /**
     * @param  ReflectionClass<LivewireComponent>  $reflection
     * @return array<int, string>
     */
    private function kinds(ReflectionClass $reflection): array
    {
        $traits = $this->allTraits($reflection);
        $kinds = [];

        if (in_array(WithTable::class, $traits, true) || $reflection->hasMethod('table')) {
            $kinds[] = 'table';
        }

        if (in_array(WithForms::class, $traits, true) || $reflection->hasMethod('form')) {
            $kinds[] = 'form';
        }

        if ($reflection->hasMethod('infolist')) {
            $kinds[] = 'infolist';
        }

        return $kinds;
    }

    /**
     * @param  ReflectionClass<LivewireComponent>  $reflection
     * @return array<int, string>
     */
    private function allTraits(ReflectionClass $reflection): array
    {
        $traits = [];

        for ($class = $reflection; $class !== false; $class = $class->getParentClass()) {
            $traits = array_merge($traits, array_keys($class->getTraits()));
        }

        return $traits;
    }

    /**
     * Resolve the fully-qualified class name declared in a PHP file.
     *
     * @return class-string|null
     */
    private function classFromFile(SplFileInfo $file): ?string
    {
        $contents = (string) file_get_contents($file->getPathname());
        $namespace = '';
        $class = null;
        $tokens = token_get_all($contents);

        foreach ($tokens as $index => $token) {
            if (! is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = $this->readName($tokens, $index + 1);
            }

            if ($token[0] === T_CLASS) {
                $next = $tokens[$index + 2] ?? null;

                if (is_array($next)) {
                    $class = $next[1];
                    break;
                }
            }
        }

        if ($class === null) {
            return null;
        }

        /** @var class-string $fqcn */
        $fqcn = $namespace !== '' ? $namespace.'\\'.$class : $class;

        return $fqcn;
    }

    /**
     * @param  array<int, array{0: int, 1: string}|string>  $tokens
     */
    private function readName(array $tokens, int $start): string
    {
        $name = '';

        for ($i = $start, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                $name .= $token[1];

                continue;
            }

            if (is_array($token) && $token[0] === T_WHITESPACE) {
                continue;
            }

            break;
        }

        return trim($name, '\\');
    }
}
