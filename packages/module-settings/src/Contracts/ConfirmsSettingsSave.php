<?php

declare(strict_types=1);

namespace NyonCode\WireModuleSettings\Contracts;

/**
 * A settings group that says in its own words that it was saved.
 *
 * "Settings saved." is right for a screen that edits one thing. On a screen
 * with several groups, the message is the only thing confirming *which* group
 * was written — and a person who changed the mail settings and reads "Settings
 * saved." has to trust it was the mail ones.
 */
interface ConfirmsSettingsSave
{
    /** The notification shown after the group is saved. */
    public static function savedMessage(): string;
}
