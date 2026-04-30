<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Concerns;

use NyonCode\WireCore\Actions\Concerns\HasLifecycle;

/**
 * @deprecated Use {@see HasLifecycle} instead. Will be removed in v2.0.
 */
class_alias(HasLifecycle::class, 'NyonCode\WireCore\Concerns\HasLifecycle');
