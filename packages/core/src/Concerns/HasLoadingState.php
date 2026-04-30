<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Concerns;

use NyonCode\WireCore\Actions\Concerns\HasLoadingState;

/**
 * @deprecated Use {@see HasLoadingState} instead. Will be removed in v2.0.
 */
class_alias(HasLoadingState::class, 'NyonCode\WireCore\Concerns\HasLoadingState');
