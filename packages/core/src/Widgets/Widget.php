<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use NyonCode\WireCore\Foundation\Concerns\CanBeDisabled;
use NyonCode\WireCore\Foundation\Concerns\HasActions;
use NyonCode\WireCore\Foundation\Concerns\HasColumnSpan;
use NyonCode\WireCore\Foundation\Concerns\HasExtraAttributes;
use NyonCode\WireCore\Foundation\Concerns\HasRowSpan;
use NyonCode\WireCore\Foundation\Concerns\HasVisibility;
use NyonCode\WireCore\Foundation\Concerns\InteractsWithStateConditions;
use NyonCode\WireCore\Foundation\Contracts\ActionContract;
use NyonCode\WireCore\Foundation\Contracts\HasFieldActions;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;
use NyonCode\WireCore\Widgets\Concerns\CanBeLazy;
use NyonCode\WireCore\Widgets\Concerns\HasEmptyState;
use NyonCode\WireCore\Widgets\Concerns\HasPolling;
use NyonCode\WireCore\Widgets\Concerns\HasWidgetFilter;

/**
 * Base widget class for dashboard components.
 *
 * @phpstan-consistent-constructor
 */
abstract class Widget implements HasFieldActions, Htmlable
{
    use CanBeDisabled;
    use CanBeLazy;
    use EvaluatesClosures;
    use HasActions;
    use HasColumnSpan;
    use HasEmptyState;
    use HasExtraAttributes;
    use HasPolling;
    use HasRowSpan;
    use HasVisibility;
    use HasWidgetFilter;
    use InteractsWithStateConditions;

    protected ?string $heading = null;

    protected ?string $description = null;

    protected ?string $key = null;

    protected ?string $group = null;

    /** @var array<int, array{0: int, 1: int}> */
    protected array $sizes = [];

    public static function make(): static
    {
        return new static;
    }

