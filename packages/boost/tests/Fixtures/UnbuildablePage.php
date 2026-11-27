<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Tests\Fixtures;

use Livewire\Component;
use NyonCode\WireForms\Concerns\WithActions;

/** A component the container cannot make: its constructor asks for a scalar. */
class UnbuildablePage extends Component
{
    use WithActions;

    public function __construct(public string $tenant) {}

    public function render(): string
    {
        return '<div></div>';
    }
}
