<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Livewire\Features\SupportScriptsAndAssets\SupportScriptsAndAssets;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\Concerns\InteractsWithPartials;
use NyonCode\WireCore\Foundation\Icons\IconManager;
use NyonCode\WireCore\Foundation\Icons\IconSprite;
use NyonCode\WireCore\WireCoreServiceProvider;

/**
 * The icon sprite inside Livewire: every piece of markup Livewire sends whole —
 * a component render, an island render, a partial — defines the symbols it
 * references, so it is correct whichever of them the browser morphs in.
 */
class SpriteRowsHost extends Component
{
    use InteractsWithPartials;

    public int $rows = 10;

    public function editRow(): void
    {
        $this->renderPartial('row-1', fn (): string => '<tr wire:partial="row-1"><td>'.icon('pencil').icon('check').'</td></tr>');
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                <table><tbody>
                    @for ($i = 0; $i < $rows; $i++)
                        <tr><td>{!! icon('pencil') !!}</td><td><x-wire::icon name="trash" class="text-danger-600" /></td></tr>
                    @endfor
                </tbody></table>
                @island('footer')
                    <footer>{!! icon('arrow-path') !!}</footer>
                @endisland
            </div>
            BLADE;
    }
}

beforeEach(function () {
    config()->set('wire-core.icons.sprite', true);
    app()->forgetInstance(IconSprite::class);
});

it('draws each icon of a component once and references it in every row', function () {
    $html = Livewire::test(SpriteRowsHost::class)->html();

    // pencil, trash, arrow-path — three bodies for twenty-one icons.
    expect(substr_count($html, '<symbol id="wi-'))->toBe(3)
        ->and(substr_count($html, '<use href="#wi-'))->toBe(21)
        ->and(preg_replace('#<symbol.*?</symbol>#s', '', $html))->not->toContain('<path');
});

it('leaves the markup untouched with the sprite off', function () {
    config()->set('wire-core.icons.sprite', false);
    app()->forgetInstance(IconSprite::class);

    $html = Livewire::test(SpriteRowsHost::class)->html();

    expect($html)->not->toContain('<use')->not->toContain('<symbol')
        ->and(substr_count($html, '<svg'))->toBe(21);
});

it('keeps every attribute an icon carries on its own svg', function () {
    $sprited = Livewire::test(SpriteRowsHost::class)->html();

    config()->set('wire-core.icons.sprite', false);
    app()->forgetInstance(IconSprite::class);
    $inline = Livewire::test(SpriteRowsHost::class)->html();

    preg_match_all('/<svg[^>]*>/', $sprited, $spritedTags);
    preg_match_all('/<svg[^>]*>/', $inline, $inlineTags);

    expect($spritedTags[0])->toBe($inlineTags[0]);
});

it('defines in a partial the symbols the partial references', function () {
    $test = Livewire::test(SpriteRowsHost::class)->call('editRow');

    $row = $test->effects['wirePartials']['row-1'];

    // The browser morphs this row in alone — `check` appears nowhere else.
    expect(substr_count($row, '<symbol id="wi-'))->toBe(2)
        ->and($row)->toContain('<use href="#wi-');
});

it('defines in an island render the symbols the island references', function () {
    $test = Livewire::test(SpriteRowsHost::class);

    $test->instance()->renderIsland('footer');
    $island = implode('', array_map(fn ($f) => is_array($f) ? ($f['content'] ?? '') : (string) $f, $test->instance()->getRenderedIslandFragments()));

    expect($island)->toContain('<symbol id="wi-')->toContain('<use href="#wi-');
});

it('sends the browser half with the first component that draws a reference', function (bool $on, bool $sent) {
    config()->set('wire-core.icons.sprite', $on);
    app()->forgetInstance(IconSprite::class);
    // Livewire's own per-request registry; a real request starts with it empty.
    SupportScriptsAndAssets::$alreadyRunAssetKeys = [];
    SupportScriptsAndAssets::$renderedAssets = [];

    Route::get('/sprite-page', fn () => Blade::render('<html><head></head><body>@livewire(SpriteRowsHost::class)</body></html>'))
        ->middleware('web');

    $page = $this->get('/sprite-page')->assertOk()->getContent();

    expect(str_contains($page, '/wire-core/assets/icons.js'))->toBe($sent);
})->with([
    'sprite on' => [true, true],
    'sprite off' => [false, false],
]);

it('ships the browser half inside the package', function () {
    $bundle = WireCoreServiceProvider::ASSETS_PATH.'/wire-core-icons.js';

    expect(is_file($bundle))->toBeTrue()
        ->and(file_get_contents($bundle))->toContain('wire-icon-sprite');

    $this->get('/wire-core/assets/icons.js')->assertOk();
});

it('draws inline again once the render is over — a PDF from an action stays whole', function () {
    Livewire::test(SpriteRowsHost::class);

    expect(app(IconManager::class)->render('pencil'))->toContain('<path')->not->toContain('<use');
});
