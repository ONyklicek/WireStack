<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

use Livewire\Component;
use NyonCode\WireCore\Foundation\Support\IslandViewScope;
use NyonCode\WireForms\Forms\Form;

use function Livewire\store;

/**
 * Answer a field update with the fields that changed, not the whole view.
 *
 * Opt-in through {@see Form::fieldPartials()}. A
 * `wire:model` commit re-renders the host: on a 12-field form that is 19 860 B of
 * HTML to carry one field's 1 562 B, plus the browser morphing all of it.
 *
 * **It compares rendered markup; it does not reason about dependencies.** Every
 * visible field is rendered and stamped, and the stamps are compared with the
 * ones the last render left behind. That is the same shape as
 * `WithTable::queueChangedRowPartials()`, and it is what makes the feature safe
 * without a dependency graph: a sibling whose `options()`, `label()` or
 * `helperText()` closure reads the updated field's state comes out with different
 * markup, and different markup is the only thing this has to notice.
 *
 * Four outcomes, and the most common one sends nothing at all:
 *
 *  - **nothing moved.** A field's value rides `wire:model` and the data payload,
 *    not its markup — a `TextInput` renders no `value` attribute — so an ordinary
 *    keystroke commit changes no markup anywhere and the response carries neither
 *    a view nor a region;
 *
 *  - **the set of fields changed** — a `visibleWhen()` sibling appeared or
 *    disappeared. That is the page's shape moving, which no region can express;
 *  - **a field that carries no anchor changed.** Eight field views render no
 *    wrapper (hidden, alert, placeholder, view-field, html, repeater,
 *    repeater-table, builder), so there is nothing to morph into. Detected by
 *    looking for the anchor in the markup rather than by keeping a list, so it
 *    cannot drift as views change;
 *  - otherwise the changed fields are queued and the host's view is skipped.
 *
 * The stamps ride the snapshot, which is why they are only written where the
 * form asked for this.
 *
 * @phpstan-require-extends Component
 */
trait InteractsWithFieldPartials
{
    /**
     * crc32 of each field's markup, as this component last sent it.
     *
     * @var array<string, int>
     */
    public array $fieldPartialStamps = [];

    /** Request-scoped marker: the diff has already run for this commit. */
    private const RAN_KEY = 'wireFormsFieldPartialsRan';

    /**
     * Queue the fields whose markup moved, and say so if they cover the update.
     *
     * Called from the `updated` lifecycle hook, which runs after every property
     * in the request is set and before the render that would otherwise happen.
     */
    protected function queueChangedFieldPartials(): void
    {
        // Once per request, however many properties the commit carried. The diff
        // renders every field, so a second pass would render them all again to
        // reach the same answer — and would compare against the stamps the first
        // pass just wrote, making every field look unchanged.
        if (store($this)->get(self::RAN_KEY, false)) {
            return;
        }

        store($this)->set(self::RAN_KEY, true);

        $form = $this->form ?? null;

        if (! is_object($form) || ! method_exists($form, 'usesFieldPartials') || ! $form->usesFieldPartials()) {
            return;
        }

        $stamps = [];
        $markup = [];
        $unanchored = false;

        foreach ($form->getFlatComponents() as $component) {
            if (! method_exists($component, 'getStatePath') || ! $component->isVisible()) {
                continue;
            }

            $path = (string) $component->getStatePath();

            // Rendered the way the response will render it, or the comparison is
            // against markup nobody will ever be sent.
            $html = IslandViewScope::asLivewireRender(
                $this,
                fn (): string => $form->renderingFields(fn (): string => $component->toHtml()),
            );

            $stamps[$path] = crc32($html);
            $markup[$path] = $html;

            if (! str_contains($html, 'wire:partial="field-'.$path.'"')) {
                $unanchored = true;
            }
        }

        $previous = $this->fieldPartialStamps;
        $this->fieldPartialStamps = $stamps;

        // First update on this page, or a field appeared or disappeared: the
        // shape moved, and no set of regions describes that.
        if ($previous === [] || array_keys($previous) !== array_keys($stamps)) {
            return;
        }

        $changed = array_keys(array_filter(
            $stamps,
            fn (int $stamp, string $path): bool => ($previous[$path] ?? null) !== $stamp,
            ARRAY_FILTER_USE_BOTH,
        ));

        // Nothing moved, so there is nothing to send — and that is the common
        // case, not an edge one. A field's value rides `wire:model` and the data
        // payload rather than its markup: a `TextInput` renders no `value`
        // attribute at all, so committing one produces byte-identical markup.
        // Markup moves only when something *derived* moves — a dependent
        // sibling's options, a validation error, a disabled state.
        if ($changed === []) {
            $this->skipRender();

            return;
        }

        // A field with no anchor cannot be sent, so if one of them moved the whole
        // view has to run — whether or not it is one of the changed ones, because
        // the next update would compare against markup that was never delivered.
        if ($unanchored) {
            return;
        }

        foreach ($changed as $path) {
            $this->renderPartial('field-'.$path, fn (): string => $markup[$path]);
        }

        $this->coverUpdatesWithPartials();
    }
}
