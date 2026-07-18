<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * An action that resolves its full button render state in PHP.
 *
 * The canonical action button view consumes this array instead of probing the
 * action with `method_exists()`. Implementers resolve every per-record dynamic
 * property (color, icon, label, disabled, extra attributes) plus the host-supplied
 * click expression up front, so the Blade view only echoes resolved state.
 */
interface RendersAsButton
{
    /**
     * Resolve all render data for the button view.
     *
     * @return array<string, mixed>
     */
    public function toButtonRenderArray(?Model $record = null, ?ResolvesActionClick $click = null): array;
}
