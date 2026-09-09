<?php

declare(strict_types=1);

namespace NyonCode\WireCore\GlobalSearch;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
use Livewire\Component;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Foundation\Contracts\ActionContract;
use NyonCode\WireCore\Foundation\Contracts\ClassifiesComponentActions;
use NyonCode\WireCore\Foundation\Contracts\ProvidesCommands;
use NyonCode\WireCore\Foundation\Contracts\RunsComponentActions;
use NyonCode\WireCore\Foundation\Registration\Catalog;
use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;
use NyonCode\WireCore\Foundation\Routing\Zone;

/**
 * The command palette: one search box over every registered resource, the
 * application's own menu, and whatever commands are on offer.
 *
 * A Livewire component for the reason the notification bell is one — every
 * keystroke is a round trip — and, like the bell, it composes no host trait:
 * there is no form to bind and no table to drive. Named in prose rather than
 * with a `{@see}`, because the tag needs an import and `Notifications` is
 * another L2 module: the layer test rejects the edge, and it is right to.
 *
 * It holds no query. {@see GlobalSearch} owns the record search, {@see
 * PaletteNavigation} the menu and {@see PaletteCommands} the actions, so each
 * rule that decides what a user may see has one owner rather than one per
 * surface.
 *
 * ## Why it runs no action itself
 *
 * `Actions` is a sibling L2 module and this one may not import it — and that
 * restriction turned out to be the right shape anyway. The palette resolves a
 * row to an action *name* and then does one of three things with it, none of
 * which is a second execution pipeline:
 *
 * - nothing to ask → {@see RunsComponentActions} runs the callback;
 * - something to ask, and the owner has a page → go there with `?action=`, and
 *   let the host that owns a modal open it;
 * - something to ask, and no page → dispatch `wire-palette-action`, for a host
 *   already on screen.
 *
 * A palette cannot host a modal, so it never pretends to. What it can do is hand
 * the action to something that can.
 *
 * Mount it once in the layout:
 *
 *   `@livewire`('wire-global-search')
 *
 * Opening it is the application's business — a button, or a ⌘K binding on the
 * markup below. The component does not bind a global shortcut itself, because a
 * framework that claimed ⌘K on every page would be taking a key the application
 * may already use.
 *
 * @property-read array<string, array<int, GlobalSearchResult>> $results The
 *   cached computed property behind {@see getResultsProperty()}. Declared so
 *   that reading it — which every caller in here does, rather than calling the
 *   method and paying for the whole search again — is a typed read instead of
 *   magic static analysis has to take on trust.
 */
class GlobalSearchPalette extends Component
{
    /** The event a host composing an action runtime listens for. */
    public const ACTION_EVENT = 'wire-palette-action';

    /** The query parameter a resource page reads to open an action on arrival. */
    public const ACTION_PARAMETER = 'action';

    public bool $open = false;

    public string $term = '';

    /**
     * The active row, as a flat index over every group.
     *
     * Flat rather than (group, row) because that is what the arrow keys move
     * through: a user pressing Down at the end of one group expects the first
     * row of the next, not nothing.
     */
    public int $active = 0;

    /**
     * The record the palette has drilled into, as `[ownerKey, recordKey]`.
     *
     * Two scalars rather than the record, because this survives a Livewire round
     * trip and a model does not travel well through one. The record is loaded
     * again when it is needed, which is at most once per keystroke and only while
     * the user is looking at one thing.
     *
     * @var array{0: string, 1: int|string}|null
     */
    public ?array $drilldown = null;

    /**
     * The zone this palette was opened in, so results point back into it.
     *
     * A public property because it has to survive the round trip: the search runs
     * on a Livewire request, where {@see Zone::current()} answers nothing — so it
     * is read once while the page renders and carried from there (ADR 0027 §3),
     * the same way the record key travels on the resource pages.
     *
     * Public also means an application can set it: a palette mounted inside a
     * shell that is not itself a wire route says which zone it belongs to.
     */
    public ?string $zone = null;

    /**
     * Opened by the application, on whatever it decides the shortcut is.
     *
     * A listener rather than a bound key: a framework that claimed ⌘K on every
     * page would be taking a combination the application may already use, so the
     * page dispatches `open-global-search` and this answers it.
     */
    public function mount(?string $zone = null): void
    {
        $this->zone = $zone ?? Zone::current();
    }