    /**
     * A stable identity for this widget within its dashboard.
     *
     * Widgets are the one component here built by `make()` with no name, which
     * is fine until something has to address *one* of them across a round trip —
     * which polling does. {@see Concerns\WithWidgets} stamps a key derived from
     * the widget's position in `getWidgets()` when none was set, so the default
     * needs no ceremony; set one explicitly where the declaration's order is
     * likely to change, since the derived key moves with it.
     *
     * Deliberately position-in-`getWidgets()` rather than position among the
     * *visible* ones: a widget hidden by a condition would otherwise renumber
     * every widget after it, and a poll would then answer with the wrong one.
     */
    public function key(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    /** Set the widget heading. */
    public function heading(?string $heading): static
    {
        $this->heading = $heading;

        return $this;
    }

    public function getHeading(): ?string
    {
        return $this->heading;
    }

    /** Set the descriptive subheading shown under the heading. */
    public function description(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Whether this widget has to be rendered inside its own addressable region.
     *
     * Three things need one, for one reason: each of them replaces this widget
     * and nothing else on the page — a poll tick, a deferred first load, and a
     * filter change. The answer is asked of the widget rather than assembled in
     * the grid template so that a fourth trigger has one place to be added, and
     * so the template keeps deciding layout rather than mechanism.
     *
     * A widget with no key cannot be addressed at all, and every path that
     * answers one of these declines without a key. It is not asked here because
     * {@see Concerns\WithWidgets::getVisibleWidgets()} stamps one on every
     * widget before this is ever read.
     */
    public function usesPartialAnchor(): bool
    {
        return $this->isPolling() || $this->isLazy() || $this->hasFilter() || $this->hasRenderableActions();
    }

    /**
     * The header actions that actually have somewhere to go.
     *
     * A guard rather than tidiness, and resolved here rather than in the view
     * because the rendering contract puts the decision in PHP. The shared action
     * button falls back to the *infolist* dispatch when it is handed no click
     * expression — a different method, on a host that either does not have it or
     * has it for something else entirely. A button that calls the wrong thing is
     * worse than one that is not there.
     *
     * So the rule is one sentence: no expression, not drawn. It could have been
     * narrower — a `url()` action is answered by the browser and needs no host —
     * but asking an action whether it carries a url means asking through
     * {@see ActionContract}, whose two methods are two on purpose and whose
     * docblock says to think hard before adding a third. A url action is drawn
     * whenever the widget has a key, which is always: {@see Concerns\WithWidgets}
     * stamps one before anything renders, and a key is all
     * {@see getActionExpression()} needs. Nothing is lost outside of echoing a
     * widget by hand.
     *
     * @return array<int, ActionContract>
     */
    public function getRenderableActions(): array
    {
        return array_values(array_filter(
            $this->getActions(),
            fn (ActionContract $action): bool => $this->getActionExpression($action) !== null,
        ));
    }

    public function hasRenderableActions(): bool
    {
        return $this->getRenderableActions() !== [];
    }

    /**
     * Which tray group this widget is offered under.
     *
     * A heading in the list of things a user can put on their dashboard, and
     * nothing else — it does not group anything on the dashboard itself, where
     * the user's own order decides. Null puts the widget in the ungrouped run,
     * which is where a dashboard with a handful of widgets should leave them:
     * one group is a heading over everything, which is a heading that says
     * nothing.
     */
    public function group(?string $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    /**
     * The sizes this widget may be given, as `[width, height]` pairs.
     *
     * Empty means the whole grid — 1–4 columns by 1–6 rows, stepped freely.
     * Declaring a list narrows it to sizes the widget actually looks right at:
     * a sparkline row is not a 1×1 tile and a single figure is not a 4×6 one,
     * and letting a user find that out by dragging is worse than not offering
     * it.
     *
     * The pairs are the *offer*, not a guarantee about what is stored: a layout
     * that arrives with a size outside them is still clamped to the grid rather
     * than rejected, because a stored layout can outlive the declaration that
     * shaped it.
     *
     * Typed as a loose array and narrowed here, the way `BarChartWidget::items()`
     * is: the pairs are a declaration somebody writes by hand, and half a pair
     * would otherwise reach the grid as a size with no height.
     *
     * @param  array<int, mixed>  $sizes
     */
    public function sizes(array $sizes): static
    {
        $pairs = [];

        foreach ($sizes as $size) {
            if (is_array($size) && isset($size[0], $size[1]) && is_int($size[0]) && is_int($size[1])) {
                $pairs[] = [$size[0], $size[1]];
            }
        }

        $this->sizes = $pairs;

        return $this;
    }

    /**
     * @return array<int, array{0: int, 1: int}>
     */
    public function getSizes(): array
    {
        return $this->sizes;
    }

    /**
     * The size a widget is added at: the first it offers, or one column by one row.
     *
     * @return array{0: int, 1: int}
     */
    public function getDefaultSize(): array
    {
        return $this->sizes[0] ?? [1, 1];
    }

    /** The expression the tile's remove control calls on the host. */
    public function getRemoveExpression(): ?string
    {
        if ($this->key === null) {
            return null;
        }

        return "removeWidget('".addslashes($this->key)."')";
    }

    /**
     * The expression the tray's add button calls on the host.
     *
     * Appended rather than inserted anywhere in particular — a click has no
     * position to report, and the end of the dashboard is where a user looking
     * at the tray will look for what they just added. A drag says where.
     *
     * Built in PHP and escaped, for the reason {@see getResizeExpression()}
     * gives.
     */
    public function getPlaceExpression(int $position = 999): ?string
    {
        if ($this->key === null) {
            return null;
        }

        return "placeWidget('".addslashes($this->key)."', {$position})";
    }

    /**
     * The expression a resize control calls on the host.
     *
     * Built here rather than spelled in the template, for the reason
     * {@see getActionExpression()} and
     * {@see HasWidgetFilter::getFilterExpression()} are: the key is
     * being spliced into a Livewire expression, and a key carrying a quote would
     * otherwise change what that expression says rather than being passed to it.
     * The template had it inline until a test showed the quotes coming out
     * unescaped, which is exactly what that would look like on the way to
     * breaking.
     *
     * Null without a key, the same rule the other two follow.
     */
    public function getResizeExpression(int $width, int $height): ?string
    {
        if ($this->key === null) {
            return null;
        }

        return "resizeWidget('".addslashes($this->key)."', {$width}, {$height})";
    }

    /**
     * Interactive buttons drawn in the widget's header.
     *
     * Alias for {@see HasActions::actions()} with header-slot semantics, exactly
     * as {@see Section::headerActions()}
     * names the same thing — one vocabulary for "a display component carries
     * actions", and `HasActions` is its canonical owner.
     *
     * **What a widget action can be.** Its callback, run on the server, with the
     * widget re-rendered afterwards. Not the action *lifecycle*: no modal, no
     * confirmation, no form, for the same reason an infolist entry's actions
     * have none — a widget is rebuilt from the declaration on every request, so
     * the button carries a name and there is nothing mounted to resume into. An
     * action that has to ask before it acts belongs on the page, where a host
     * composing `WithActions` owns a modal host.
     *
     * @param  array<int, ActionContract>  $actions
     */
    public function headerActions(array $actions): static
    {
        return $this->actions($actions);
    }

    /**
     * The expression a header action's button calls on the host.
     *
     * Null without a key, the same rule {@see HasPolling::getPollingDirective()}
     * and {@see HasWidgetFilter::getFilterExpression()} follow: no key,
     * no region to answer with, so nothing to call.
     *
     * The name is escaped rather than trusted. It is a declaration-time string
     * today, but it is being spliced into a Livewire expression, and an action
     * named by a `match` over user data would otherwise be one quote away from
     * changing what that expression says.
     */
    public function getActionExpression(ActionContract $action): ?string
    {
        if ($this->key === null) {
            return null;
        }

        return "callWidgetAction('".addslashes($this->key)."', '".addslashes($action->getName())."')";
    }

    abstract protected function viewName(): string;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [];
    }

    public function render(): View
    {
        return view($this->viewName(), array_merge(
            ['widget' => $this],
            $this->getViewData(),
        ));
    }

    public function toHtml(): string
    {
        return $this->render()->render();
    }

    public function __toString(): string
    {
        return $this->toHtml();
    }
}
