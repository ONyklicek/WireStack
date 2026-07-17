<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Panels;

use Illuminate\Contracts\Support\Htmlable;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireCore\Infolists\Components\Entry;
use NyonCode\WireCore\Infolists\Infolist;

/**
 * Public panel API — a declarative "record panel" for a single record.
 *
 * A panel reuses the infolist layout + {@see Entry} vocabulary but is an
 * *interactive* surface: alongside read-only entries it can host editable
 * entries ({@see Components\EditableEntry}) that write directly back to the
 * bound record with optimistic UI + optimistic locking, through the host
 * {@see Concerns\WithEditablePanel} trait.
 *
 * It is deliberately a separate concept from {@see Infolist}
 * (which stays read-only by contract): a panel reads *and* writes.
 *
 * @phpstan-consistent-constructor
 */
class Panel implements Htmlable
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

        return view('wire-core::panels.panel', [
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
     * components — the same binding contract as an infolist.
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
