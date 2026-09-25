<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Pages;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesBreadcrumbs;
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
 * Routed like any other page — `RoutePage::make(TaskBoard::class)` in an owner's
 * `pages()` — or mounted by hand. A trail is opted into by implementing
 * `ProvidesBreadcrumbs`; a page inside nothing says so by not implementing it.
 *
 * Thin on purpose. Everything here is a capability a resource page composes too
 * ({@see HostsPageActions}); the class only saves an application from composing
 * them itself for the common case.
 */
abstract class Page extends Component implements HasHeaderActions
{
    use HostsPageActions;
    use InteractsWithPageWidgets;

    /** The view the page's content is drawn from. */
    protected static string $view = '';

    /** The heading, or none. */
    protected ?string $title = null;

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
