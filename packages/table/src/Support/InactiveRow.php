<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use NyonCode\WireTable\Concerns\InteractsWithRecordDisabledState;
use NyonCode\WireTable\Exceptions\TableConfigurationException;

/**
 * What an *inactive* record looks like and what it still permits — the single
 * owner of that decision for the row, the stacked card and the server alike.
 *
 * A record that has been cancelled, voided, archived or closed is still part of
 * the list: it is read, searched and counted, and hiding it would be a lie about
 * the data. What it is not is *writable*, and a table that keeps offering its
 * editors invites a write the domain has already refused. So the state is
 * declared once on the table — `Table::rowInactive()` — and everything that
 * follows from it is resolved here:
 *
 *   $table->rowInactive(
 *       fn (Order $order) => $order->status === 'cancelled',
 *       fn (InactiveRow $row) => $row->strikethrough()->color('danger'),
 *   );
 *
 * **The lock is the point, the styling is the hint.** Inline editing is off for
 * an inactive record by default and is refused *server-side* — the table pushes
 * this state into every editable column's own per-record disabled state
 * ({@see InteractsWithRecordDisabledState}), which
 * `Column::canEdit()` already enforces in `CellEditPipeline::commit()`, so a
 * forged request and a fill-handle drag are refused on the same rule the cell
 * renders from. Nothing here is cosmetic-only.
 *
 * **What stays live, by default.** Row actions, the selection checkbox and a
 * record click. A cancelled order still has to be openable, and "un-cancel" is
 * itself a row action — locking the whole row would take away the one control
 * that undoes the state. Both are switchable ({@see selectable()},
 * {@see actions()}) for a table where an inactive record really is inert.
 *
 * **The two looks are separate on purpose.** {@see dim()} is the state (the row
 * has stopped being live) and is on by default; {@see strikethrough()} is the
 * *claim* that the value itself was struck out, which is right for a cancelled
 * invoice line and wrong for an archived customer, so it waits to be asked for.
 * {@see color()} feeds the canonical row tint — the same resolver
 * `Table::rowColor()` uses, so an inactive row and a coloured row cannot drift
 * apart — and an explicit `rowColor()` on the table always wins over it.
 *
 * The project-wide default lives in `config('wire-table.defaults.inactive_rows')`
 * and is read through {@see fromConfig()}, so a back office that wants one house
 * style for every cancelled row says so once.
 */
final class InactiveRow
{
    /** Setter name keyed by its normalized config key. */
    private const OPTIONS = [
        'strikethrough' => 'strikethrough',
        'dim' => 'dim',
        'color' => 'color',
        'editing' => 'editing',
        'selectable' => 'selectable',
        'actions' => 'actions',
    ];

    private bool $strikethrough = false;

    private bool $dim = true;

    private ?string $color = null;

    private bool $editing = false;

    private bool $selectable = true;

    private bool $actions = true;

    /** The shipped look: dimmed, not struck through, no tint, editing locked. */
    public static function make(): self
    {
        return new self;
    }

    /**
     * Build from a config value: `null` (or a missing key) for the shipped
     * defaults, or a map of option => value applied on top of them. An unknown
     * option is a typo that would otherwise silently do nothing, so it throws.
     *
     * @param  array<string, bool|string|null>|null  $config
     */
    public static function fromConfig(?array $config): self
    {
        $row = new self;

        if ($config === null) {
            return $row;
        }

        foreach ($config as $option => $value) {
            $setter = self::OPTIONS[strtolower(str_replace(['_', '-'], '', (string) $option))]
                ?? throw TableConfigurationException::unknownInactiveRowOption((string) $option, array_values(self::OPTIONS));

            if ($setter === 'color') {
                $row->color($value === null ? null : (string) $value);

                continue;
            }

            $row->{$setter}((bool) $value);
        }

        return $row;
    }

    /**
     * Strike the row's text through — the value was voided, not merely closed.
     *
     * Off by default. It reads as a statement about the *content* ("this line is
     * not part of the total"), which is true of a cancelled order line and false
     * of an archived customer, so the table says which one it means.
     */
    public function strikethrough(bool $condition = true): static
    {
        $this->strikethrough = $condition;

        return $this;
    }

