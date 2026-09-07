<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * The application's team model. This package ships none: an application that
 * has teams already has one, and one that does not is not using this.
 */
class Team extends Model
{
    protected $table = 'teams';

    protected $guarded = [];
}
