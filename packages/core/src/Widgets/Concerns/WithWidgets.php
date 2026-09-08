<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use NyonCode\WireCore\Core\Plugin\HookDispatch;
use NyonCode\WireCore\Core\Plugin\Hooks\WidgetConfiguringPayload;
use NyonCode\WireCore\Core\Plugin\HookTarget;
use NyonCode\WireCore\Exceptions\WidgetLayoutException;
use NyonCode\WireCore\Foundation\Concerns\InteractsWithPartials;
use NyonCode\WireCore\Foundation\Contracts\RunsComponentActions;
use NyonCode\WireCore\Foundation\Enums\Hook;
use NyonCode\WireCore\Foundation\Preferences\Contracts\PreferenceDriver;
use NyonCode\WireCore\Foundation\Preferences\PreferenceManager;
use NyonCode\WireCore\Widgets\Support\WidgetLayout;
use NyonCode\WireCore\Widgets\Widget;

/** @phpstan-require-extends Component */
trait WithWidgets
{
    use InteractsWithPartials;

    /**
     * The declared widgets, after anything installed has had its say.
     *
     * Memoized for the request, and that is not only a saving: `getVisibleWidgets()`
     * is called by the render and again by a poll tick, and a hook that ran twice
     * would append the same widget twice on the second call.
     *
     * @var array<int, Widget>|null
     */
    private ?array $configuredWidgets = null;

    /**
     * The filter selection each widget is showing, keyed by widget key.
     *
     * Public because it is state that has to survive the round trip, and a
     * widget cannot hold it: a widget is rebuilt from `getWidgets()` on every
     * request, so anything it was told in the browser is gone by the time the
     * next render asks it a question. The host is the only thing on this page
     * that persists, so the host keeps the selection and pushes it back in.
     *
     * @var array<string, string>
     */
    public array $widgetFilters = [];

    /**
     * Where an application configures what a dashboard layout is stored in.
     *
     * A prefix of its own, not the table's: whether a column layout is worth a
     * database row and whether a dashboard layout is are separate questions with
     * separate right answers. With nothing configured the resolver answers with
     * the null driver, so opting a dashboard in without choosing a store leaves
     * it working and forgetful rather than broken.
     */
    private const WIDGET_PREFERENCE_CONFIG = 'wire-core.preferences';

    /** @var WidgetLayout|null Memoized for the request; see {@see WidgetLayout()}. */
    private ?WidgetLayout $widgetLayout = null;

    /**
     * Whether the user is rearranging this dashboard right now.
     *
     * An explicit mode rather than always-live handles, and the reason is the
     * write: a live drag persists on every drop, so a stray grab overwrites a
     * layout somebody was happy with and there is nothing to undo it. A mode has
     * a Cancel.
     */
    public bool $editingWidgets = false;

    /**
     * The layout being edited, before anybody agreed to keep it.
     *
     * Public because it has to survive the round trips a drag makes, and
     * deliberately separate from what is stored: nothing here reaches the
     * preference driver until {@see saveWidgetLayout()} says so.
     *
     * @var array<int, array{key: string, w: int, h: int}>
     */
    public array $widgetLayoutDraft = [];

    /**
     * The keys of the widgets whose deferred first render has already happened.
     *
     * Same reason as above, and one consequence worth stating: this only ever
     * grows. A widget that has been fetched stays fetched for the life of the
     * component, so a poll tick or a filter change afterwards re-renders the
     * real widget instead of dropping back to the skeleton.
     *
     * @var array<int, string>
     */
    public array $loadedWidgets = [];

    /**
     * @return array<int, Widget>
     */
    abstract protected function getWidgets(): array;

    /**
     * Number of columns in the widget grid (1-4).
     */
    protected function getWidgetColumns(): int
    {
        return 2;
    }

    /**
     * Get only visible widgets.
     *
     * Each one is stamped with a key it can be addressed by across a round trip
     * (see {@see Widget::key()}), derived from its position in `getWidgets()` —
     * the unfiltered list, so that hiding one does not renumber the rest. A
     * widget that was given its own key keeps it.
     *
     * @return array<int, Widget>
     */
    public function getVisibleWidgets(): array
    {
        $declared = $this->stampedWidgets();

        // After the keys, because a layout addresses widgets by key. Before the
        // visibility filter, because the two answer different questions: a
        // layout says what this *user* put on the dashboard, and `visible()`
        // says what this user is allowed to see. A widget a layout places and a
        // policy hides stays hidden — the layout is a preference, never a grant.
        $placed = $this->widgetLayout()->apply($declared);

        return array_values(array_filter($placed, fn (Widget $widget): bool => $widget->isVisible()));
    }

