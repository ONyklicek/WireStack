<?php

declare(strict_types=1);

namespace NyonCode\WireModuleSettings\Support;

use Illuminate\Contracts\Support\Htmlable;
use NyonCode\WireModuleSettings\Contracts\ConfirmsSettingsSave;
use NyonCode\WireModuleSettings\Contracts\ExtendsSettingsScreen;
use NyonCode\WireModuleSettings\Contracts\RendersSettingsScreen;
use NyonCode\WireModuleSettings\Contracts\SettingsGroup;
use NyonCode\WireModuleSettings\Contracts\SpansSettingsGroups;
use NyonCode\WireModuleSettings\Contracts\TransformsSettings;
use NyonCode\WireModuleSettings\Contracts\ValidatesSettings;

/**
 * A settings group's values on their way between storage and its form.
 *
 * The one owner of what a group's optional contracts change about that trip —
 * where its keys are stored ({@see SpansSettingsGroups}), how they are shaped
 * for the form and back ({@see TransformsSettings}), what is checked across
 * fields ({@see ValidatesSettings}) — so the screen calls three methods and does
 * not grow a branch per contract, and a group without any of them is exactly the
 * group it was before they existed.
 */
final class SettingsGroupValues
{
    /**
     * The form's state for a group: stored values over declared defaults,
     * gathered from every storage group it spans, shaped for the form.
     *
     * @param  class-string<SettingsGroup>  $class
     * @return array<string, mixed>
     */
    public static function load(string $class): array
    {
        $values = Settings::all($class::group());

        foreach (self::elsewhere($class) as $storage => $keys) {
            $stored = Settings::all($storage);

            // Only what is stored there moves over the group's own answer: a key
            // nobody wrote yet keeps the default the group declared for it,
            // rather than turning into null because its storage group has none.
            foreach ($keys as $key) {
                if (array_key_exists($key, $stored)) {
                    $values[$key] = $stored[$key];
                }
            }
        }

        return is_a($class, TransformsSettings::class, true) ? $class::fromStorage($values) : $values;
    }

    /**
     * Messages for the fields that fail the group's own cross-field rule.
     *
     * @param  class-string<SettingsGroup>  $class
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public static function problems(string $class, array $data): array
    {
        return is_a($class, ValidatesSettings::class, true) ? $class::validateSettings($data) : [];
    }

    /**
     * Store a group's validated form state: shaped for storage, split across
     * the storage groups it spans, written in one transaction.
     *
     * @param  class-string<SettingsGroup>  $class
     * @param  array<string, mixed>  $data
     * @return array<string, array<string, mixed>> What was written, by storage group.
     */
    public static function save(string $class, array $data): array
    {
        $values = is_a($class, TransformsSettings::class, true) ? $class::toStorage($data) : $data;

        $groups = [$class::group() => $values];

        foreach (self::elsewhere($class) as $storage => $keys) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $values)) {
                    $groups[$storage][$key] = $values[$key];
                    unset($groups[$class::group()][$key]);
                }
            }
        }

        Settings::fillMany($groups);

        return $groups;
    }

    /**
     * What the group shows under its form, from the form's current state.
     *
     * @param  class-string<SettingsGroup>  $class
     * @param  array<string, mixed>  $data
     */
    public static function extension(string $class, array $data): ?Htmlable
    {
        return is_a($class, ExtendsSettingsScreen::class, true) ? $class::screenExtension($data) : null;
    }

    /**
     * The Livewire component drawn in place of the form, when the group has one.
     *
     * @param  class-string<SettingsGroup>  $class
     */
    public static function component(string $class): ?string
    {
        return is_a($class, RendersSettingsScreen::class, true) ? $class::component() : null;
    }

    /**
     * The group's own confirmation, when it words one.
     *
     * @param  class-string<SettingsGroup>  $class
     */
    public static function savedMessage(string $class): ?string
    {
        return is_a($class, ConfirmsSettingsSave::class, true) ? $class::savedMessage() : null;
    }

    /**
     * Keys the group keeps outside its own storage, by the storage group holding them.
     *
     * Its own storage name listed here is ignored rather than honoured: the
     * keys are in it already, and treating it as "elsewhere" would move them out
     * of the group and back in again.
     *
     * @param  class-string<SettingsGroup>  $class
     * @return array<string, list<string>>
     */
    private static function elsewhere(string $class): array
    {
        if (! is_a($class, SpansSettingsGroups::class, true)) {
            return [];
        }

        $storage = $class::storage();

        unset($storage[$class::group()]);

        return array_map(static fn (array $keys): array => array_map('strval', $keys), $storage);
    }
}
