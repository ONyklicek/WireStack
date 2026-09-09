<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Contracts;

/**
 * A registered thing that offers actions to a command surface.
 *
 * Opt-in, and separate from every other capability for the reason they all are:
 * something that should offer no commands says so by not implementing this,
 * rather than by returning an empty array from a method it was forced to have.
 *
 * ## Why this lives in Foundation
 *
 * Every other capability contract lives in the module that owns the type its
 * signature names — `ProvidesResourceTable` in wire-panels, `ProvidesResourceForm`
 * in wire-forms, `ProvidesResourceInfolist` beside the Infolists surface. By that
 * rule this one would belong in `Actions/`, and then the command palette could
 * not read it: `GlobalSearch` and `Actions` are sibling L2 modules and neither
 * may import the other.
 *
 * It names {@see ActionContract} instead of `Actions\Action`, and that contract
 * is already Foundation. So the rule is satisfied rather than bent — this *is*
 * the lowest layer that can own the signature.
 *
 * ## One method, not two
 *
 * `$record` is what separates the two things a palette wants: a command that
 * stands alone ("Recount stock") and a command about something it just found
 * ("Order #412 → Cancel"). Splitting them into two contracts would make a
 * resource that wants both implement two interfaces to answer one question, and
 * would leave the palette asking twice per keystroke.
 *
 * Static, like identity and navigation: a palette asks every registered class
 * what it offers before anything is instantiated, and a surface that had to
 * construct fifty resources to draw one list is the reason nobody turns it on.
 *
 *   public static function commands(?object $record = null): array
 *   {
 *       return $record === null
 *           ? [Action::make('recount')->label('Recount stock')->action(fn () => …)]
 *           : [Action::make('cancel')->requiresConfirmation()->action(fn () => …)];
 *   }
 *
 * Whether a returned action can simply be run, or has to ask first, is not this
 * contract's business — see {@see ClassifiesComponentActions}.
 */
interface ProvidesCommands
{
    /**
     * The actions this offers, optionally about one record.
     *
     * @param  object|null  $record  An instance of this thing's model when the
     *                               palette is asking about one, null when it is
     *                               asking what stands on its own.
     * @return array<int, ActionContract>
     */
    public static function commands(?object $record = null): array;
}