    /**
     * Every declared widget, with a key stamped on the ones that had none.
     *
     * One owner, because three things now need the same list at the same moment
     * in its life: the grid, the tray, and looking a widget up by key when one
     * is added. Memoized with `configuredWidgets()` underneath it, so the hook
     * still fires once.
     *
     * @return array<int, Widget>
     *
     * @throws WidgetLayoutException When a customisable dashboard has a widget
     *                               with no key of its own — see the refusal below.
     */
    private function stampedWidgets(): array
    {
        $stamped = [];
        $layoutKey = $this->widgetLayoutKey();

        foreach ($this->configuredWidgets() as $index => $widget) {
            if ($widget->getKey() === null) {
                // A derived key is a *position*, and a stored layout addresses
                // widgets by key — so on a dashboard somebody rearranges, the
                // first widget inserted at the top makes every saved layout
                // describe different widgets than it did yesterday. The page
                // still renders, which is what makes it worth refusing here
                // rather than documenting.
                if ($layoutKey !== null) {
                    throw WidgetLayoutException::widgetHasNoKey($layoutKey, $index, $widget::class);
                }

                $widget->key('w'.$index);
            }

            $this->applyWidgetState($widget);

            $stamped[] = $widget;
        }

        return $stamped;
    }

    /** The declared widget answering to a key, whatever the layout has done with it. */
    private function declaredWidget(string $key): ?Widget
    {
        foreach ($this->stampedWidgets() as $widget) {
            if ($widget->getKey() === $key) {
                return $widget;
            }
        }

        return null;
    }

    /**
     * The key this host's widget layout is stored under, or null for a dashboard
     * nobody may rearrange.
     *
     * Null by default, which is what keeps every dashboard that exists today
     * exactly as it is: no key, no store lookup, no layout, and the declaration
     * renders as it always has. Opting in is naming a key — a dashboard's, a
     * page's, whatever stays stable while the widgets on it change.
     *
     * `WirePanels\Resources\Pages\DashboardPage` overrides this with the
     * declared dashboard's key when that dashboard says `customisable()`, the
     * same bridge it already builds for `hookKey()`.
     */
    protected function widgetLayoutKey(): ?string
    {
        return null;
    }

    /**
     * Which saved layout to show — null is the one the user is working in.
     *
     * The store has carried named views since it was a table's, and a dashboard
     * gets them for nothing: "my morning layout" is the same bag under a name.
     * Nothing here reads it yet beyond passing it through, and that is the point
     * of it being a method rather than a constant.
     */
    protected function widgetLayoutView(): ?string
    {
        return null;
    }

    /**
     * This user's stored layout, or "the declaration decides".
     *
     * Memoized for the request: `getVisibleWidgets()` is called by the render and
     * again by any tick that answers with one widget, and a dashboard should not
     * hit its preference store twice to draw itself once.
     */
    private function widgetLayout(): WidgetLayout
    {
        if ($this->widgetLayout instanceof WidgetLayout) {
            return $this->widgetLayout;
        }

        $key = $this->widgetLayoutKey();

        if ($key === null) {
            return $this->widgetLayout = WidgetLayout::none();
        }

        // While editing, the draft *is* the layout: the grid has to show what
        // the user is arranging, not what they last agreed to keep.
        if ($this->editingWidgets) {
            return $this->widgetLayout = WidgetLayout::of($this->widgetLayoutDraft);
        }

        $bag = $this->widgetPreferenceDriver()->load(
            $key,
            $this->widgetPreferenceUser(),
            $this->widgetLayoutView(),
        );

        return $this->widgetLayout = WidgetLayout::fromBag($bag);
    }

