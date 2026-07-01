<?php

declare(strict_types=1);

use Livewire\Component;
use NyonCode\WireForms\Concerns\DispatchesStateUpdates;

/**
 * Host exposing the protected dispatch helpers so the non-Form guard branch can
 * be exercised in isolation.
 */
class DispatchesStateUpdatesHost extends Component
{
    use DispatchesStateUpdates;

    /**
     * @param  iterable<mixed>  $forms
     */
    public function callAfterStateUpdated(iterable $forms, string $path, mixed $old): bool
    {
        return $this->dispatchAfterStateUpdated($forms, $path, $old);
    }

    /**
     * @param  iterable<mixed>  $forms
     */
    public function callLiveValidation(iterable $forms, string $path): bool
    {
        return $this->dispatchLiveValidation($forms, $path);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

test('dispatchAfterStateUpdated skips non-Form entries', function () {
    $host = new DispatchesStateUpdatesHost;

    expect($host->callAfterStateUpdated([new stdClass], 'data.name', null))->toBeFalse();
});

test('dispatchLiveValidation skips non-Form entries', function () {
    $host = new DispatchesStateUpdatesHost;

    expect($host->callLiveValidation([new stdClass], 'data.name'))->toBeFalse();
});
