<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Pages;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesBreadcrumbs;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Routing\RoutePage;
use NyonCode\WirePanels\Exceptions\ResourcePageException;
use NyonCode\WirePanels\Pages\Concerns\HostsPageActions;
use NyonCode\WirePanels\Pages\Concerns\InteractsWithPageWidgets;
use NyonCode\WirePanels\Pages\Contracts\HasHeaderActions;

/**
 * A page of the application's own — a board, a calendar, a report — drawn with
 * the same heading, trail and header actions as the resource pages.
 *
 * The content is the application's: name a view and write it the way any
 * Livewire view is written. The page wraps it.
 *
 *   final class TaskBoard extends Page
 *   {
 *       protected static string $view = 'livewire.task-board';
 *
 *       protected ?string $title = 'Board';
 *
 *       protected function headerActions(): array
 *       {
 *           return [Action::make('newTask')->form([...])->action(...)];
 *       }
 *   }
 *
 * **Registered like a resource.** List it in `config('wire-panels.pages')`, or
 * name its folder in `config('wire-core.discover.pages')`, and it joins the
 * catalogue: `Route::wireResources()` routes it at its `key()`, the menu lists
 * it, `wire:resources` shows it. The statics below say where and how:
 *
 *   protected static ?string $slug = 'board';                // URL and key — default: the class name, kebab-cased
 *   protected static ?string $navigationLabel = 'Board';     // default: the class name, humanised
 *   protected static ?string $navigationIcon = 'outline:view-columns';
 *   protected static ?string $navigationGroup = 'work';
 *   protected static int $navigationSort = 30;
 *   protected static ?string $permission = 'tasks.view';     // the route's can:, and hides the entry
 *   protected static bool $shouldRegisterNavigation = false;  // routed, not in the menu
 *
 * It can still be routed from an owner's `pages()` or mounted by hand instead.
 * A trail is opted into by implementing `ProvidesBreadcrumbs`; a page inside
 * nothing says so by not implementing it.
 *
 * Thin on purpose. Everything here is a capability a resource page composes too
 * ({@see HostsPageActions}); the class only saves an application from composing
 * them itself for the common case.
 */
abstract class Page extends Component implements HasHeaderActions, ProvidesNavigation, ProvidesPages
{
    use HostsPageActions;
    use InteractsWithPageWidgets;

    /** The view the page's content is drawn from. */
    protected static string $view = '';

    /** The heading, or none. */
    protected ?string $title = null;

    /** The page's key and URL segment; null derives it from the class name. */
    protected static ?string $slug = null;

    protected static ?string $navigationLabel = null;

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationGroup = null;

    protected static int $navigationSort = 100;

    /** The ability the route requires — its `can:` middleware — and the menu entry with it. */
    protected static ?string $permission = null;

    /** False keeps a registered page routed but out of the menu. */
    protected static bool $shouldRegisterNavigation = true;

    /** The registered key: the URL segment, the menu entry's key, the catalogue's. */
    public static function key(): string
    {
        return static::$slug ?? Str::kebab((string) Str::of(class_basename(static::class))->beforeLast('Page')->whenEmpty(fn () => 'page'));
    }

    /** What the menu calls the page. */
    public static function label(): string
    {
        return static::$navigationLabel ?? Str::headline((string) Str::of(class_basename(static::class))->beforeLast('Page')->whenEmpty(fn () => 'Page'));
    }

    /**
     * One page, at the page's own key, guarded by its permission.
     *
     * @return array<string, RoutePage>
     */
    public static function pages(): array
    {
        return ['index' => RoutePage::make(static::class)->permission(static::$permission)];
    }

    /**
     * Its menu entry — hidden when the page asks not to be listed, or when this
     * user may not open it: an entry leading to a 403 is worse than none.
     */
    public static function navigation(): NavigationItem
    {
        return NavigationItem::make(static::label())
            ->icon(static::$navigationIcon)
            ->group(static::$navigationGroup)
            ->sort(static::$navigationSort)
            ->visible(static fn (): bool => static::$shouldRegisterNavigation
                && (static::$permission === null || Gate::allows(static::$permission)));
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Anything else the content view needs, beside the component's public
     * properties Livewire already hands it.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [];
    }

    /** A page of its own, by default, is about no record, so neither are its actions. */
    protected function headerActionRecord(): ?Model
    {
        return null;
    }

    public function render(): View
    {
        if (static::$view === '') {
            throw ResourcePageException::noView(static::class);
        }

        return view('wire-panels::pages.page', [
            ...$this->getViewData(),
            'title' => $this->getTitle(),
            'breadcrumbs' => $this instanceof ProvidesBreadcrumbs ? $this->breadcrumbs() : [],
            'headerActions' => $this->renderedHeaderActions(),
            'headerWidgets' => $this->pageWidgetsForView('header'),
            'footerWidgets' => $this->pageWidgetsForView('footer'),
            'contentView' => static::$view,
        ]);
    }
}
