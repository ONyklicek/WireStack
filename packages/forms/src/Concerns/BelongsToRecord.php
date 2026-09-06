<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireForms\Components\BelongsToSelect;

/**
 * The form's parent record, handed to a field by the runtime.
 *
 * Runtime wiring, not user API: `FormRuntime::prepare()` propagates the form's
 * `->model()` into every field exposing a `record()` setter, so an owner
 * configures the record on the form and never on the field. In create mode the
 * record is a bare instance of the model class — present, but `exists === false`,
 * which is the distinction a caller must make before reading a key off it.
 *
 * One owner because two fields already wanted it for unrelated reasons: a
 * {@see BelongsToSelect} introspects the
 * relationship it hangs off, and `unique()` needs the row it must not collide
 * with. Anything that needs the record next inherits it rather than declaring a
 * third copy.
 */
trait BelongsToRecord
{
    /** @var Model|null Resolved parent model instance (set by the form runtime) */
    protected ?Model $record = null;

    /**
     * Bind the form's parent record to this field.
     *
     * @docs-ignore
     */
    public function record(?Model $record): static
    {
        $this->record = $record;

        return $this;
    }

    /** The form's parent record, when the form was given a model. */
    public function getRecord(): ?Model
    {
        return $this->record;
    }
}
