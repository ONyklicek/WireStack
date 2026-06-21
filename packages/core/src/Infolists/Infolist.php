<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Infolists;

use Illuminate\Contracts\Support\Htmlable;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireCore\Infolists\Components\Entry;

/**
 * Public infolist API — a declarative, read-only display of a single record.
 *
 * Mirrors the wire-forms Form ergonomics (schema of sections/grids +
 * components) but renders {@see Entry} components instead of editable fields.
 * Lives in core next to Widgets because it is a display assembly, and depends
 * only on the shared schema layout + entries.
 *
 * @phpstan-consistent-constructor
 */
class Infolist implements Htmlable
{
    protected mixed $record = null;

    /** @var array<int, Entry|LayoutComponent> */
    protected array $schema = [];

    protected int $columns = 1;

    public static function make(): static
    {
        return new static;
    }

    public function record(mixed $record): static
    {
        $this->record = $record;

        return $this;
    }

    public function getRecord(): mixed
    {
        return $this->record;
    }

    /**
     * Bind a plain array of data as the record.
     *
     * @param  array<string, mixed>  $data
     */
    public function state(array $data): static
    {
        return $this->record($data);
    }

    /**
     * @param  array<int, Entry|LayoutComponent>  $components
     */
    public function schema(array $components): static
    {
        $this->schema = $components;

        return $this;
    }

    /**
     * @return array<int, Entry|LayoutComponent>
     */
    public function getSchema(): array
    {
        return $this->schema;
    }

    public function columns(int $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    public function getColumns(): int
    {
        return $this->columns;
    }

    public function toHtml(): string
    {
        $this->prepareComponents($this->schema);

        return view('wire-core::infolists.infolist', [
            'components' => $this->schema,
            'columns' => $this->columns,
        ])->render();
    }

    public function __toString(): string
    {
        return $this->toHtml();
    }

    /**
     * Propagate the bound record to every entry, recursing through layout
     * components. Repeatable entries receive the parent record and rebind their
     * own children per row at render time.
     *
     * @param  array<int, Entry|LayoutComponent>  $components
     */
    protected function prepareComponents(array $components): void
    {
        foreach ($components as $component) {
            if ($component instanceof LayoutComponent) {
                $this->prepareComponents($component->getSchema());
            } elseif ($component instanceof Entry) {
                $component->record($this->record);
            }
        }
    }
}
