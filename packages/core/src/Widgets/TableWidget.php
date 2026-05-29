<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets;

use Closure;

class TableWidget extends Widget
{
    protected ?Closure $tableCallback = null;

    /**
     * Configure an embedded table for this widget.
     *
     * @param  Closure  $callback  fn(Table $table): Table
     */
    public function table(Closure $callback): static
    {
        $this->tableCallback = $callback;

        return $this;
    }

    public function getTableCallback(): ?Closure
    {
        return $this->tableCallback;
    }

    protected function viewName(): string
    {
        return 'wire-core::widgets.table';
    }
}
