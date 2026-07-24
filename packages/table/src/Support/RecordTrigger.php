<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

/**
 * One way a record action can be fired: a click, double-click, right-click, a
 * key, or a future gesture. Deliberately an **open** value — `type` is a free
 * string, not an enum — so triple-click, long-press, swipe and user-registered
 * gestures can be added later without reshaping the public API. The built-in
 * types are named as constants for the resolver and JS controller to key on.
 */
final class RecordTrigger
{
    public const CLICK = 'click';

    public const DOUBLE_CLICK = 'dblclick';

    public const CONTEXT_MENU = 'contextmenu';

    public const KEY = 'key';

    /**
     * @param  string  $type  One of the built-in constants, or a custom gesture name.
     * @param  string|null  $key  The key name, set only for {@see self::KEY} triggers.
     */
    public function __construct(
        public readonly string $type,
        public readonly ?string $key = null,
    ) {}
}
