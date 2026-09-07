<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAudit\Tests\Fixtures;

use NyonCode\WireCore\Audit\AuditEntry;

/** An entry model pointed at a connection this application does not have. */
class UnreachableAuditEntry extends AuditEntry
{
    protected $connection = 'not-configured';
}
