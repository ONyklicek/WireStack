#!/usr/bin/env python3
"""Apply Torchlight `[tl! focus]` annotations to docs code examples.

Focus decisions are made per EN page and keyed by the code block's opening
fence line (1-based, as reported by the scan). Because each CS page mirrors the
EN code line-for-line (only translated comments differ), the same focus is
mirrored to the CS page by *block index* — the Nth code block in the EN file
maps to the Nth code block in the CS file.

A focus target is either:
  * an int  -> a single line offset (1-based from the first line after the
    opening fence), annotated with `[tl! focus]`; or
  * a (start, end) tuple -> an inclusive range, annotated with
    `[tl! focus:start]` / `[tl! focus:end]`.

The token is appended to an existing `//` comment when present, otherwise a new
`// ...` comment is added. Torchlight strips the token and keeps the focus.
"""
from __future__ import annotations

import sys
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]

Target = "int | tuple[int, int]"
# rel path -> { opening_fence_line -> [targets] }
# Offsets are 1-based from the first line after the opening fence. A tuple is an
# inclusive focus range; the intent is to spotlight the actual definition while
# imports / class + method boilerplate dim away.
FOCUS: dict[str, dict[int, list]] = {
    "getting-started.md": {
        181: [(17, 57)],   # Table quick start: the whole ->model()...->paginated() chain
        255: [(21, 32)],   # Form quick start: the ->statePath()...->successMessage() chain
    },
    "table/overview.md": {
        36: [(17, 64)],    # Quick start: full table definition, dim the imports/scaffold
    },
    "forms/overview.md": {
        38: [(13, 20)],            # Single form: the form() chain
        81: [(10, 16), (21, 27)],  # Multi-form: both auto-detected *Form schemas
    },
    "forms/save-lifecycle.md": {
        265: [(27, 38)],   # Complete example: spotlight the mutate/before/after hooks
    },
    "core/infolists.md": {
        50: [(14, 23)],    # Quick start: the Infolist::make()->record()->schema() chain
    },
    "core/actions.md": {
        55: [10, 16, (23, 24), (31, 32)],  # Basic usage: the distinguishing call per action type
    },
    "testing.md": {
        119: [(13, 16)],   # Host component: the form() definition under test
    },
    "core/modals.md": {
        79: [(8, 12), 17, 22, 27],  # The four modal-object variants passed to ->modal()
    },
    "table/imports.md": {
        13: [(11, 25)],    # The ImportAction/TableImport config, dim the table scaffold
    },
    "forms/custom-fields.md": {
        94: [(37, 41), (43, 46)],  # Custom field: the framework-required overrides
    },
}


def code_blocks(lines: list[str]) -> list[tuple[int, int]]:
    """[(open_fence_idx, close_fence_idx)] 0-based for each ``` block."""
    blocks: list[tuple[int, int]] = []
    open_idx: int | None = None
    for i, ln in enumerate(lines):
        if ln.lstrip().startswith("```"):
            if open_idx is None:
                open_idx = i
            else:
                blocks.append((open_idx, i))
                open_idx = None
    return blocks


def add_token(line: str, token: str) -> str | None:
    """Append a Torchlight token to a code line. None if unsafe/blank."""
    body = line.rstrip("\n")
    if "[tl!" in body:
        return None
    if body.strip() == "":
        return None
    if "//" in body:
        return f"{body} {token}\n"
    return f"{body.rstrip()} // {token}\n"


def annotate(lines: list[str], open_idx: int, close_idx: int, targets: list) -> int:
    changed = 0
    for target in targets:
        if isinstance(target, tuple):
            start, end = target
            spots = [(start, "[tl! focus:start]"), (end, "[tl! focus:end]")]
            if start == end:
                spots = [(start, "[tl! focus]")]
        else:
            spots = [(target, "[tl! focus]")]
        for off, token in spots:
            idx = open_idx + off
            if idx >= close_idx:
                print(f"  ! block at fence {open_idx + 1}: no line offset {off}", file=sys.stderr)
                continue
            new = add_token(lines[idx], token)
            if new is not None:
                lines[idx] = new
                changed += 1
    return changed


def apply_en(path: Path, focus: dict[int, list]) -> tuple[int, dict[int, list]]:
    lines = path.read_text().splitlines(keepends=True)
    blocks = code_blocks(lines)
    open_to_index = {o: n for n, (o, _c) in enumerate(blocks)}
    by_block: dict[int, list] = {}
    changed = 0
    for open_line_1based, targets in focus.items():
        open_idx = open_line_1based - 1
        if open_idx not in open_to_index:
            print(f"  ! {path}: no code block opens at line {open_line_1based}", file=sys.stderr)
            continue
        idx = open_to_index[open_idx]
        _o, close_idx = blocks[idx]
        by_block[idx] = targets
        changed += annotate(lines, open_idx, close_idx, targets)
    path.write_text("".join(lines))
    return changed, by_block


def apply_cs(path: Path, by_block: dict[int, list]) -> int:
    lines = path.read_text().splitlines(keepends=True)
    blocks = code_blocks(lines)
    changed = 0
    for block_index, targets in by_block.items():
        if block_index >= len(blocks):
            print(f"  ! {path}: missing block #{block_index}", file=sys.stderr)
            continue
        open_idx, close_idx = blocks[block_index]
        changed += annotate(lines, open_idx, close_idx, targets)
    path.write_text("".join(lines))
    return changed


def main() -> None:
    total_en = total_cs = 0
    for rel, focus in FOCUS.items():
        en = REPO / "docs" / rel
        cs = REPO / "docs" / "cs" / rel
        en_changed, by_block = apply_en(en, focus)
        cs_changed = apply_cs(cs, by_block) if cs.exists() else 0
        total_en += en_changed
        total_cs += cs_changed
        print(f"{rel}: EN +{en_changed}, CS +{cs_changed}")
    print(f"\nTotal focus tokens: EN {total_en}, CS {total_cs}")


if __name__ == "__main__":
    main()
