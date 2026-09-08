<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\Icons\IconManager;
use NyonCode\WireForms\Components\MarkdownEditor;
use NyonCode\WireForms\Components\Rating;
use NyonCode\WireForms\Components\RichEditor;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;
use NyonCode\WireForms\Support\Icons\FormsIconSet;

/*
 * The glyphs wire-forms owns.
 *
 * Bold, the alignment bars, the quote mark and the rating star are not in
 * Heroicons, and they used to be inline <svg> in five templates — which is what
 * the Icons rule forbids, and how the quote mark came to be written out three
 * times. They are an icon set now, so the assertions are about the pipeline as
 * much as the artwork: the set answers under its prefix, the toolbars reach it
 * through icon(), and no template draws an <svg> of its own any more.
 */

it('answers for its own glyphs and for nothing else', function () {
    $set = new FormsIconSet;

    expect($set->has('bold'))->toBeTrue()
        ->and($set->names())->toContain('blockquote', 'align-left', 'undo')
        // Heroicons stays the owner of everything it already has.
        ->and($set->has('pencil'))->toBeFalse()
        ->and($set->getPath('pencil'))->toBeNull()
        // And core owns what crosses a package boundary: the star is drawn by
        // the Rating field and by the table's RatingColumn, so it is `wire:star`
        // and not this package's to keep.
        ->and($set->has('star'))->toBeFalse()
        ->and($set->has('star-outline'))->toBeFalse();
});

it('describes its own format, so the glyphs are not scaled as 20x20 solid', function () {
    // A plain IconSet is wrapped in the Heroicons solid format. These are 24x24,
    // so the set implements ProvidesIconMetadata and says so itself.
    $icon = (new FormsIconSet)->getIcon('bold');

    expect($icon)->not->toBeNull()
        ->and($icon->viewBox)->toBe('0 0 24 24')
        ->and($icon->attributes)->toBe(['fill' => 'currentColor'])
        ->and((new FormsIconSet)->getIcon('nope'))->toBeNull();
});

it('is registered under the forms prefix at boot', function () {
    $icons = app(IconManager::class);

    expect($icons->has('forms:bold'))->toBeTrue()
        ->and($icons->render('forms:bold', 'w-4 h-4'))
        ->toContain('viewBox="0 0 24 24"')
        ->toContain('class="w-4 h-4"');
});

/** A host, because a field's markup entangles against a Livewire component. */
class IconFieldHost extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = [];

    public string $type = 'rich';

    public function mount(string $type = 'rich'): void
    {
        $this->type = $type;
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([match ($this->type) {
            'markdown' => MarkdownEditor::make('body'),
            'rating' => Rating::make('body')->allowHalf(),
            default => RichEditor::make('body'),
        }]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

it('draws every toolbar and the rating stars through icon(), never an inline svg', function (string $type, string $glyph) {
    $html = Livewire::test(IconFieldHost::class, ['type' => $type])->html();

    $svgs = substr_count($html, '<svg');
    $stamped = preg_match_all('/<svg[^>]*aria-hidden="true"/', $html);

    // Every <svg> in the output came out of IconManager, which stamps each one it
    // resolves. A hand-written one in a template would not be stamped.
    expect($svgs)->toBeGreaterThan(0)
        ->and($stamped)->toBe($svgs)
        ->and($html)->toContain($glyph);
})->with([
    'rich' => ['rich', 'M4.583 17.321'],
    'markdown' => ['markdown', 'M4.583 17.321'],
    // The rating draws core's star; that it resolves at all is the assertion.
    'rating' => ['rating', 'M12 2l3.09 6.26L22 9.27'],
]);

it('names no glyph the toolbars ask for and the set does not have', function () {
    // The Tiptap toolbar is a map of names now, not of markup, so a typo would
    // draw the fallback icon rather than failing. This is what notices.
    $views = glob(dirname(__DIR__, 3).'/resources/views/components/*.blade.php') ?: [];
    $icons = app(IconManager::class);
    $asked = [];

    foreach ($views as $view) {
        preg_match_all("/'(forms:[a-z0-9-]+)'/", (string) file_get_contents($view), $matches);
        $asked = [...$asked, ...$matches[1]];
    }

    $asked = array_values(array_unique($asked));
    $missing = array_values(array_filter($asked, fn (string $name): bool => ! $icons->has($name)));

    expect($asked)->not->toBeEmpty()
        ->and($missing)->toBe([]);
});
