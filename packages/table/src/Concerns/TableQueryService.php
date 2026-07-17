<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use NyonCode\WireTable\Services\TableQueryService;

/**
 * It was never a concern — a final class in a directory the coding standard
 * reserves for traits. It now lives in `Services/`.
 *
 * @deprecated Use {@see TableQueryService} instead. Will be removed in v2.0.
 */
class_alias(TableQueryService::class, 'NyonCode\WireTable\Concerns\TableQueryService');
