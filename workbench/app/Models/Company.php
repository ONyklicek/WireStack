<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

/** A tenant of the workbench's `tenants` zone, addressed by its slug. */
class Company extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
