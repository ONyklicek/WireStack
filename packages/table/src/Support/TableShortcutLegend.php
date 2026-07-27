<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use Illuminate\Support\Str;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireCore\Foundation\ValueObjects\ShortcutHint;
use NyonCode\WireTable\Table;

/**
 * What keyboard gestures this table answers to, assembled from the table's own
 * configuration: grid navigation for every grid, selection gestures when the
 * table is selectable, the Enter/Shift+Enter and context-menu bindings plus
 * every `onKey()` / `keyboardShortcut()` projection when record actions exist.
 *
 * Data only — localized section headings with {@see ShortcutHint} rows, no
 * markup — the `?` help modal renders it, and any other surface may. Rows with
 * the same key set are deduplicated (first wins), and a `Delete` binding also
 * lists `⌫`, mirroring the client matcher's platform alias.
 */
final class TableShortcutLegend
{
    public function __construct(private readonly Table $table) {}

    public static function for(Table $table): self
    {
        return new self($table);
    }

    /**
     * @return array<int, array{heading: string, hints: array<int, ShortcutHint>}>
     */
    public function sections(): array
    {
        if (! $this->table->usesGridSemantics()) {
            return [];
        }

        $sections = [
            [
                'heading' => Trans::get('wire-table::messages.shortcuts_navigation'),
                'hints' => [
                    ShortcutHint::make(['ArrowUp', 'ArrowDown'], Trans::get('wire-table::messages.shortcut_move_active')),
                    ShortcutHint::make(['Home', 'End'], Trans::get('wire-table::messages.shortcut_jump_edges')),
                    ShortcutHint::make(['PageUp', 'PageDown'], Trans::get('wire-table::messages.shortcut_page_step')),
                ],
            ],
        ];

        if ($this->table->isSelectable()) {
            $sections[] = [
                'heading' => Trans::get('wire-table::messages.shortcuts_selection'),
                'hints' => [
                    ShortcutHint::make('Space', Trans::get('wire-table::messages.shortcut_toggle_selection')),
                    ShortcutHint::make(['shift+ArrowUp', 'shift+ArrowDown'], Trans::get('wire-table::messages.shortcut_extend_selection')),
                    ShortcutHint::make(['mod+shift+ArrowUp', 'mod+shift+ArrowDown', 'shift+Home', 'shift+End'], Trans::get('wire-table::messages.shortcut_extend_selection_edges')),
                    ShortcutHint::make('mod+A', Trans::get('wire-table::messages.shortcut_select_page')),
                ],
            ];
        }

        $actionHints = $this->actionHints();

        if ($actionHints !== []) {
            $sections[] = [
                'heading' => Trans::get('wire-table::messages.shortcuts_actions'),
                'hints' => $actionHints,
            ];
        }

        $sections[] = [
            'heading' => Trans::get('wire-table::messages.shortcuts_help'),
            'hints' => [
                ShortcutHint::make('?', Trans::get('wire-table::messages.shortcut_show_help')),
            ],
        ];

        return $sections;
    }

    public function isEmpty(): bool
    {
        return $this->sections() === [];
    }

    /**
     * @return array<int, ShortcutHint>
     */
    private function actionHints(): array
    {
        if (! $this->table->hasRecordActions()) {
            return [];
        }

        $config = $this->table->getRecordActionKeyboardConfig();
        $hints = [];

        if (($primary = $config['primary']) !== null) {
            $hints[] = ShortcutHint::make('Enter', $this->runLabel($primary));
        }

        if (($secondary = $config['secondary']) !== null) {
            $hints[] = ShortcutHint::make('shift+Enter', $this->runLabel($secondary));
        }

        $hints[] = ShortcutHint::make(['ContextMenu', 'shift+F10'], Trans::get('wire-table::messages.shortcut_context_menu'));

        // onKey() / keyboardShortcut() bindings, grouped so one action reads as
        // one row even when it answers to several keys.
        $keysByAction = [];

        foreach ($config['shortcuts'] as $key => $name) {
            $keysByAction[$name][] = $key;

            if (strtolower($key) === 'delete') {
                // The client matcher treats Backspace as a platform alias.
                $keysByAction[$name][] = 'Backspace';
            }
        }

        foreach ($keysByAction as $name => $keys) {
            $uniqueKeys = [];

            foreach ($keys as $key) {
                $uniqueKeys[strtolower($key)] ??= $key;
            }

            $hints[] = ShortcutHint::make(array_values($uniqueKeys), $this->runLabel($name));
        }

        $unique = [];

        foreach ($hints as $hint) {
            $unique[$hint->signature()] ??= $hint;
        }

        return array_values($unique);
    }

    private function runLabel(string $actionName): string
    {
        $action = $this->table->findRegisteredAction($actionName);

        if ($action === null) {
            foreach ($this->table->getRecordActionInstances() as $instance) {
                if ($instance->getName() === $actionName) {
                    $action = $instance;

                    break;
                }
            }
        }

        return Trans::get('wire-table::messages.shortcut_run_action', [
            'action' => $action?->getLabel() ?? Str::headline($actionName),
        ]);
    }
}
