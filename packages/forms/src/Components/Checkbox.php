<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;
use Illuminate\Session\Store;
use NyonCode\WireCore\Foundation\Concerns\HasExtraInputAttributes;
use NyonCode\WireForms\Concerns\CanSubmitNatively;
use NyonCode\WireForms\Contracts\SupportsNativeSubmit;

/**
 * Single checkbox field with optional description.
 */
class Checkbox extends Field implements SupportsNativeSubmit
{
    use CanSubmitNatively;
    use HasExtraInputAttributes;

    protected string|Closure|null $description = null;

    protected bool $inline = false;

    /** Set the descriptive text shown beside the checkbox. */
    public function description(string|Closure|null $description): static
    {
        $this->description = $description;

        return $this;
    }

    /** Render the label inline with the checkbox instead of above it. */
    public function inline(bool $condition = true): static
    {
        $this->inline = $condition;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->evaluate($this->description);
    }

    public function isInline(): bool
    {
        return $this->inline;
    }

    public function getStateType(): string
    {
        return 'bool';
    }

    /**
     * `name`, the value a tick posts, and whether it starts ticked.
     *
     * The trait's version is wrong here and quietly so: a checkbox does not
     * carry its state in `value` — it carries a constant there and answers with
     * its *presence*. An unticked box posts no key at all, which is what makes
     * `remember` on a sign-in form a boolean the browser can express.
     *
     * Which is also why `old()` alone cannot decide the tick. `old('remember')`
     * is null both for a fresh page and for a submission that came back with the
     * box cleared, so a default of `true` would silently re-tick a box the user
     * had just unticked. The question is whether there is *any* old input: if
     * there is, the last submission decides; if there is not, the default does.
     */
    public function getNativeBindingHtml(): string
    {
        $name = $this->getName();

        return 'name="'.e($name).'" value="1"'.($this->isNativelyChecked() ? ' checked' : '');
    }

    private function isNativelyChecked(): bool
    {
        $session = request()->hasSession() ? request()->session() : null;

        // The concrete store rather than the contract: `hasOldInput()` and
        // `getOldInput()` are the flash-data half of a session and live on
        // `Store`, which is what a request actually carries.
        if ($session instanceof Store && $session->hasOldInput()) {
            return (bool) $session->getOldInput($this->getName());
        }

        return (bool) $this->getDefault();
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.checkbox';
    }
}