    /**
     * Let anything installed add a widget before the keys are stamped.
     *
     * Before, deliberately: a key is derived from a widget's index in this list,
     * so a widget appended after stamping would either carry no key — and be
     * unreachable by a poll tick — or take a key another widget already answers
     * to. And before the visibility filter, so an added widget's own `visible()`
     * is honoured rather than skipped.
     *
     * @return array<int, Widget>
     */
    private function configuredWidgets(): array
    {
        if ($this->configuredWidgets !== null) {
            return $this->configuredWidgets;
        }

        $widgets = $this->getWidgets();

        $payload = HookDispatch::typed(Hook::WidgetConfiguring, fn () => new WidgetConfiguringPayload(
            host: $this,
            widgets: $widgets,
            target: HookTarget::for('widget', $this),
        ));

        if ($payload !== null) {
            /** @var array<int, Widget> $widgets */
            $widgets = array_values($payload->widgets);
        }

        return $this->configuredWidgets = $widgets;
    }

    /**
     * Begin rearranging, from whatever the dashboard looks like now.
     *
     * The draft starts as the *effective* layout — the stored one if there is
     * one, the declaration if there is not — so a dashboard nobody has touched
     * is dragged exactly like a saved one, and the first drop does not have to
     * invent a starting order.
     */
    public function startEditingWidgets(): void
    {
        if ($this->widgetLayoutKey() === null) {
            return;
        }

        $layout = $this->widgetLayout();

        $this->widgetLayoutDraft = ($layout->isDeclared()
            ? WidgetLayout::fromWidgets($this->getVisibleWidgets())
            : $layout)->toBag()['widgets'];

        $this->editingWidgets = true;

        // The memo above was resolved before the mode opened, so it still holds
        // the pre-edit layout — and everything asked afterwards, the tray
        // included, would answer from it. Cleared here for the same reason every
        // other mutator clears it.
        $this->widgetLayout = null;
    }

    /** Put the dashboard back the way it was; the draft is thrown away unsaved. */
    public function cancelEditingWidgets(): void
    {
        $this->editingWidgets = false;
        $this->widgetLayoutDraft = [];
        $this->widgetLayout = null;
    }

    /** Keep the arrangement: the draft becomes what this user sees from now on. */
    public function saveWidgetLayout(): void
    {
        $key = $this->widgetLayoutKey();

        if ($key === null) {
            return;
        }

        $this->widgetPreferenceDriver()->save(
            $key,
            $this->widgetPreferenceUser(),
            WidgetLayout::of($this->widgetLayoutDraft)->toBag(),
            $this->widgetLayoutView(),
        );

        $this->editingWidgets = false;
        $this->widgetLayoutDraft = [];
        $this->widgetLayout = null;
    }

    /**
     * Forget this user's layout entirely, going back to what the dashboard
     * declares.
     *
     * Not the same as removing every widget, which is a layout in its own right.
     * This is "I never arranged this", and it is the way out of an arrangement
     * somebody has made unusable.
     */
    public function resetWidgetLayout(): void
    {
        $key = $this->widgetLayoutKey();

        if ($key === null) {
            return;
        }

        $this->widgetPreferenceDriver()->forget($key, $this->widgetPreferenceUser(), $this->widgetLayoutView());

        $this->cancelEditingWidgets();
    }

    /**
     * Move a widget to a position — what a drop reports.
     *
     * The server is what places the tile: SortableJS leaves the DOM in the
     * dropped order and this re-renders the grid from the draft over it. That is
     * safe here, where the repeater's controller has to revert instead, because
     * a widget cell carries a `wire:key` while a repeater card does not — the
     * morph pairs cells by key rather than by position, so the two orders cannot
     * disagree.
     */
    public function moveWidget(string $key, int $position): void
    {
        $this->updateDraft(fn (WidgetLayout $layout): WidgetLayout => $layout->move($key, $position));
    }

    /**
     * Put a widget on the dashboard at a position, or move it if it is already
     * there.
     *
     * What the grid's drop calls, from either direction: a tile dragged within
     * the grid and a tile dragged in from the tray arrive at the same method,
     * because the drop cannot tell them apart and should not have to.
     *
     * The size a widget arrives at is the first one it offers
     * ({@see Widget::getDefaultSize()}), so a widget that only looks right at
     * 2×2 is not dropped in as a 1×1 the user then has to fix.
     */
    public function placeWidget(string $key, int $position): void
    {
        $widget = $this->declaredWidget($key);

        if ($widget === null) {
            return;
        }

        [$width, $height] = $widget->getDefaultSize();

        $this->updateDraft(
            fn (WidgetLayout $layout): WidgetLayout => $layout->has($key)
                ? $layout->move($key, $position)
                : $layout->place($key, $position, $width, $height),
        );
    }

