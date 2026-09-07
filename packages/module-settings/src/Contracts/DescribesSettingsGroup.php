<?php

declare(strict_types=1);

namespace NyonCode\WireModuleSettings\Contracts;

/**
 * The presentation half of a settings group, for a group that wants one.
 *
 * Opt-in, and separate from {@see SettingsGroup} for the reason every other
 * surface contract here is separate: a group that only needs a heading and a
 * schema stays four lines, and does not grow three methods returning null so
 * that one group elsewhere can have an icon.
 *
 *   final class MailSettings implements SettingsGroup, DescribesSettingsGroup
 *   {
 *       public static function icon(): ?string { return 'outline:envelope'; }
 *
 *       public static function description(): ?string
 *       {
 *           return __('Where transactional mail is sent from.');
 *       }
 *
 *       public static function sort(): int { return 20; }
 *   }
 *
 * `sort()` is the switcher's order. Groups that declare none keep the order the
 * config listed them in, and PHP's sort has been stable since 8.0, so a group
 * that declares a number does not shuffle the ones that do not.
 */
interface DescribesSettingsGroup
{
    /** The icon beside the group in the switcher, in the framework's own notation. */
    public static function icon(): ?string;

    /** A line under the heading saying what this group is for. */
    public static function description(): ?string;

    /** Where the group sits in the switcher. Lower is earlier. */
    public static function sort(): int;
}
