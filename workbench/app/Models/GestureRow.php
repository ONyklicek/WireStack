<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

class GestureRow extends Model
{
    // A property rather than `#[Fillable]`: that attribute is Laravel 13's, and
    // these packages support 12, where it is not read at all.
    protected $fillable = ['name', 'status', 'amount'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }
}
