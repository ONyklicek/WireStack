<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Console\Support;

use Illuminate\Support\Str;
use NyonCode\WireCore\Foundation\Console\Actions\ScaffoldComponent;

/**
 * What one `make:wire-{component}` run writes: a class, and — for a component
 * that renders — the Blade view it names.
 *
 * Every custom-component generator (column, filter, field, entry, action) is
 * this and nothing else: a base class to extend, a folder, a suffix, and a view
 * folder. The shape is data so the writing is one action,
 * {@see ScaffoldComponent}, rather than five commands each doing it slightly
 * differently.
 */
final class ComponentBlueprint
{
    /**
     * @param  string  $namespace  Below the application's root namespace, e.g. `Tables\Columns`.
     * @param  string  $suffix  Appended to the class unless the name already ends with it; `''` for none.
     * @param  string  $classStub  The class template's file name.
     * @param  string|null  $viewStub  The view template's file name; null for a component with no view.
     * @param  string|null  $viewFolder  Dot path below `resources/views`, e.g. `tables.columns`.
     */
    public function __construct(
        public readonly PublishedStubs $stubs,
        public readonly string $namespace,
        public readonly string $suffix,
        public readonly string $classStub,
        public readonly ?string $viewStub = null,
        public readonly ?string $viewFolder = null,
    ) {}

    /**
     * The class name for what was typed: `Money`, `money` and `MoneyColumn`
     * all give `MoneyColumn`, so the class reads as what it is wherever it
     * turns up in a schema.
     */
    public function className(string $input): string
    {
        $base = Str::studly(class_basename(str_replace('/', '\\', $input)));

        return $this->suffix === '' || str_ends_with($base, $this->suffix) ? $base : $base.$this->suffix;
    }

    /**
     * The name without its suffix — what the view file and the default
     * component name are derived from. Derived rather than asked for, for the
     * reason `Dashboard::key()` gives: a name written twice drifts.
     */
    public function baseName(string $input): string
    {
        $class = $this->className($input);

        return $this->suffix !== '' && $class !== $this->suffix ? Str::beforeLast($class, $this->suffix) : $class;
    }

    /** The dot view name the class renders, or null when it renders none. */
    public function viewName(string $input): ?string
    {
        return $this->viewStub === null || $this->viewFolder === null
            ? null
            : $this->viewFolder.'.'.Str::kebab($this->baseName($input));
    }
}
