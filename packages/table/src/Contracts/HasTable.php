<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Contracts;

use NyonCode\WireTable\Table;

interface HasTable
{
    /**
     * Configure the table instance.
     */
    public function table(Table $table): Table;
}
