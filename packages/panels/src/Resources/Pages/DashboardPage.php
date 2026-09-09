<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Pages;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use NyonCode\WireCore\Core\Plugin\Contracts\IdentifiesHookTarget;
use NyonCode\WireCore\Widgets\Concerns\WithWidgets;
use NyonCode\WireCore\Widgets\Dashboard;
use NyonCode\WireCore\Widgets\Widget;
use NyonCode\WirePanels\Exceptions\ResourcePageException;

/**
 * A full page rendering one dashboard's widgets.
 *
 * The same shape as {@see ListPage}: a Livewire component composing the host
 * trait, with the declaration coming from an owner rather than from the
 * component. Everything `WithWidgets` already does — stamping widget keys,
 * filtering by visibility, the grid, and answering a poll tick with one widget
 * instead of the page — arrives unchanged, because none of it knows a dashboard
 * exists.
 *
 *   class SalesDashboardPage extends DashboardPage
 *   {
 *       protected static ?string $dashboard = SalesDashboard::class;
 *   }
 *
 * Or write the widgets here and use no dashboard at all, exactly as any
 * `WithWidgets` component does — both paths are first class, as they are for
 * the resource pages.
 *
 * It deliberately does not route. A page is a Livewire component the application
 * mounts wherever it likes; the registry holds no URL shell, and this holds none
 * either.
 */
abstract class DashboardPage extends Component implements IdentifiesHookTarget
{
    // Aliased, not `parent::`: `getWidgetColumns()` comes from the trait, and
    // the parent of this class is Livewire\Component, which has no such method —
    // `parent::getWidgetColumns()` compiles and then throws BadMethodCallException
    // through Livewire's __call, at render time only.
    use WithWidgets {
        getWidgetColumns as private declaredWidgetColumns;
    }

    /**
     * The dashboard this page shows, or null when the page declares its own
     * widgets.
     *
     * @var class-string<Dashboard>|null
     */
    protected static ?string $dashboard = null;

    /** Optional heading; falls back to the dashboard's label. */
    protected ?string $title = null;

    /**
     * The key a *standalone* page's widget layout is stored under.
     *
     * Null, so a page that declares its own widgets is not customisable until
     * somebody says what to call it. The dashboard path needs none of this — a
     * registered dashboard already has a key that survives its class being
     * renamed, which is exactly what a stored layout has to be addressed by.
     *
     * A page declaring its widgets inline has no such key, and deriving one from
     * the class name would tie a user's saved layout to a class they do not
     * control: rename the page and every layout is orphaned, with no error to
     * say so. So it is named here or it does not exist.
     */
    protected static ?string $layoutKey = null;

    /** @return class-string<Dashboard>|null */
    public static function dashboardClass(): ?string
    {
        return static::$dashboard;
    }

    /**
     * The registered key a plugin hook can address this page's widgets by.
     *
     * What turns `hook(Hook::WidgetConfiguring, $cb, for: 'sales')` into
     * something an application can write: the widgets on this page are declared
     * inside a dashboard the application may not own, so the key that dashboard
     * registered under is the handle it has on them.
     *
     * Tolerant of a page that declares nothing, for the reason the resource
     * pages' `BelongsToResource::hookKey()` gives — a page rendering its own
     * widgets is scoped by class, and throwing here would turn a hook's absence
     * of scope into a render failure.
     */
    public function hookKey(): ?string
    {
        $dashboard = static::$dashboard;

        return $dashboard !== null && is_subclass_of($dashboard, Dashboard::class)
            ? $dashboard::key()
            : null;
    }

    /**
     * The key this page's widget layout is stored under, or null.
     *
     * The same bridge `hookKey()` above builds, and built the same way for the
     * same reason: the widgets are declared inside a dashboard the application
     * may not own, so the key that dashboard registered under is the handle
     * anything outside has on them.
     *
     * A page taking the standalone path — its own `getWidgets()`, no declared
     * dashboard — answers with whatever {@see $layoutKey} names, which is
     * nothing until somebody names it.
     */
    protected function widgetLayoutKey(): ?string
    {
        $dashboard = static::$dashboard;

        // The standalone path: no dashboard to ask, so the page says so itself
        // or stays uncustomisable. See {@see $layoutKey}.
        if ($dashboard === null || ! is_subclass_of($dashboard, Dashboard::class)) {
            return static::$layoutKey;
        }

        return $this->requireDashboard()->customisable() ? $dashboard::key() : null;
    }

    /**
     * The declared dashboard's widgets, or a clear refusal.
     *
     * Reached only on the dashboard path: a page taking the standalone path
     * overrides this, so control never arrives here.
     *
     * @return array<int, Widget>
     *
     * @throws ResourcePageException When nothing is declared or it is not a
     *                               dashboard — either of which would otherwise render as an empty
     *                               grid, and empty reads as "no widgets" rather than as a mistake.
     */
    protected function getWidgets(): array
    {
        return $this->requireDashboard()->widgets();
    }

    protected function getWidgetColumns(): int
    {
        return static::$dashboard !== null ? $this->requireDashboard()->columns() : $this->declaredWidgetColumns();
    }

    public function getTitle(): ?string
    {
        if ($this->title !== null) {
            return $this->title;
        }

        // Through the check, not around it: reading a label off an unvalidated
        // class throws "call to undefined method" from whatever it happens to
        // be, which is a worse message than the one this page has for exactly
        // this mistake — and it made the refusal below unreachable.
        return static::$dashboard !== null ? $this->requireDashboardClass()::label() : null;
    }

    public function render(): View
    {
        return view('wire-panels::pages.dashboard-page', array_merge(
            $this->widgetGridData($this->getWidgetColumns()),
            ['title' => $this->getTitle()],
        ));
    }

    /**
     * @throws ResourcePageException
     */
    private function requireDashboard(): Dashboard
    {
        $dashboard = $this->requireDashboardClass();

        return new $dashboard;
    }

    /**
     * The declared dashboard class, checked but not instantiated.
     *
     * Separate from {@see requireDashboard()} because the title needs the class
     * and not an instance: building one to read a static label would compose
     * every widget on the page to find out what to call it.
     *
     * @return class-string<Dashboard>
     *
     * @throws ResourcePageException
     */
    private function requireDashboardClass(): string
    {
        $dashboard = static::$dashboard;

        if ($dashboard === null) {
            throw ResourcePageException::noSource(static::class, Dashboard::class);
        }

        if (! is_subclass_of($dashboard, Dashboard::class)) {
            throw ResourcePageException::notAResource(static::class, $dashboard, Dashboard::class);
        }

        return $dashboard;
    }
}