    /**
     * Take a widget off the dashboard; it goes back to the tray.
     *
     * Nothing records that it was removed. It is simply not in the layout any
     * more, and "not placed" is the same state as "available" — which is what
     * stops the two ever disagreeing.
     */
    public function removeWidget(string $key): void
    {
        $this->updateDraft(fn (WidgetLayout $layout): WidgetLayout => $layout->remove($key));
    }

    /**
     * Whether this dashboard offers to be rearranged.
     *
     * Public so a view can ask — `widgetLayoutKey()` is protected on purpose,
     * since what a layout is *stored under* is nobody else's business, while
     * whether there is one at all decides whether a button is drawn.
     */
    public function isCustomisableDashboard(): bool
    {
        return $this->widgetLayoutKey() !== null;
    }

    /**
     * Everything `wire-core::widgets.widget-grid` needs, in one call.
     *
     * A single method rather than five keys a caller assembles, because four of
     * them have to agree and forgetting one is silent: a grid rendered without
     * `available` draws an empty tray in edit mode, and one rendered without
     * `trayGroup` puts the tray and the grid in different drag groups so a tile
     * cannot cross between them. Neither is an error anywhere.
     *
     *   return view('wire-core::widgets.widget-grid', $this->widgetGridData(2));
     *
     * @return array<string, mixed>
     */
    public function widgetGridData(?int $columns = null): array
    {
        return [
            'widgets' => $this->getVisibleWidgets(),
            'columns' => $columns ?? $this->getWidgetColumns(),
            'editing' => $this->editingWidgets,
            'customisable' => $this->isCustomisableDashboard(),
            'available' => $this->getAvailableWidgets(),
            // Named per component so two dashboards on one page cannot pull
            // tiles out of each other.
            'trayGroup' => 'wire-widgets-'.$this->getId(),
        ];
    }

    /**
     * The widgets a user can put on this dashboard but has not, grouped for the
     * tray.
     *
     * Declared, visible, and not currently placed. Visible matters as much here
     * as on the grid: a tray that offered a widget a policy hides would be a
     * list of things that vanish when you add them.
     *
     * Ungrouped widgets come first under an empty key, then each named group in
     * the order the declaration mentions it — so a dashboard that names no
     * groups gets one plain list, and one that names them keeps the order it
     * wrote them in rather than an alphabetical one nobody chose.
     *
     * @return array<string, array<int, Widget>>
     */
    public function getAvailableWidgets(): array
    {
        if ($this->widgetLayoutKey() === null) {
            return [];
        }

        $layout = $this->widgetLayout();

        // A dashboard nobody has laid out places everything it declares, so
        // there is nothing to offer. `WidgetLayout::has()` cannot say that on
        // its own — it holds placements, not the declaration they came from.
        if ($layout->isDeclared()) {
            return [];
        }

        $groups = [];

        foreach ($this->stampedWidgets() as $widget) {
            $key = $widget->getKey();

            if ($key === null || $layout->has($key) || ! $widget->isVisible()) {
                continue;
            }

            $groups[$widget->getGroup() ?? ''][] = $widget;
        }

        return $groups;
    }

    /** Resize a widget: `$width` columns of the grid, `$height` rows. */
    public function resizeWidget(string $key, int $width, int $height): void
    {
        $this->updateDraft(fn (WidgetLayout $layout): WidgetLayout => $layout->resize($key, $width, $height));
    }

    /**
     * Apply a change to the draft, and only while there is one.
     *
     * The guard is not ceremony: every one of these is a public Livewire method,
     * so the browser can call it whenever it likes. Outside edit mode there is
     * nothing to change, and answering anyway would let a request rearrange a
     * dashboard nobody opened the editor on.
     *
     * @param  callable(WidgetLayout): WidgetLayout  $change
     */
    private function updateDraft(callable $change): void
    {
        if (! $this->editingWidgets || $this->widgetLayoutKey() === null) {
            return;
        }

        $this->widgetLayoutDraft = $change(WidgetLayout::of($this->widgetLayoutDraft))->toBag()['widgets'];
        $this->widgetLayout = null;
    }

