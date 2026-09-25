<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Console\Concerns;

use NyonCode\WirePanels\Resources\Console\Support\PanelSetup;

/**
 * How a generator ends: with the address of what it wrote, or with the steps
 * that still stand between the file and a page — and only those.
 *
 * A generator used to end with the same two reminders whatever the application
 * had already set up, which taught people to stop reading them. It asks
 * {@see PanelSetup} instead, so an application with discovery and
 * `Route::wireResources()` in place hears "ready, at /admin/orders", and one
 * without hears exactly which of the two is missing.
 */
trait ReportsNextSteps
{
    /**
     * @param  string  $key  The registered key — the URL segment.
     * @param  bool  $registered  Whether the class is registered, as far as the command knows.
     * @param  string  $registerHow  The step that registers this kind of class, said for this class.
     */
    protected function reportNextSteps(PanelSetup $setup, string $key, bool $registered, string $registerHow): void
    {
        $routed = $setup->isRouted();

        if ($registered && $routed) {
            $path = $setup->pathFor($key);

            $this->components->info($setup->knowsTheWholePath()
                ? "Ready: registered and routed at {$path}."
                : "Ready: registered and routed at {$path}, under the group your routes file puts Route::wireResources() in.");

            return;
        }

        $steps = [];

        if (! $registered) {
            $steps[] = $registerHow;
        }

        if (! $routed) {
            $steps[] = 'Give registered classes URLs, once for all of them: Route::wireResources() inside your panel\'s route group in routes/web.php, '
                ."or 'routes' => ['enabled' => true] in config/wire-panels.php.";
        }

        $this->components->bulletList($steps);
    }
}
