<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Foundation\Registration\Contracts\HasRegistryKey;
use NyonCode\WireCore\Foundation\Routing\Concerns\InteractsWithPageUrls;
use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;

/*
 * A class asked where its own pages are.
 *
 * The forwarder is thin on purpose, so what is worth pinning is what it hands
 * the resolver: the class's own key, a model reduced to its key, and the zone
 * it defaults to. A resolver that records the question is the whole fixture.
 */
class IpuOrderResource implements DescribesResource
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }

    public static function key(): string
    {
        return 'ipu-orders';
    }
}

class IpuBoard implements HasRegistryKey
{
    use InteractsWithPageUrls;

    public static function key(): string
    {
        return 'board';
    }
}

class IpuOrder extends Model
{
    protected $guarded = [];
}

final class IpuRecordingUrls implements ResolvesPageUrls
{
    /** @var array<int, array{0: string, 1: string, 2: array<string, mixed>, 3: ?string}> */
    public array $asked = [];

    public function urlFor(string $key, string $page = 'index', array $parameters = [], ?string $zone = null): ?string
    {
        $this->asked[] = [$key, $page, $parameters, $zone];

        return "/{$key}/{$page}";
    }
}

beforeEach(function () {
    $this->urls = new IpuRecordingUrls;
    app()->instance(ResolvesPageUrls::class, $this->urls);
});

it('asks for the index page under the class key when given nothing', function () {
    expect(IpuBoard::url())->toBe('/board/index')
        ->and($this->urls->asked)->toBe([['board', 'index', [], null]]);
});

it('comes with DescribesRecords, so every resource already has it', function () {
    expect(IpuOrderResource::url('create'))->toBe('/ipu-orders/create');
});

it('reduces a record model to its key rather than its route key', function () {
    // The page resolves `{record}` as a key; a model whose route key is a slug
    // would otherwise produce a URL the page cannot find a record for.
    $order = new class(['id' => 7]) extends IpuOrder
    {
        public function getRouteKey(): mixed
        {
            return 'slug-seven';
        }
    };

    IpuOrderResource::url('edit', $order);

    expect($this->urls->asked[0][2])->toBe(['record' => 7]);
});

it('passes a scalar record through and keeps further parameters, models reduced too', function () {
    IpuOrderResource::url('edit', 7, ['parent' => new IpuOrder(['id' => 3]), 'tab' => 'lines']);

    expect($this->urls->asked[0][2])->toBe(['parent' => 3, 'tab' => 'lines', 'record' => 7]);
});

it('forwards an explicit zone and a null answer untouched', function () {
    app()->instance(ResolvesPageUrls::class, new class implements ResolvesPageUrls
    {
        public function urlFor(string $key, string $page = 'index', array $parameters = [], ?string $zone = null): ?string
        {
            return $zone === 'business' ? '/business/board' : null;
        }
    });

    expect(IpuBoard::url(zone: 'business'))->toBe('/business/board')
        ->and(IpuBoard::url())->toBeNull();
});
