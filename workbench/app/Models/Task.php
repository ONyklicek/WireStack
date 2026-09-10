<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    // A property rather than `#[Fillable]`: that attribute is Laravel 13's, and
    // these packages support 12, where it is not read at all.
    protected $fillable = ['title', 'status', 'priority', 'owner_name', 'sort_order', 'completed', 'due_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
            'due_at' => 'datetime',
        ];
    }
}
