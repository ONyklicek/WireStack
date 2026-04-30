<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Concerns;

use NyonCode\WireCore\Actions\Concerns\HasDynamicProperties;

/**
 * @deprecated Use {@see HasDynamicProperties} instead. Will be removed in v2.0.
 */
class_alias(HasDynamicProperties::class, 'NyonCode\WireCore\Concerns\HasDynamicProperties');
