<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Data;

use Illuminate\Database\Eloquent\Model;

/**
 * A `Model` seen through {@see RecordContract}.
 *
 * A wrapper rather than making `Model` implement the contract directly — ADR
 * 0019's first open question, answered wrapper. Editing a framework class was
 * never on the table, and the allocation argument against wrapping does not
 * apply where this is used: rows are wrapped on the *resolution* seam, one at a
 * time when an action needs a record, never per row of a rendered page.
 */
final readonly class EloquentRecord implements RecordContract
{
    public function __construct(private Model $model) {}

    public function getKey(): int|string
    {
        /** @var int|string $key */
        $key = $this->model->getKey();

        return $key;
    }

    /**
     * Reads through relations, because `data_get()` does and half the framework
     * already relies on that: a column named `company.name` is the ordinary
     * case, not an exotic one.
     */
    public function get(string $path): mixed
    {
        return data_get($this->model, $path);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->model->toArray();
    }

    public function unwrap(): Model
    {
        return $this->model;
    }
}