    private function widgetPreferenceDriver(): PreferenceDriver
    {
        return PreferenceManager::resolve(
            authenticated: $this->widgetPreferenceUser() !== null,
            configPrefix: self::WIDGET_PREFERENCE_CONFIG,
        );
    }

    /**
     * The user whose layout is read (null for a guest).
     *
     * Through the facade, the same way `WithTable::preferenceUser()` asks: the
     * `auth()` helper resolves to the guard *factory*, which has no `user()`,
     * and a host that overrides which user it means should have one method to
     * override rather than a helper call buried in a lookup.
     */
    protected function widgetPreferenceUser(): ?Authenticatable
    {
        return Auth::user();
    }

    /**
     * Answer a widget's poll tick with that widget, not with the whole page.
     *
     * A bare `wire:poll` evaluates to `$refresh`, so one polling widget used to
     * re-render every other widget on the dashboard and any table sharing the
     * component — measured at 6.5 ms and 57 kB on a 12-widget grid to deliver
     * one widget's 3.9 kB. The tick now names this method, and the queued
     * partial is the only thing the response carries.
     *
     * Islands cannot do this: an `@island` name is re-evaluated inside its own
     * compiled view file, which never sees the enclosing loop's variable, so
     * `@island($widget->getKey())` throws `Undefined variable $widget` on the
     * first render — the same rule `IslandSemanticsTest` pins for table rows. A
     * partial is chosen by the server and anchored with a plain attribute.
     *
     * Queuing nothing is the safe outcome, not a failure: the coverage rule in
     * `PartialRenderHook` falls back to the full render whenever a call queued
     * no region, so an unknown key, a widget that stopped being visible, or one
     * that stopped polling all end up rendering the page — which is what a
     * changed page shape needs anyway.
     */
    public function refreshWidget(string $key): void
    {
        foreach ($this->getVisibleWidgets() as $widget) {
            if ($widget->getKey() !== $key || ! $widget->isPolling()) {
                continue;
            }

            $this->renderWidgetPartial($widget);

            return;
        }
    }

    /**
     * Give a widget back the state this host is holding for it.
     *
     * Runs after the key is stamped and before visibility is asked, which is
     * the only window where both halves are true: the state is addressed by
     * key, and a `visible(fn () => …)` closure may well read the filter the
     * user chose. A widget that offers no such filter option ignores the
     * selection ({@see Widget::applyFilter()}), so a stale key left over from
     * an earlier declaration cannot reach a dataset closure.
     */
    private function applyWidgetState(Widget $widget): void
    {
        $key = $widget->getKey();

        $widget->applyFilter($this->widgetFilters[$key] ?? null);

        if (in_array($key, $this->loadedWidgets, true)) {
            $widget->lazy(false);
        }
    }

    /**
     * Record a widget's filter selection and answer with that widget alone.
     *
     * The chart's filter used to be resolved in the browser and therefore
     * resolved nothing — `updateChart()` assigned the same two arrays back onto
     * the chart it was built with, so the dataset closure only ever ran with its
     * default. The selection has to reach the server because that is where the
     * closure is, and once it is here the response may as well be the one widget
     * that changed rather than the whole grid.
     *
     * An unknown key queues nothing, which is the safe outcome rather than a
     * failure: `PartialRenderHook` falls back to the full render whenever a call
     * queued no region.
     */
    public function filterWidget(string $key, string $value): void
    {
        $this->widgetFilters[$key] = $value;

        // No cache to invalidate, deliberately. `getVisibleWidgets()` re-applies
        // the host's state to every widget on each call, so a memoized list is
        // still correct after this write — and clearing it would re-run the
        // `WidgetConfiguring` hook, which appends. That is the bug the memo in
        // `configuredWidgets()` exists to prevent.
        foreach ($this->getVisibleWidgets() as $widget) {
            if ($widget->getKey() !== $key || ! $widget->hasFilter()) {
                continue;
            }

            $this->renderWidgetPartial($widget);

            return;
        }
    }

