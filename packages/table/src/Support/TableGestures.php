<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use NyonCode\WireTable\Exceptions\TableConfigurationException;

/**
 * Which desktop pointer/keyboard gestures a table offers — the single owner of
 * that decision, for the table and for the client controllers alike.
 *
 * The gesture layer is what turns a table into something closer to a desktop
 * application: arrow-key navigation, Shift-ranges, a drag sweep down the
 * checkbox column, `?` help, the Excel fill handle. That is right for a back
 * office and wrong for a public page, so every capability is switchable, and
 * the whole layer switches at once:
 *
 *   $table->gestures(false);                       // a plain web table
 *   $table->gestures(fn (TableGestures $g) => $g
 *       ->keyboard()                               // arrows, Enter, shortcuts
 *       ->dragSelect(false)                        // no mouse sweep
 *       ->rangeSelection());                       // Shift+click still ranges
 *
 * The project-wide default lives in `config('wire-table.defaults.gestures')`
 * and is read through {@see fromConfig()}, so a site that wants none of this
 * says so once.
 *
 * What this does NOT govern: an explicitly declared record action. A binding
 * such as `RecordAction::make('view')->onClick()` is a deliberate statement
 * about that table and keeps firing with the gesture layer off — the exception
 * being `onKey()`, which needs the keyboard layer to exist and is documented as
 * such. Everything here is the *implicit* layer the table would otherwise turn
 * on for itself.
 *
 * Every capability is a plain permission: switching one on never conjures the
 * thing it governs (a sweep still needs `selectable()`, a fill handle still
 * needs `fillHandle()` and editable columns). `keyboard()` is the one
 * three-state switch, because it also has to express "force it on for a table
 * that would not have qualified" — null means the table decides.
 */
final class TableGestures
{
    /** Setter name keyed by its normalized config key. */
    private const CAPABILITIES = [
        'keyboard' => 'keyboard',
        'rangeselection' => 'rangeSelection',
        'dragselect' => 'dragSelect',
        'contextmenu' => 'contextMenu',
        'shortcuthelp' => 'shortcutHelp',
        'fillhandle' => 'fillHandle',
    ];

    /** null = the table decides (record actions or a selectable table). */
    private ?bool $keyboard = null;

    private bool $rangeSelection = true;

    private bool $dragSelect = true;

    private bool $contextMenu = true;

    private bool $shortcutHelp = true;

    private bool $fillHandle = true;

    /** Every gesture allowed — the default a table starts from. */
    public static function all(): self
    {
        return new self;
    }

    /** No gesture layer at all: an ordinary table that clicks and nothing more. */
    public static function none(): self
    {
        return (new self)
            ->keyboard(false)
            ->rangeSelection(false)
            ->dragSelect(false)
            ->contextMenu(false)
            ->shortcutHelp(false)
            ->fillHandle(false);
    }

    /**
     * Build from a config value: `true`/`null` for everything, `false` for
     * nothing, or a map of capability => bool for a mixed default. An unknown
     * capability is a typo that would otherwise silently do nothing, so it
     * throws.
     *
     * @param  bool|array<string, bool>|null  $config
     */
    public static function fromConfig(bool|array|null $config): self
    {
        if ($config === null || $config === true) {
            return self::all();
        }

        if ($config === false) {
            return self::none();
        }

        $gestures = self::all();

        foreach ($config as $capability => $enabled) {
            $setter = self::CAPABILITIES[strtolower(str_replace(['_', '-'], '', $capability))]
                ?? throw TableConfigurationException::unknownGesture($capability, array_values(self::CAPABILITIES));

            $gestures->{$setter}((bool) $enabled);
        }

        return $gestures;
    }

    /**
     * Grid keyboard navigation: roving tabindex, arrow keys, Home/End,
     * PageUp/PageDown, Enter / Shift+Enter for the primary and secondary record
     * action, Space to toggle the selection, and every record action's own
     * `keyboardShortcut()` / `onKey()` against the active row. Also what makes
     * the table an ARIA `grid`.
     *
     * Pass null to hand the decision back to the table (on for a table with
     * record actions or a selectable one), true to force it on for a table that
     * would not have qualified, false to switch it off.
     */
    public function keyboard(?bool $enabled = true): static
    {
        $this->keyboard = $enabled;

        return $this;
    }

    /**
     * Range selection: Shift+click, mod+click and mod+Shift+click on a row, and
     * Shift+arrow / Shift+Home / Shift+End from the keyboard. With this off a
     * modified click is an ordinary click and the arrows only move the active
     * row; the checkboxes keep working as they always did.
     */
    public function rangeSelection(bool $enabled = true): static
    {
        $this->rangeSelection = $enabled;

        return $this;
    }

    /** The mouse sweep: press in the checkbox column and drag to select a block of rows. */
    public function dragSelect(bool $enabled = true): static
    {
        $this->dragSelect = $enabled;

        return $this;
    }

    /**
     * The right-click row menu — both `rowContextMenu()` and any
     * `onContextMenu()` record action. With this off the browser's own context
     * menu is left alone.
     */
    public function contextMenu(bool $enabled = true): static
    {
        $this->contextMenu = $enabled;

        return $this;
    }

    /** The `?` shortcut help. Needs the keyboard layer, which is what listens for the key. */
    public function shortcutHelp(bool $enabled = true): static
    {
        $this->shortcutHelp = $enabled;

        return $this;
    }

    /** The Excel-style fill handle on editable cells (still opt-in per table via `fillHandle()`). */
    public function fillHandle(bool $enabled = true): static
    {
        $this->fillHandle = $enabled;

        return $this;
    }

    /** Three-state: null leaves the decision to the table. */
    public function allowsKeyboard(): ?bool
    {
        return $this->keyboard;
    }

    public function allowsRangeSelection(): bool
    {
        return $this->rangeSelection;
    }

    public function allowsDragSelect(): bool
    {
        return $this->dragSelect;
    }

    public function allowsContextMenu(): bool
    {
        return $this->contextMenu;
    }

    public function allowsShortcutHelp(): bool
    {
        return $this->shortcutHelp;
    }

    public function allowsFillHandle(): bool
    {
        return $this->fillHandle;
    }

    /**
     * @return array{keyboard: bool|null, rangeSelection: bool, dragSelect: bool, contextMenu: bool, shortcutHelp: bool, fillHandle: bool}
     */
    public function toArray(): array
    {
        return [
            'keyboard' => $this->keyboard,
            'rangeSelection' => $this->rangeSelection,
            'dragSelect' => $this->dragSelect,
            'contextMenu' => $this->contextMenu,
            'shortcutHelp' => $this->shortcutHelp,
            'fillHandle' => $this->fillHandle,
        ];
    }
}
