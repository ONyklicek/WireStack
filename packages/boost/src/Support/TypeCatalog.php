<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Support;

use Illuminate\Support\Str;
use NyonCode\WireBoost\Exceptions\UnresolvableComponentException;
use NyonCode\WireCore\Actions\BaseAction;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireCore\Infolists\Components\Entry;
use NyonCode\WireCore\Modals\Modal;
use NyonCode\WireCore\Panels\Components\EditableEntry;
use NyonCode\WireCore\Widgets\Widget;
use NyonCode\WireForms\Components\Display\Display;
use NyonCode\WireForms\Components\Field;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Filters\Filter;
use ReflectionClass;

/**
 * Discovers the built-in wireStack component vocabulary (column, field, filter,
 * action, entry, widget and modal types) by reflecting the concrete subclasses
 * shipped next to each canonical base class. Categories whose package is not
 * installed simply resolve to an empty list, so the catalog degrades safely.
 */
class TypeCatalog
{
    /**
     * Category => canonical base class.
     *
     * @var array<string, class-string>
     */
    public const CATEGORIES = [
        'columns' => Column::class,
        'filters' => Filter::class,
        'fields' => Field::class,
        'displays' => Display::class,
        'actions' => BaseAction::class,
        'infolist-entries' => Entry::class,
        // Panel entries extend the infolist Entry but ship from a sibling
        // directory, so the scan of Infolists/Components never sees them.
        'panel-entries' => EditableEntry::class,
        'widgets' => Widget::class,
        'modals' => Modal::class,
        'layouts' => LayoutComponent::class,
    ];

    /**
     * @return array<int, string>
     */
    public function categories(): array
    {
        return array_keys(self::CATEGORIES);
    }

    public function has(string $category): bool
    {
        return isset(self::CATEGORIES[$category]);
    }

    /**
     * Concrete component types registered under a category.
     *
     * @return array<int, array{name: string, class: class-string, summary: string}>
     */
    public function types(string $category): array
    {
        $base = self::CATEGORIES[$category] ?? null;

        if ($base === null || ! class_exists($base)) {
            return [];
        }

        $reflection = new ReflectionClass($base);
        [$directory, $namespace] = $this->scanRoot(
            $category,
            (string) $reflection->getFileName(),
            $reflection->getNamespaceName(),
        );

        $types = [];

        foreach (glob($directory.'/*.php') ?: [] as $file) {
            /** @var class-string $class */
            $class = $namespace.'\\'.basename($file, '.php');

            // Every file in a base-class directory maps to a loadable class.
            // @codeCoverageIgnoreStart
            if (! class_exists($class)) {
                continue;
            }
            // @codeCoverageIgnoreEnd

            $candidate = new ReflectionClass($class);

            if ($candidate->isAbstract() || ! $candidate->isSubclassOf($base)) {
                continue;
            }

            $types[] = [
                'name' => Str::kebab($candidate->getShortName()),
                'class' => $class,
                'summary' => $this->summary($candidate->getDocComment(), $candidate->getShortName()),
            ];
        }

        usort($types, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $types;
    }

    /**
     * Directory + namespace to scan for a category's concrete types.
     *
     * Most categories ship their concrete types next to the canonical base
     * class. Layout components are the exception: they extend LayoutComponent
     * (Foundation/Components) but live in the sibling Foundation/Schema
     * directory, so scan there instead.
     *
     * @return array{0: string, 1: string}
     */
    private function scanRoot(string $category, string $baseFile, string $baseNamespace): array
    {
        if ($category === 'layouts') {
            return [
                dirname(dirname($baseFile)).'/Schema',
                Str::beforeLast($baseNamespace, '\\').'\\Schema',
            ];
        }

        return [dirname($baseFile), $baseNamespace];
    }

    /**
     * Resolve a component type, or fail saying so.
     *
     * The throwing counterpart of {@see self::resolve()}: use this when the
     * caller has no sensible answer for "not found", so the failure travels to
     * the boundary instead of being re-described at every call site.
     *
     * @return class-string
     *
     * @throws UnresolvableComponentException
     */
    public function resolveOrFail(string $name): string
    {
        return $this->resolve($name) ?? throw UnresolvableComponentException::unknownType($name);
    }

    /**
     * Resolve a component type to its fully-qualified class name, accepting an
     * exact FQCN or a built-in type short name (e.g. "text-column").
     *
     * Returns null rather than throwing: this is a query, and callers such as
     * the catalog's own listing legitimately ask about names that may not exist.
     *
     * @return class-string|null
     */
    public function resolve(string $name): ?string
    {
        if (class_exists($name)) {
            /** @var class-string $name */
            return $name;
        }

        $needle = ltrim($name, '\\');

        foreach ($this->categories() as $category) {
            foreach ($this->types($category) as $type) {
                if ($type['name'] === $needle || class_basename($type['class']) === $needle) {
                    return $type['class'];
                }
            }
        }

        return null;
    }

    /**
     * First meaningful line of the class doc-block, or a humanised class name.
     */
    private function summary(string|false $doc, string $shortName): string
    {
        if (is_string($doc)) {
            foreach (preg_split('/\R/', $doc) ?: [] as $raw) {
                $line = trim(ltrim(trim($raw), '/* '));

                if ($line !== '' && ! str_starts_with($line, '@')) {
                    return $line;
                }
            }
        }

        return Str::headline($shortName);
    }
}
