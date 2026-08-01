<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fixture for the display/edit column surfaces that no other workbench model
 * carries: a stored CSS color, a numeric score, a tag list, an inline-editable
 * boolean — and soft deletes, which nothing else here uses.
 *
 * Deliberately its own model rather than columns bolted onto Task: adding
 * SoftDeletes to a model dozens of tests query would change every one of those
 * queries.
 */
#[Fillable(['title', 'brand_color', 'score', 'tags', 'is_published'])]
class Document extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'score' => 'float',
            'is_published' => 'boolean',
        ];
    }
}
