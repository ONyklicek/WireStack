<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Tenancy\Concerns\BelongsToTenant;

/** One company's project: the one tenant-owned model in the workbench. */
class Project extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public $timestamps = false;
}
