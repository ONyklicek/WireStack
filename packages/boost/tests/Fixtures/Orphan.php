<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A model whose connection does not exist — the introspector must treat its
 * schema as unknowable rather than let the failure escape.
 */
class Orphan extends Model
{
    protected $connection = 'no-such-connection';

    protected $table = 'orphans';
}
