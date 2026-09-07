<?php

declare(strict_types=1);

namespace NyonCode\WireModuleSettings\Pages;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use NyonCode\WireCore\Core\Plugin\Contracts\IdentifiesHookTarget;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesBreadcrumbs;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;
use NyonCode\WireCore\Foundation\Routing\Zone;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;
use NyonCode\WireModuleSettings\Contracts\SettingsGroup;
use NyonCode\WireModuleSettings\Resources\SettingsResource;
use NyonCode\WireModuleSettings\Support\Settings;
use NyonCode\WireModuleSettings\Support\SettingsGroups;

/**
 * The settings screen: one group at a time.
 *
 * The module owns storage and this page; **what** is configurable is the
 * application's, declared as {@see SettingsGroup} classes in
 * `wire-module-settings.groups`. A page that shipped its own list of settings
 * would be a package guessing what an application needs.
 *
 * One group per visit rather than every group in tabs, and that is a decision
 * about saving rather than about layout: a form that writes six groups at once
 * has to explain what happened when the third one fails validation, and "your
 * branding saved but your mail did not" is not a message a settings page should
 * ever have to produce.
 *
 * ## Which group is open
 *
 * The route is `settings/{record}` — the URL shape every resource page in this
 * framework gets — so the group arrives under the name **`record`**, and that
 * is why `mount()` takes both. It used to take only `$group`, which Livewire
 * matches against neither a public property nor a mount parameter, so it dropped
 * the value into the component's HTML attributes and every bookmarked group URL
 * quietly rendered the first group instead. The page looked like it worked, and
 * the whole reason the switcher is links rather than tabs stopped being true.
 *
 * A group that is not declared is a 404 and one this user may not see is a 403,
 * because the alternative for both is a form with no fields in it — which reads
 * as "there is nothing to configure here" rather than as "this is not yours" or
 * "this is gone".
 */
class SettingsPage extends Component implements IdentifiesHookTarget, ProvidesBreadcrumbs
{
    use WithForms;

    /** Which group is being edited. Public so it survives the round trip. */
    public string $group = '';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /**
     * The zone this page was opened in, read once and carried.
     *
     * The same rule the resource pages follow (ADR 0027): during a Livewire
     * update `Route::currentRouteName()` is `livewire.update`, so a switcher
     * that re-derived the zone would link out of it correctly on the first paint
     * and wrongly on every one after.
     */
    public ?string $breadcrumbZone = null;

    /**
     * The declared groups, resolved once per request.
     *
     * @var array<string, class-string<SettingsGroup>>|null
     */
    protected ?array $resolvedGroups = null;

    /**
     * @param  mixed  $record  The group name, as the route names it.
     * @param  string|null  $group  The same thing, for a page mounted by hand.
     */
    public function mount(mixed $record = null, ?string $group = null): void
    {
        $this->breadcrumbZone = Zone::current();

        $named = $this->named($record) ?? $this->named($group);

        if ($named !== null) {
            $class = SettingsGroups::find($named);

            if ($class === null) {
                // A group the application removed, or a typo in a bookmark. Not
                // an empty form: empty reads as "nothing to configure".
                abort(404);
            }

            if (! SettingsGroups::authorized($class)) {
                abort(403);
            }
        }

        // Declared, but none of it this user's. Not the empty state: that one
        // says "declare a SettingsGroup class and list it in config", which is
        // an instruction for the developer and a lie to everybody else.
        if ($this->groups() === [] && SettingsGroups::all() !== []) {
            abort(403);
        }

        $this->group = $named ?? (string) (array_key_first($this->groups()) ?? '');

        // Defaults first, then what is stored over them: a group that declares a
        // default shows it in the field rather than showing an empty input the
        // first time somebody opens the screen.
        $this->form->fill(Settings::all($this->group));
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            // `$class::schema()` rather than `$class?->schema()`: the group is a
            // class *name*, and the null-safe operator on a string is a call on a
            // string — which fails at render with a message about the wrong type.
            ->schema($this->groupSchema())
            ->successMessage(__('wire-module-settings::messages.saved'))
            // No model: settings are rows in a table of their own, so the write
            // is a command rather than a save — which is exactly what `using()`
            // is for, and why a form needs no model to be useful.
            ->using(function (array $data): array {
                Settings::fill($data, $this->group);

                return $data;
            });
    }

