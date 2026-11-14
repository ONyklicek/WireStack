<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Contracts;

/**
 * A column whose cells share one piece of markup drawn once per table — the
 * option list of a SelectColumn, which a hundred rows would otherwise each carry.
 *
 * The data region draws {@see renderCellPanel()} for every visible column that
 * implements this, beside the rows rather than inside them, so a cell stays as
 * small as its own value and the list travels once.
 */
interface RendersCellPanel
{
    /**
     * The shared markup, or '' when this column, as configured, has none.
     */
    public function renderCellPanel(): string;
}
