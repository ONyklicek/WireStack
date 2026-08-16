{{--
    The row's action cell — compiled once for the table, filled per row.

    Variables: $cellPadding, $borderClass (both table-level, baked at compile
    time) and $actions (a slot: this row's buttons, already rendered).

    It lived inline in the row loop until the loop's conditionals moved into PHP.
    The buttons themselves stay per record — an action can be non-executable for
    one row and not the next — which is why they carry `wire:key="act-…"` now:
    that is what pairs them through a morph, in place of the `@foreach` markers
    this partial no longer emits.

    Mind the whitespace: the tags touch on purpose. A run of whitespace between
    two tags is a DOM text node that the morph walks on every commit, and this
    cell is emitted once per row. Whitespace between attributes is free.
--}}
<td class="{{ $cellPadding }} {{ $borderClass }}"><div class="flex flex-wrap items-center gap-1 {{ $justifyClass }}">{!! $actions !!}</div></td>
