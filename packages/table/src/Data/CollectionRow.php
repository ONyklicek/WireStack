<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Data;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Data\RecordContract;
use NyonCode\WireTable\Exceptions\CustomDataSourceException;

/**
 * A row of a custom data source, dressed as a model for the table to draw.
 *
 * Everything that renders a row — columns, record actions, URLs, the selection
 * checkbox — is written against an Eloquent model (`Column::getState(Model)`),
 * and a `DataSource` answers with arrays or `RecordContract`s. Rather than
 * retype that whole surface, a source's row is handed to it as this: the row's
 * values as attributes, its key under the table's primary key, and nothing
 * behind it — no table, no connection, no query.
 *
 * Which is also why it cannot be written: there is nowhere for a save to go. A
 * table over a custom source that edits in place asks the source's owner, not
 * the row.
 */
final class CollectionRow extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    /** What the table draws for one row of a source. A model a source already returns is kept. */
    public static function from(mixed $row, string $keyName = 'id'): Model
    {
        if ($row instanceof Model) {
            return $row;
        }

        $attributes = $row instanceof RecordContract ? $row->toArray() : (array) $row;

        $model = new self;
        $model->setKeyName($keyName);
        $model->setRawAttributes($attributes, sync: true);
        $model->exists = true;

        return $model;
    }

    /** @param  array<string, mixed>  $options */
    public function save(array $options = []): bool
    {
        throw CustomDataSourceException::readOnlyRow();
    }

    public function delete(): ?bool
    {
        throw CustomDataSourceException::readOnlyRow();
    }
}