    #[On('open-global-search')]
    public function open(): void
    {
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->term = '';
        $this->active = 0;
        $this->drilldown = null;
    }

    /**
     * Reset the cursor whenever the term changes.
     *
     * Without this, typing one more character while sitting on row four keeps
     * the cursor on row four of a completely different result set — and Enter
     * then opens something the user never looked at.
     *
     * Typing also leaves a drill-down, for the same reason: the actions on screen
     * belong to a record the new term may not even match.
     */
    public function updatedTerm(): void
    {
        $this->active = 0;
        $this->drilldown = null;

        $this->forgetResults();
    }

    public function moveDown(): void
    {
        $count = count($this->flatResults());

        if ($count > 0) {
            $this->active = ($this->active + 1) % $count;
        }
    }

    public function moveUp(): void
    {
        $count = count($this->flatResults());

        if ($count > 0) {
            $this->active = ($this->active - 1 + $count) % $count;
        }
    }

    /**
     * Show what can be done with the active record.
     *
     * Only a record row has a second level — a command *is* the second level, and
     * a menu entry is a place rather than a thing. A row whose owner offers no
     * commands does not drill in at all, so the key never opens an empty list.
     */
    public function drillDown(): void
    {
        $row = $this->flatResults()[$this->active] ?? null;

        if ($row === null || $row->kind !== PaletteRowKind::Record || $row->recordKey === null) {
            return;
        }

        $record = $this->recordFor($row->resourceKey, $row->recordKey);

        if ($record === null || $this->commands()->forRecord($row->resourceKey, $record) === []) {
            return;
        }

        $this->drilldown = [$row->resourceKey, $row->recordKey];
        $this->active = 0;

        $this->forgetResults();
    }

    /**
     * Back out of a record's actions to the results that found it.
     *
     * The term is kept, so Escape means "close" and Left means "undo one step" —
     * a user who has drilled in by mistake gets their search back rather than an
     * empty box.
     */
    public function drillUp(): void
    {
        $this->drilldown = null;
        $this->active = 0;

        $this->forgetResults();
    }

    /**
     * Where the active row goes, or null when there is nowhere to go.
     *
     * Returned rather than redirected to, so a caller can decide: the palette
     * navigates by default, and an application that renders its results into a
     * panel instead needs the destination, not a response.
     */
    public function selectedUrl(): ?string
    {
        return ($this->flatResults()[$this->active] ?? null)?->url;
    }

    /**
     * Do whatever the chosen row is.
     *
     * Three outcomes rather than one, and which applies is the row's kind plus
     * one question about the action — never a guess about the page underneath.
     *
     * `$index` is what makes "chosen" mean the row that was actually activated.
     * Without it this read the keyboard cursor and nothing else, and the cursor
     * is only correct for the keyboard: a row reached with Tab keeps the cursor
     * where it was, so activating it opened a *different* record — measured in a
     * browser, three rows apart. A tap did the same thing, because a touch device
     * fires no `mouseenter` for the hover binding that used to keep the two in
     * step. Now the pointer, the Tab key and the arrow keys all name the row they
     * mean, and only Enter on the input leaves it to the cursor.
     */
    public function select(?int $index = null): mixed
    {
        if ($index !== null) {
            $this->active = $index;
        }

        $row = $this->flatResults()[$this->active] ?? null;

        if ($row === null) {
            return null;
        }

        if ($row->kind->isLink()) {
            $url = $row->url;

            if ($url === null) {
                return null;
            }

            $this->close();

            return $this->redirect($url, navigate: true);
        }

        return $this->invoke($row);
    }

    /**
     * Run an action row, hand it on, or go to where it can be answered.
     *
     * The action is resolved from its owner again rather than carried on the
     * row. A round trip has happened since the list was built, so this is also
     * the only place where "may this user still run it" is a fresh answer rather
     * than a remembered one.
     */
    protected function invoke(GlobalSearchResult $row): mixed
    {
        $record = $row->recordKey === null
            ? null
            : $this->recordFor($row->resourceKey, $row->recordKey);

        $action = $this->resolveAction($row, $record);

        if ($action === null) {
            return null;
        }

        $classifier = app(ClassifiesComponentActions::class);

        // Re-checked, not trusted. The row was authorized when the list was
        // built; between then and now the user pressed a key and a request went
        // to the server, and an action that has become forbidden in between must
        // not run because a stale row said it could.
        if (! $classifier->isRunnable($action, $record)) {
            return null;
        }

        if (! $classifier->needsPrompt($action)) {
            app(RunsComponentActions::class)->runComponentAction($action, [
                'record' => $record,
                'livewire' => $this,
                'component' => $this,
                'palette' => $this,
            ]);

            $this->close();

            return null;
        }

        return $this->handOff($row, $action->getName());
    }

