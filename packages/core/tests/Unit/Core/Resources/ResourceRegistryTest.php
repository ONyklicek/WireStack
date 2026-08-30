<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Exceptions\ResourceRegistrationException;
use NyonCode\WireCore\WireCoreServiceProvider;

/*
 * Which resources exist, and which one owns a model.
 *
 * The registry reads only the static half of the resource contract, and that is
 * the point: listing a menu or routing a model must not compose a table or a
 * form. Everything here therefore runs without instantiating a single resource.
 */
class RrOrder extends Model
{
    protected $guarded = [];
}

class RrOrderLine extends Model
{
    protected $guarded = [];
}

class RrOrderResource implements DescribesResource
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return RrOrder::class;
    }
}

class RrOrderLineResource implements DescribesResource
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return RrOrderLine::class;
    }
}

/** A resource over a V2.0 DataSource — registered and listed, but no model to route. */
class RrReportResource implements DescribesResource
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }
}

class RrRivalOrderResource implements DescribesResource
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return RrOrder::class;
    }

    public static function key(): string
    {
        return 'rr-orders';
    }
}

beforeEach(function () {
    $this->registry = new ResourceRegistry;
});

// ─── Registration ────────────────────────────────────────────────────────────

it('lists what it was given, keyed by resource key', function () {
    $this->registry->register(RrOrderResource::class);
    $this->registry->register(RrReportResource::class);

    expect($this->registry->all())->toBe([
        'rr-orders' => RrOrderResource::class,
        'rr-reports' => RrReportResource::class,
    ])
        ->and($this->registry->find('rr-orders'))->toBe(RrOrderResource::class)
        ->and($this->registry->has('rr-orders'))->toBeTrue()
        ->and($this->registry->find('nope'))->toBeNull()
        ->and($this->registry->has('nope'))->toBeFalse();
});

it('accepts the same resource twice', function () {
    // Config merging and a provider booted twice in a test suite both do this;
    // treating it as a collision would make the second boot fatal.
    $this->registry->register(RrOrderResource::class);
    $this->registry->register(RrOrderResource::class);

    expect($this->registry->all())->toHaveCount(1);
});

it('refuses two different resources claiming one key', function () {
    // The key is the config handle, the route segment and the introspection
    // name. Letting the second win would silently move routing off the first.
    $this->registry->register(RrOrderResource::class);

    $this->registry->register(RrRivalOrderResource::class);
})->throws(ResourceRegistrationException::class, 'Two resources claim the key');

it('refuses a class that is not a resource', function () {
    $this->registry->register(RrOrder::class);
})->throws(ResourceRegistrationException::class, 'does not implement');

// ─── Routing a model ─────────────────────────────────────────────────────────

it('routes a model to the resource that owns it', function () {
    $this->registry->register(RrOrderResource::class);
    $this->registry->register(RrOrderLineResource::class);

    expect($this->registry->forModel(RrOrder::class))->toBe(RrOrderResource::class)
        ->and($this->registry->forModel(RrOrderLine::class))->toBe(RrOrderLineResource::class)
        ->and($this->registry->forModel(Model::class))->toBeNull();
});

it('routes a leading-slash model class the same way', function () {
    // ::class never carries one, but a config array or a string built by hand
    // does, and a map keyed one way and read the other silently answers null.
    $this->registry->register(RrOrderResource::class);

    expect($this->registry->forModel('\\'.RrOrder::class))->toBe(RrOrderResource::class);
});

it('registers a model-less resource but never finds it by model', function () {
    $this->registry->register(RrReportResource::class);

    expect($this->registry->all())->toHaveCount(1)
        ->and($this->registry->forModel(RrOrder::class))->toBeNull();
});

it('sees a resource registered after the model map was first built', function () {
    // The map is memoized on first use; a registration after that must invalidate
    // it, or a resource added in code after boot is unroutable for the request.
    $this->registry->register(RrOrderResource::class);
    expect($this->registry->forModel(RrOrderLine::class))->toBeNull();

    $this->registry->register(RrOrderLineResource::class);

    expect($this->registry->forModel(RrOrderLine::class))->toBe(RrOrderLineResource::class);
});

// ─── Registration from a config list ─────────────────────────────────────────

it('registers everything a config list names', function () {
    // The path the docs recommend, and the one that had no test: the registry
    // was covered, how anything gets into it was not.
    $this->registry->registerMany([RrOrderResource::class, RrReportResource::class]);

    expect($this->registry->all())->toBe([
        'rr-orders' => RrOrderResource::class,
        'rr-reports' => RrReportResource::class,
    ]);
});

it('ignores a config value that is not a list', function () {
    // Application config with a stray value should not take the boot down.
    foreach ([null, 'nonsense', 42] as $value) {
        $this->registry->registerMany($value);
    }

    expect($this->registry->all())->toBe([]);
});

it('skips a blank entry rather than fataling on it', function () {
    // A trailing comma in a published config leaves one behind, and '' would
    // otherwise reach class_implements() and die there.
    $this->registry->registerMany(['', RrOrderResource::class]);

    expect($this->registry->all())->toBe(['rr-orders' => RrOrderResource::class]);
});

it('is what the core provider hands the configured list to', function () {
    // One assertion that the wiring exists at all, so moving the rule into the
    // registry cannot quietly orphan it.
    $source = (string) file_get_contents(
        (new ReflectionClass(WireCoreServiceProvider::class))->getFileName()
    );

    expect($source)->toContain("registerMany(config('wire-core.resources'");
});
