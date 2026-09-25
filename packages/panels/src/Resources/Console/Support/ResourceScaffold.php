<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Console\Support;

use Illuminate\Support\Str;

/**
 * Everything `make:wire-resource` writes, worked out before anything is written:
 * the names, the namespaces, the pages, and what goes into each file.
 *
 * `Order` (or `OrderResource`) becomes:
 *
 *   app/Resources/OrderResource.php
 *   app/Livewire/Resources/Orders/ListOrders.php     ─┐
 *   app/Livewire/Resources/Orders/CreateOrder.php     │ or ManageOrders.php alone,
 *   app/Livewire/Resources/Orders/EditOrder.php       │ with --simple
 *   app/Livewire/Resources/Orders/ViewOrder.php      ─┘ with --view
 */
final class ResourceScaffold
{
    public readonly string $base;

    public readonly string $plural;

    public readonly string $model;

    public readonly string $resourceNamespace;

    public readonly string $resourceClass;

    public readonly string $pagesNamespace;

    /**
     * @param  string  $rootNamespace  The application's, with its trailing backslash — `App\`.
     * @param  array{fields: array<int, string>, columns: array<int, string>, entries: array<int, string>, imports: array<int, string>}  $lines
     */
    public function __construct(
        string $name,
        string $rootNamespace,
        ?string $model,
        public readonly bool $view,
        public readonly bool $simple,
        public readonly bool $softDeletes,
        private readonly array $lines,
    ) {
        $this->base = Str::studly((string) Str::of(class_basename(str_replace('/', '\\', $name)))->beforeLast('Resource')) ?: 'Record';
        $this->plural = Str::pluralStudly($this->base);
        $this->model = ltrim($model ?? $rootNamespace.'Models\\'.$this->base, '\\');
        $this->resourceNamespace = $rootNamespace.'Resources';
        $this->resourceClass = $this->base.'Resource';
        $this->pagesNamespace = $rootNamespace.'Livewire\\Resources\\'.$this->plural;
    }

    public function resourceFqn(): string
    {
        return $this->resourceNamespace.'\\'.$this->resourceClass;
    }

    /**
     * Page kind => [class, base class, body].
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public function pages(): array
    {
        if ($this->simple) {
            return ['index' => ['Manage'.$this->plural, 'ManagePage', '']];
        }

        $pages = [
            'index' => ['List'.$this->plural, 'ListPage', ''],
            'create' => ['Create'.$this->base, 'CreatePage', ''],
        ];

        if ($this->view) {
            $pages['view'] = ['View'.$this->base, 'ViewPage', $this->recordHeaderActions()];
        }

        $pages['edit'] = ['Edit'.$this->base, 'EditPage', $this->recordHeaderActions()];

        return $pages;
    }

    /** @return array<string, string> What resource.stub is filled with. */
    public function resourceReplacements(): array
    {
        $contracts = ['DescribesResource', 'ProvidesPages', 'ProvidesResourceForm', 'ProvidesResourceTable'];
        $imports = [
            'NyonCode\\WireCore\\Core\\Resources\\Concerns\\DescribesRecords',
            'NyonCode\\WireCore\\Core\\Resources\\Contracts\\DescribesResource',
            'NyonCode\\WireCore\\Foundation\\Routing\\Contracts\\ProvidesPages',
            'NyonCode\\WireForms\\Contracts\\ProvidesResourceForm',
            'NyonCode\\WireForms\\Forms\\Form',
            'NyonCode\\WirePanels\\Resources\\Contracts\\ProvidesResourceTable',
            'NyonCode\\WireTable\\Table',
            $this->model,
            ...$this->lines['imports'],
        ];

        if ($this->lines['columns'] === []) {
            // The fallback column below names it.
            $imports[] = 'NyonCode\\WireTable\\Columns\\TextColumn';
        }

        if ($this->view) {
            $contracts[] = 'ProvidesResourceInfolist';
            $imports[] = 'NyonCode\\WireCore\\Infolists\\Contracts\\ProvidesResourceInfolist';
            $imports[] = 'NyonCode\\WireCore\\Infolists\\Infolist';
        }

        if ($this->softDeletes) {
            $contracts[] = 'ManagesTrashedRecords';
            $imports[] = 'NyonCode\\WirePanels\\Resources\\Contracts\\ManagesTrashedRecords';
        }

        $pages = [];

        foreach ($this->pages() as $kind => [$class]) {
            $imports[] = $this->pagesNamespace.'\\'.$class;
            $pages[] = str_repeat(' ', 12).var_export($kind, true).' => '.$class.'::class,';
        }

        sort($contracts);

        return [
            'namespace' => $this->resourceNamespace,
            'imports' => $this->imports($imports),
            'class' => $this->resourceClass,
            'contracts' => implode(', ', $contracts),
            'plural' => Str::headline($this->plural),
            'model' => class_basename($this->model),
            'pages' => implode("\n", $pages),
            'columns' => $this->indented($this->lines['columns'], "TextColumn::make('id')->sortable(),", 12),
            'fields' => $this->indented($this->lines['fields'], "// TextInput::make('name')->required(),", 12),
            'infolist' => $this->view ? $this->infolistMethod() : '',
        ];
    }

    /** @return array<string, string> What resource-page.stub is filled with for one page. */
    public function pageReplacements(string $class, string $base, string $body): array
    {
        return [
            'namespace' => $this->pagesNamespace,
            'imports' => $this->imports([$this->resourceFqn(), 'NyonCode\\WirePanels\\Resources\\Pages\\'.$base]),
            'class' => $class,
            'base' => $base,
            'resource' => $this->resourceClass,
            'body' => $body,
        ];
    }

    /** The header actions an edit or view page is generated with. */
    private function recordHeaderActions(): string
    {
        $actions = ['$this->deleteHeaderAction(),'];

        if ($this->softDeletes) {
            $actions[] = '$this->restoreHeaderAction(),';
            $actions[] = '$this->forceDeleteHeaderAction(),';
        }

        return "\n    protected function headerActions(): array\n    {\n        return [\n"
            .implode("\n", array_map(fn (string $line): string => str_repeat(' ', 12).$line, $actions))
            ."\n        ];\n    }\n";
    }

    private function infolistMethod(): string
    {
        $entries = $this->indented($this->lines['entries'], "// TextEntry::make('name'),", 12);

        return "\n    public function infolist(Infolist \$infolist): Infolist\n    {\n        return \$infolist->schema([\n{$entries}\n        ]);\n    }\n";
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function indented(array $lines, string $fallback, int $spaces): string
    {
        $lines = $lines === [] ? [$fallback] : $lines;

        return implode("\n", array_map(fn (string $line): string => str_repeat(' ', $spaces).$line, $lines));
    }

    /**
     * @param  array<int, string>  $classes
     */
    private function imports(array $classes): string
    {
        $classes = array_values(array_unique(array_map(fn (string $class): string => ltrim($class, '\\'), $classes)));
        sort($classes);

        return implode("\n", array_map(fn (string $class): string => 'use '.$class.';', $classes));
    }
}