    public function isStrikethrough(): bool
    {
        return $this->strikethrough;
    }

    /**
     * Mute the row's text. On by default: it is the quiet half of the state and
     * the only one that says "this row has stopped being live" without also
     * claiming the values were struck out.
     */
    public function dim(bool $condition = true): static
    {
        $this->dim = $condition;

        return $this;
    }

    public function isDimmed(): bool
    {
        return $this->dim;
    }

    /**
     * Tint an inactive row with a semantic role or raw hue (`'danger'`,
     * `'gray'`, …), resolved by the canonical row-tint owner. Null for no tint,
     * which is the default — a colour on every cancelled row is loud on a table
     * where most rows are cancelled.
     */
    public function color(?string $color): static
    {
        $this->color = $color === '' ? null : $color;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    /**
     * Whether inline editing survives the state. Off by default — this is the
     * reason the state exists.
     *
     * `editing(true)` re-opens the editors on an inactive row, for a table where
     * the state is a label rather than a lock.
     */
    public function editing(bool $allowed = true): static
    {
        $this->editing = $allowed;

        return $this;
    }

    public function allowsEditing(): bool
    {
        return $this->editing;
    }

    /**
     * Whether an inactive row may be ticked. On by default: bulk actions are how
     * a hundred cancelled rows get archived, and the checkbox is how they are
     * chosen.
     *
     * With `selectable(false)` the row's checkbox goes inert, "select page"
     * skips the row and a forged toggle is refused. A **"select all matching"**
     * selection is a query rather than a list (see `Table::bulkMaxRecords()`) and
     * still covers inactive rows — a bulk action that must not touch them checks
     * the record, exactly as it would for any other rule the database cannot
     * express.
     */
    public function selectable(bool $allowed = true): static
    {
        $this->selectable = $allowed;

        return $this;
    }

    public function allowsSelection(): bool
    {
        return $this->selectable;
    }

    /**
     * Whether the row's own actions stay operable. On by default, because the
     * action that undoes the state lives there.
     *
     * With `actions(false)` the action cell goes inert, the right-click menu is
     * not built for that record, and `executeTableAction()` / `openActionModal()`
     * refuse it — which covers a *bound* record action too (`onClick()`,
     * `onDoubleClick()`), since those execute through the same two methods.
     *
     * `recordUrl()` is the one row-wide click this does not touch: it is a plain
     * link, and opening a cancelled record is reading rather than writing.
     */
    public function actions(bool $allowed = true): static
    {
        $this->actions = $allowed;

        return $this;
    }

    public function allowsActions(): bool
    {
        return $this->actions;
    }

    /**
     * The `<tr>`'s share of the look.
     *
     * Both utilities are written as child/descendant variants rather than plain
     * inherited ones, and that is load-bearing twice over: a body cell carries
     * its own `dark:text-white`, which an inherited colour on the row would lose
     * to in dark mode (the variant raises specificity past it), and a form
     * control does not inherit `text-decoration` from its ancestors at all, so
     * the strike has to be applied to the inputs themselves — which is precisely
     * where an inactive row has to show it, since those are the values a reader
     * would otherwise take for editable.
     */
    public function rowClasses(): string
    {
        $classes = [];

        if ($this->strikethrough) {
            $classes[] = '[&>td]:line-through [&_input]:line-through [&_select]:line-through [&_textarea]:line-through';
        }

        if ($this->dim) {
            $classes[] = '[&>td]:text-gray-400 dark:[&>td]:text-gray-500';
        }

        return implode(' ', $classes);
    }

    /**
     * The same look for the stacked card, where the text is the card's own and
     * inherits normally — only the form controls still need naming.
     */
    public function cardClasses(): string
    {
        $classes = [];

        if ($this->strikethrough) {
            $classes[] = 'line-through [&_input]:line-through [&_select]:line-through [&_textarea]:line-through';
        }

        if ($this->dim) {
            $classes[] = 'text-gray-400 dark:text-gray-500';
        }

        return implode(' ', $classes);
    }
}