    public function save(): mixed
    {
        // Re-checked at the write rather than trusted from mount. The group is a
        // public property, so it rides in the snapshot and comes back from the
        // browser; a user who may open one group must not be able to save
        // another by editing the value that travels.
        $class = $this->groupClass();

        if ($class === null || ! SettingsGroups::authorized($class)) {
            abort(403);
        }

        return $this->form->save();
    }

    /**
     * The groups this user may open, keyed by their storage group.
     *
     * @return array<string, class-string<SettingsGroup>>
     */
    public function groups(): array
    {
        return $this->resolvedGroups ??= SettingsGroups::visible();
    }

    /** The heading: the open group's own label, or the screen's name. */
    public function getTitle(): string
    {
        $class = $this->groupClass();

        return $class !== null ? $class::label() : __('wire-module-settings::messages.settings');
    }

    /**
     * Whether the page owes this group's fields a card of its own.
     *
     * A settings group's whole contract is a heading and a schema, and the
     * simplest one is a flat list of fields. Rendered bare that list sits
     * directly on the page background, which is the one screen in a panel where
     * inputs float with nothing behind them — every resource form gets its
     * surface from the `Section`s the resource declared, and a settings group is
     * not obliged to declare any. Making one mandatory to avoid looking broken
     * would be a tax on the shortest thing the module asks anybody to write.
     *
     * So the page provides it, and only where it is missing: a group that brings
     * its own layout — a `Section`, a `Grid`, `Tabs` — is rendered as it stands,
     * because a card around a card is a border inside a border and the group
     * already said where its own edges are. Only the top level is looked at,
     * which is the level a wrapper would be added at.
     */
    public function needsSurface(): bool
    {
        foreach ($this->groupSchema() as $component) {
            if ($component instanceof LayoutComponent) {
                return false;
            }
        }

        return true;
    }

    /** A line under the heading, when the group declares one. */
    public function description(): ?string
    {
        $class = $this->groupClass();

        return $class !== null ? SettingsGroups::description($class) : null;
    }

    /**
     * Where this page sits: the screen, then the group inside it.
     *
     * A trail of one renders nothing, so a panel that declares a single group
     * pays for none of this.
     *
     * @return array<int, NavigationItem>
     */
    public function breadcrumbs(): array
    {
        $crumbs = [
            NavigationItem::make(__('wire-module-settings::messages.settings'))->url(
                app(ResolvesPageUrls::class)->urlFor(SettingsResource::key(), 'index', [], $this->breadcrumbZone),
            ),
        ];

        $class = $this->groupClass();

        if ($class !== null) {
            $crumbs[] = NavigationItem::make($class::label());
        }

        return $crumbs;
    }

    public function hookKey(): ?string
    {
        return 'settings';
    }

    /** @return class-string<SettingsGroup>|null */
    protected function groupClass(): ?string
    {
        return $this->groups()[$this->group] ?? null;
    }

    /**
     * The group a mount argument names, or null when it names none.
     *
     * The route parameter arrives as whatever was in the URL, so a non-string —
     * an array from a crafted query — is "no group" rather than a type error
     * three lines later.
     */
    protected function named(mixed $record): ?string
    {
        return is_string($record) && $record !== '' ? $record : null;
    }

    /**
     * The current group's components, or none when no group is declared.
     *
     * @return array<int, mixed>
     */
    protected function groupSchema(): array
    {
        $class = $this->groupClass();

        return $class === null ? [] : $class::schema();
    }

    public function render(): View
    {
        return view('wire-module-settings::page', [
            'groups' => $this->groups(),
            'current' => $this->group,
            'title' => $this->getTitle(),
            'description' => $this->description(),
            'breadcrumbs' => $this->breadcrumbs(),
            'surface' => $this->needsSurface(),
        ]);
    }
}