    /**
     * Give an action that has to ask to something that can ask.
     *
     * Prefers the owner's page, and the reason is that the server cannot tell
     * whether a host with a modal is on screen: a Livewire component knows its
     * own state, not its neighbours'. Navigating is always a correct answer —
     * the action opens where the record lives — so it is the one taken whenever
     * there is somewhere to navigate to.
     *
     * The dispatch is the fallback for an owner with no page at all, where a host
     * already on screen is the only thing that could answer.
     */
    protected function handOff(GlobalSearchResult $row, string $actionName): mixed
    {
        // A record action navigates; a standalone command does not. The page that
        // shows a record reads `?action=` and can mount it, but an index page owns
        // no action host — sending a user there for a modal that will not open is
        // worse than not moving them at all, so a standalone command looks for a
        // host already on screen instead.
        $url = $row->recordKey === null ? null : $this->pageFor($row);

        if ($url === null) {
            $this->dispatch(
                self::ACTION_EVENT,
                name: $actionName,
                arguments: $row->recordKey === null ? [] : ['record' => $row->recordKey],
            );

            $this->close();

            return null;
        }

        $separator = str_contains($url, '?') ? '&' : '?';
        $target = $url.$separator.self::ACTION_PARAMETER.'='.rawurlencode($actionName);

        $this->close();

        return $this->redirect($target, navigate: true);
    }

    /**
     * The page an action's owner would be answered on.
     *
     * A record action goes to the record; a standalone command goes to the
     * owner's index. Null when nothing routes either, which is what sends the
     * action to a host on screen instead.
     */
    protected function pageFor(GlobalSearchResult $row): ?string
    {
        $urls = app(ResolvesPageUrls::class);

        return $row->recordKey === null
            ? $urls->urlFor($row->resourceKey, 'index', [], $this->zone)
            : $urls->urlFor($row->resourceKey, 'view', ['record' => $row->recordKey], $this->zone);
    }

    /**
     * The action this row names, asked of its owner again.
     *
     * @return ActionContract|null
     */
    protected function resolveAction(GlobalSearchResult $row, ?Model $record)
    {
        $owner = app(Catalog::class)->find($row->resourceKey);

        if ($owner === null || ! is_subclass_of($owner, ProvidesCommands::class)) {
            return null;
        }

        foreach ($owner::commands($record) as $action) {
            if ($action->getName() === $row->actionName) {
                return $action;
            }
        }

        return null;
    }

    /**
     * Load the record a row is about.
     *
     * By key through the resource's own model, because that is the only thing the
     * row carries — and deliberately not `findOrFail`: a record deleted between
     * the search and the keystroke is an empty result, not an exception in a
     * dialog the user is still typing in.
     */
    protected function recordFor(string $resourceKey, int|string $recordKey): ?Model
    {
        $resource = app(Catalog::class)->find($resourceKey);

        if (! is_subclass_of($resource, DescribesResource::class)) {
            return null;
        }

        $model = $resource::modelClass();

        if ($model === null || ! is_subclass_of($model, Model::class)) {
            return null;
        }

        return $model::query()->find($recordKey);
    }

    /**
     * Every group the palette is showing, in the order it draws them.
     *
     * Records first, then navigation, then commands — and that order is a
     * measurement, not a preference. Drawing commands first reads well until a
     * term matches both: "INV" finds three invoices *and* the command "Recount
     * invoices", and with commands on top Enter stopped opening the record the
     * user was plainly looking for. A browser driver caught it; nothing else did.
     *
     * So the rule is that the thing being searched for wins. A command is only
     * pushed down when records also matched, which is exactly when the user was
     * not asking for the command — and when nothing else matches, it is at the
     * top anyway because the groups above it are empty.
     *
     * Deterministic order matters beyond taste: the flat keyboard cursor is an
     * index into exactly this sequence.
     *
     * A drill-down replaces the lot. A user looking at what can be done with one
     * record is not also choosing between three other things.
     *
     * @return array<string, array<int, GlobalSearchResult>>
     */
    public function getResultsProperty(): array
    {
        if ($this->drilldown !== null) {
            [$ownerKey, $recordKey] = $this->drilldown;

            $record = $this->recordFor($ownerKey, $recordKey);

            $rows = $record === null ? [] : $this->commands()->forRecord($ownerKey, $record);

            return $rows === [] ? [] : [PaletteCommands::RECORD_GROUP => $rows];
        }

        $groups = $this->searcher()->search($this->term, zone: $this->zone);

        $navigation = $this->navigation()->search($this->term, $this->zone);

        if ($navigation !== []) {
            $groups[PaletteNavigation::GROUP] = $navigation;
        }

        $commands = $this->commands()->search($this->term);

        if ($commands !== []) {
            $groups[PaletteCommands::GROUP] = $commands;
        }

        return $groups;
    }

