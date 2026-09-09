<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Contracts;

/**
 * The two questions a surface must ask before it runs someone else's action.
 *
 * The sibling of {@see RunsComponentActions}, and separate from it on purpose:
 * that contract *does* a thing, this one only answers about one. A surface that
 * runs whatever it is given needs the first; a surface that has to decide
 * whether it may, and whether it can, needs both.
 *
 * Both answers live on `Actions\BaseAction` — behind `canExecute()` and
 * `hasModal()` — and a surface in another L2 module may not import it. So the
 * questions are asked through here and answered on the far side of the
 * container, the same crossing {@see RunsComponentActions} already makes.
 *
 * ## Why the caller cannot skip these
 *
 * `runComponentAction()` checks **nothing**. Not visibility, not authorization,
 * not whether the action was going to open a modal and ask. That is deliberate —
 * it is a runner, not a lifecycle — but it means a surface that lists actions to
 * a user and runs the one they pick has to do both checks itself, or it will
 * cheerfully run an action the user was never allowed to see and silently swallow
 * the confirmation the author wrote.
 *
 * The widget host is the cautionary example rather than the model: it filters on
 * `isHidden()` alone, which is visibility without authorization.
 */
interface ClassifiesComponentActions
{
    /**
     * Whether this action was going to ask something before it acted.
     *
     * True for a confirmation, a form, an infolist or a wizard — anything the
     * author attached a modal to. A surface that cannot host a modal must not
     * run one of these; it has to hand the action to something that can, or not
     * offer it at all.
     *
     * This is the same discriminator the action runtime itself branches on when
     * it decides between mounting an action and running it standalone, so a
     * surface asking here and the modal host asking there cannot disagree.
     */
    public function needsPrompt(ActionContract $action): bool;

    /**
     * Whether the current user may run this action, in this context.
     *
     * Visibility *and* authorization — `permission()`, `authorize()` and
     * `authorizeUsing()` included. Fails closed: an action whose gate throws is
     * not runnable.
     *
     * @param  mixed  $context  What the action's own conditions are evaluated
     *                          against — typically the record, or null for a
     *                          command that stands alone.
     */
    public function isRunnable(ActionContract $action, mixed $context = null): bool;
}
