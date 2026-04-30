<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Forms\Runtime;

use Livewire\Component;

/**
 * Manages form state and wire:model bindings.
 *
 * @internal This class is not part of the public API.
 */
final class StateManager
{
    private array $state = [];

    private ?Component $livewire = null;

    private ?string $statePath = null;

    public function setLivewire(?Component $livewire): void
    {
        $this->livewire = $livewire;
    }

    public function setStatePath(?string $path): void
    {
        $this->statePath = $path;
    }

    public function getStatePath(): ?string
    {
        return $this->statePath;
    }

    public function fill(array $data): void
    {
        $this->state = $data;

        if ($this->livewire && $this->statePath) {
            data_set($this->livewire, $this->statePath, $data);
        }
    }

    public function getState(): array
    {
        if ($this->livewire && $this->statePath) {
            return (array) data_get($this->livewire, $this->statePath, []);
        }

        return $this->state;
    }

    public function setState(array $data): void
    {
        $this->state = $data;

        if ($this->livewire && $this->statePath) {
            data_set($this->livewire, $this->statePath, $data);
        }
    }

    public function hasLivewire(): bool
    {
        return $this->livewire !== null;
    }

    public function getLivewire(): ?Component
    {
        return $this->livewire;
    }
}
