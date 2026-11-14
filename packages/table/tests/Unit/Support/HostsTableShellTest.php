<?php

declare(strict_types=1);

use Livewire\Component;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Contracts\HostsTableShell;
use NyonCode\WireTable\Contracts\ShowsTableColumns;

/*
 * The render plans read their host through two contracts rather than through a
 * `mixed` component. A component using WithTable never says `implements`, so
 * nothing would notice a host method renamed or dropped — this does.
 */
abstract class HtsHost extends Component
{
    use WithTable;
}

it('has WithTable answer everything the render plans ask of their host', function (string $contract) {
    $host = new ReflectionClass(HtsHost::class);
    $missing = [];

    foreach ((new ReflectionClass($contract))->getMethods() as $method) {
        if (! $host->hasMethod($method->getName()) || ! $host->getMethod($method->getName())->isPublic()) {
            $missing[] = $method->getName();
        }
    }

    expect($missing)->toBe([]);
})->with([HostsTableShell::class, ShowsTableColumns::class]);
