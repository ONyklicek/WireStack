<?php

declare(strict_types=1);

use NyonCode\WireCore\Infolists\Concerns\WithInfolists;
use NyonCode\WireCore\Infolists\Infolist;

it('provides an infolist factory hook', function () {
    $host = new class
    {
        use WithInfolists;
    };

    expect($host->makeInfolist())->toBeInstanceOf(Infolist::class);
});
