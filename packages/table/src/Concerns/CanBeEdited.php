<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Exceptions\TableConfigurationException;

/**
 * Whether a cell in this column may be written from the table, and under what
 * rules — the column's half of inline editing.
 *
 * **Configuration only, and deliberately without a service.** The decomposition
 * plan proposed an `InlineEditPolicy` to hold "the ability/authorize half"; what
 * is actually here is one `Gate::allows()` and one closure call, while the whole
 * write — the five save strategies, validation, dehydration, the optimistic-lock
 * check and the events — already has two owners in `Services\CellEditPipeline`
 * and `Services\CellValueWriter`. A third service over eight lines would name a
 * responsibility that is already taken.
 *
 * The two gates are separate on purpose, and `CellEditPipeline` consults both:
 * {@see isEditable()} asks whether the column offers an editor at all, and
 * {@see canInlineEdit()} whether *this* user may use it. A column readable by
 * everyone and writable by an editor is the ordinary case.
 *
 * @phpstan-require-extends Column
 */
trait CanBeEdited
{
    // Note: the $editable boolean was removed in v2 — the capability is the
    // single source of truth, read through isEditable().

    /** @var string|null Gate ability for inline editing */
    protected ?string $inlineEditAbility = null;

    /** @var Closure|null Validation rules for inline editing */
    protected ?Closure $editableRules = null;

    /** @var Closure|null Callback to handle the edit action */
    protected ?Closure $editableCallback = null;

    /** @var bool Whether a fill handle drag may write this column (editable columns only) */
    protected bool $fillable = true;

    /**
     * Set a Gate ability required for inline editing of this column.
     */
    public function authorizeInline(?string $ability): static
    {
        $this->inlineEditAbility = $ability;

        return $this;
    }

    public function getInlineEditAbility(): ?string
    {
        return $this->inlineEditAbility;
    }

    /**
     * Check if the current user can inline-edit this column.
     */
    public function canInlineEdit(): bool
    {
        if (! $this->inlineEditAbility) {
            return true;
        }

        return Gate::allows($this->inlineEditAbility);
    }

    /**
     * Allow this column's cells to be written.
     *
     * On a dedicated editable column (TextInputColumn, SelectColumn,
     * ToggleColumn, CheckboxColumn) this is the switch that turns its editor on
     * and off: `->editable(false)` renders the plain value and makes the server
     * refuse a write for that column.
     *
     * It does **not** render an editor on an ordinary column — no view has read
     * an editor type in any revision since the first commit — so the old
     * `$type` / `$options` arguments are gone.
     *
     * They are swallowed by a variadic and refused rather than simply dropped
     * from the signature: PHP ignores surplus *positional* arguments without a
     * word, so `editable(true, 'select', [...])` — the form the docs taught —
     * would otherwise keep doing exactly the silent nothing this removed.
     * A named `type:` argument lands in that same variadic, so both call styles
     * get the same message.
     */
    public function editable(bool $editable = true, mixed ...$removedEditorArguments): static
    {
        if ($removedEditorArguments !== []) {
            throw TableConfigurationException::genericEditorNotRendered($this->getName());
        }

        $this->capabilities = $editable
            ? $this->capabilities->add(Capability::Editable)
            : $this->capabilities->remove(Capability::Editable);

        return $this;
    }

    public function isEditable(): bool
    {
        return $this->hasCapability(Capability::Editable);
    }

    /**
     * Whether a fill may write this column, when the table offers a fill handle.
     *
     * Editable columns are fillable by default — a cell you can type into is one
     * you can drag down. Turn it off for a column where repeating one value is
     * meaningless or dangerous (a unique code, an invoice number).
     *
     * Example:
     *   TextInputColumn::make('invoice_number')->fillable(false);
     */
    public function fillable(bool $condition = true): static
    {
        $this->fillable = $condition;

        return $this;
    }

    public function isFillable(): bool
    {
        return $this->isEditable() && $this->fillable;
    }

    /** Validation rules for the inline-editable cell; the Closure receives `$record` and returns a rules array. */
    public function editableRules(Closure $callback): static
    {
        $this->editableRules = $callback;

        return $this;
    }

    /**
     * @return array<int, mixed>
     */
    public function getEditableRules(?Model $record): array
    {
        if ($this->editableRules) {
            return ($this->editableRules)($record);
        }

        return [];
    }

    /** Persist an inline edit with a custom callback (`$record, $value`) instead of the default attribute write. */
    public function editableUsing(Closure $callback): static
    {
        $this->editableCallback = $callback;

        return $this;
    }

    public function getEditableCallback(): ?Closure
    {
        return $this->editableCallback;
    }
}
