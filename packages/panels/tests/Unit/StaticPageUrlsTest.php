<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WirePanels\Pages\Page;
use NyonCode\WirePanels\Pages\PageRegistry;

/*
 * `OrderResource::url('edit', $order)` and `Board::url()`, end to end.
 *
 * The forwarder itself is pinned in wire-core; what only this package can show
 * is that the answer is the route the macro actually registered — in the zone
 * the group was named for, and with anything the route does not name carried in
 * the query string.
 */
class SpuListPage extends Component
{
    public function render(): string
    {
        return '<div>list</div>';
    }
}

class SpuEditPage extends SpuListPage {}

class SpuOrderResource implements DescribesResource, ProvidesPages
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }

    public static function key(): string
    {
        return 'spu-orders';
    }

    public static function pages(): array
    {
        return ['index' => SpuListPage::class, 'edit' => SpuEditPage::class];
    }
}

class SpuBoardPage extends Page
{
    protected static string $view = 'spu::content';
}

class SpuOrder extends Model
{
    protected $guarded = [];
}

beforeEach(function () {
    app(ResourceRegistry::class)->register(SpuOrderResource::class);
    app(PageRegistry::class)->register(SpuBoardPage::class);
});

it('links a resource page and a record page by the class alone', function () {
    Route::prefix('admin')->group(fn () => Route::wireResources());
    Route::getRoutes()->refreshNameLookups();

    expect(SpuOrderResource::url())->toBe(url('admin/spu-orders'))
        ->and(SpuOrderResource::url('edit', new SpuOrder(['id' => 7])))->toBe(url('admin/spu-orders/7/edit'))
        ->and(SpuOrderResource::url('view', 7))->toBeNull();
});

it('links a page of the application own, with its parameters in the query string', function () {
    Route::prefix('admin')->group(fn () => Route::wireResources());
    Route::getRoutes()->refreshNameLookups();

    expect(SpuBoardPage::url(parameters: ['week' => 12]))->toBe(url('admin/spu-board').'?week=12');
});

it('answers for the zone it is asked about', function () {
    Route::name('business.')->prefix('business')->group(fn () => Route::wireResources(only: ['spu-orders']));
    Route::getRoutes()->refreshNameLookups();

    expect(SpuOrderResource::url(zone: 'business'))->toBe(url('business/spu-orders'))
        ->and(SpuBoardPage::url(zone: 'business'))->toBeNull();
});
