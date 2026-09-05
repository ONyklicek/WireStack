<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\Routing\Zone;

/*
 * A zone is a route-name prefix (ADR 0027 §1), and the two things worth pinning
 * about it are both traps rather than features: the substring that looks like a
 * zone and is not, and the request on which asking at all is wrong.
 */

it('reads the zone out of a route name', function () {
    expect(Zone::of('business.wire.invoices.index'))->toBe('business.')
        ->and(Zone::of('admin.wire.invoices.edit'))->toBe('admin.')
        // Nested groups compose, because a route name is just a string.
        ->and(Zone::of('ops.eu.wire.batches.index'))->toBe('ops.eu.');
});

it('answers null for an unzoned wire route', function () {
    expect(Zone::of('wire.invoices.index'))->toBeNull()
        ->and(Zone::of('wire.invoices.archive'))->toBeNull();
});

it('does not find a zone in livewire.update, which contains the word wire', function () {
    // The whole reason this is a regex. `str_contains('livewire.update', 'wire.')`
    // is TRUE, so a substring search reports a zone called `li` on precisely the
    // request where there is none — and that request is every Livewire round
    // trip, which is most of them.
    expect(Zone::of('livewire.update'))->toBeNull()
        ->and(Zone::of('default-livewire.update'))->toBeNull()
        ->and(Zone::of('livewire.upload-file'))->toBeNull();
});

it('answers null for anything that is not a wire page route', function () {
    expect(Zone::of(null))->toBeNull()
        ->and(Zone::of('dashboard'))->toBeNull()
        ->and(Zone::of('admin.users.index'))->toBeNull()
        ->and(Zone::of('wire.invoices'))->toBeNull();
});

it('normalises a zone to a route-name prefix', function () {
    expect(Zone::prefix('business'))->toBe('business.')
        ->and(Zone::prefix('business.'))->toBe('business.')
        ->and(Zone::prefix(null))->toBe('')
        ->and(Zone::prefix(''))->toBe('')
        ->and(Zone::prefix('.'))->toBe('');
});

class ZnProbe extends Component
{
    public ?string $mounted = null;

    public ?string $asked = null;

    public function mount(): void
    {
        $this->mounted = Zone::current();
    }

    public function poke(): void
    {
        $this->asked = Zone::current() ?? 'NULL';
    }

    public function render(): string
    {
        return '<div>probe</div>';
    }
}

it('is a full-page-render call, and says null rather than something wrong later', function () {
    // Measured, not assumed: `Route::currentRouteName()` is `livewire.update` on
    // a round trip. The property mounted from the page render is what survives;
    // asking again answers null, which renders as "no link" instead of a link
    // out of the zone.
    Route::name('business.')->prefix('business')->group(function (): void {
        Route::get('probe', ZnProbe::class)->name('wire.zn.index');
    });
    Route::getRoutes()->refreshNameLookups();

    $component = Livewire::test(ZnProbe::class)->call('poke');

    expect($component->get('asked'))->toBe('NULL');
});