    /**
     * Perform a deferred widget's first render.
     *
     * Called once per lazy widget from `wire:init`, and idempotent: a widget
     * already in `loadedWidgets` still re-renders, because that is also the
     * cheapest correct answer to a double-fired init and costs one widget.
     */
    public function loadWidget(string $key): void
    {
        foreach ($this->getVisibleWidgets() as $widget) {
            if ($widget->getKey() !== $key) {
                continue;
            }

            if (! in_array($key, $this->loadedWidgets, true)) {
                $this->loadedWidgets[] = $key;
            }

            $widget->lazy(false);

            $this->renderWidgetPartial($widget);

            return;
        }
    }

    /**
     * Run a header action a widget is carrying, then re-render that widget.
     *
     * The re-render is the point of doing it here rather than letting the page
     * render: an action on a widget almost always changes what the widget shows
     * — that is why it is on the widget — and answering with the one region
     * keeps the rest of the grid still, exactly as a poll tick and a filter
     * change do.
     *
     * **How this crosses a boundary it may not import over.** `Widgets` and
     * `Actions` are sibling L2 modules (ADR 0025), so nothing here knows what an
     * `Action` is: the widget resolves the action by name through
     * {@see Widget::getFieldAction()}, which answers with the Foundation
     * contract, and {@see RunsComponentActions} — resolved from the container,
     * implemented on the Actions side — is what runs it. That is the route the
     * layer rule names for exactly this case.
     *
     * An unknown key or an unknown action name queues nothing and the request
     * falls back to a full render, which is the same safe outcome the other
     * three triggers have.
     */
    public function callWidgetAction(string $key, string $name): void
    {
        foreach ($this->getVisibleWidgets() as $widget) {
            if ($widget->getKey() !== $key) {
                continue;
            }

            $action = $widget->getFieldAction($name);

            if ($action === null || $action->isHidden()) {
                return;
            }

            app(RunsComponentActions::class)->runComponentAction($action, [
                'widget' => $widget,
                'component' => $widget,
                'livewire' => $this,
            ]);

            $redeclared = $this->redeclaredWidget($key);

            // Gone after its own action — hidden by what the callback changed, or
            // removed outright. Queue nothing and let the full render happen: a
            // partial can replace an element but not delete one, so re-rendering
            // the stale object would leave a widget on screen that the
            // declaration no longer has. Same answer every other trigger here
            // gives a key that names nothing.
            if ($redeclared === null) {
                return;
            }

            $this->renderWidgetPartial($redeclared);

            return;
        }
    }

    /**
     * The widget with this key, rebuilt from the declaration.
     *
     * Because the object we just ran the action on is *older than the action*.
     * A widget resolves its data when `getWidgets()` builds it — a stats card's
     * figures, a progress board's rows — so re-rendering that same object after
     * an action that changed what those figures are shows the state from before
     * the click. Found by a browser driver, where a "Recount" button incremented
     * a counter and the widget kept saying the old number, with a correct
     * response and an empty console.
     *
     * A widget given a *closure* would have re-resolved on its own. Most are not,
     * and "your action works if you happened to pass a closure" is not a rule
     * anyone should have to know.
     *
     * Null when the action removed its own widget from the declaration — hiding
     * it is enough, since this reads the *visible* ones. The caller answers that
     * with a full render.
     *
     * Clearing the memo is safe here, and only here. It exists so that one
     * request's render and its poll tick do not both fire `WidgetConfiguring`;
     * this rebuilds from `getWidgets()` first, so the hook appends to a fresh
     * list and the result has each widget once — pinned by
     * `WidgetConfiguringHookTest`. What it must not become is a reset on every
     * call, which is why it is private and has exactly one caller.
     */
    private function redeclaredWidget(string $key): ?Widget
    {
        $this->configuredWidgets = null;

        foreach ($this->getVisibleWidgets() as $widget) {
            if ($widget->getKey() === $key) {
                return $widget;
            }
        }

        return null;
    }

    /**
     * Queue one widget's cell as the region this response carries.
     *
     * One owner for the four triggers — a poll tick, a filter change, a deferred
     * load, a header action — because they must all queue the *same* markup
     * under the same name. If they did not, the morph would drop whatever two of them
     * disagreed on.
     */
    private function renderWidgetPartial(Widget $widget): void
    {
        $this->renderPartial(
            'widget-'.$widget->getKey(),
            fn () => view('wire-core::widgets.widget-cell', ['widget' => $widget])->render(),
        );
    }
}
