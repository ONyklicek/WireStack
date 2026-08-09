<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Support;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\BaseAction;
use NyonCode\WireCore\Actions\Contracts\ResolvesActionClick;

/**
 * A resolver that answers with one fixed expression, whatever it is asked.
 *
 * It exists so the button view can be rendered ONCE with a {@see
 * \NyonCode\WireCore\Foundation\View\Skeleton} slot standing in for the click
 * expression — the only per-record value that reaches a rendered button's markup.
 * Every row then splices its own expression into that compiled shape instead of
 * rendering the view again ({@see Action::render()}).
 *
 * Not a public extension point: a host describes how its actions are invoked with
 * a real resolver, and this one describes nothing.
 *
 * @internal
 */
final readonly class FixedClickResolver implements ResolvesActionClick
{
    public function __construct(private string $expression) {}

    public function clickHandler(BaseAction $action, ?Model $record): string
    {
        return $this->expression;
    }
}