    /**
     * The heading each group of results is shown under, keyed by group key.
     *
     * For a resource group the key is an identifier — a config key, a route
     * segment, a `wire:key` — and `pluralLabel()` is the plural human name;
     * putting the first on screen would be a second vocabulary for the second,
     * and wrong the moment a resource makes them differ on purpose (`orders` /
     * `Sales Orders`).
     *
     * The palette's own groups are translated instead. Their keys carry a colon,
     * which no catalogue key may contain, so the two namespaces cannot collide
     * however an application names its resources.
     *
     * One static call per group, not per row, and only for the groups that
     * actually matched. A key with nothing behind it keeps the key, which is
     * what a catalogue emptied between the search and the render leaves.
     *
     * @return array<string, string>
     */
    public function groupLabels(): array
    {
        $catalog = app(Catalog::class);

        $reserved = [
            PaletteCommands::GROUP => __('wire-core::global-search.commands'),
            PaletteNavigation::GROUP => __('wire-core::global-search.navigation'),
            PaletteCommands::RECORD_GROUP => __('wire-core::global-search.record_actions'),
        ];

        $labels = [];

        foreach (array_keys($this->results) as $key) {
            if (isset($reserved[$key])) {
                $labels[$key] = $reserved[$key];

                continue;
            }

            $resource = $catalog->find($key);

            $labels[$key] = is_subclass_of($resource, DescribesResource::class)
                ? $resource::pluralLabel()
                : $key;
        }

        return $labels;
    }

    /**
     * The same rows in one list, in the order they are rendered.
     *
     * The keyboard cursor is an index into this, so it and the markup have to
     * walk the groups the same way — which is why both read this method rather
     * than each flattening the groups themselves.
     *
     * Reads `$this->results`, never `getResultsProperty()`. The property is the
     * cached computed property; the method behind it caches nothing, so every
     * caller that reaches for the method pays for the whole search again — one
     * query per opted-in resource, per caller, per keystroke.
     *
     * @return array<int, GlobalSearchResult>
     */
    public function flatResults(): array
    {
        $groups = $this->results;

        return $groups === [] ? [] : array_merge(...array_values($groups));
    }

    /**
     * Whether the palette is showing one record's actions rather than results.
     *
     * The view needs it for the heading and the way back; asked here so the
     * markup does not learn the shape of `$drilldown`.
     */
    /**
     * Drop the cached results, because what they were computed from has changed.
     *
     * `$results` is a computed property and Livewire caches it for the whole
     * request. Every method that moves in or out of a drill-down runs *before*
     * the render that follows it in the same request — and reads the results on
     * the way, to find the active row — so without this the palette renders the
     * list it had before the change: drilling into a record showed the search
     * results again, and only in the browser, because a test that drills and then
     * asserts in a second request never sees it.
     */
    protected function forgetResults(): void
    {
        unset($this->results);
    }

    public function isDrilledDown(): bool
    {
        return $this->drilldown !== null;
    }

    public function render(): View
    {
        return view('wire-core::global-search.palette');
    }

    /**
     * Resolved rather than constructed, so an application can bind its own.
     *
     * {@see GlobalSearch::searchResource()} and {@see GlobalSearch::matchAny()}
     * are protected because a resource whose match needs a join, a full-text
     * index or a search service has to replace them. Building the searcher with
     * `new` made both unreachable from the only surface that renders them — the
     * override would have been written and then silently never run.
     */
    protected function searcher(): GlobalSearch
    {
        return app(GlobalSearch::class);
    }

    protected function navigation(): PaletteNavigation
    {
        return app(PaletteNavigation::class);
    }

    protected function commands(): PaletteCommands
    {
        return app(PaletteCommands::class);
    }
}
