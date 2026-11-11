{{-- Room for an editable cell's validation message, made by the ROW.

     The message of a table cell is laid under its input, out of the flow, and
     the row grows by the message's height in EVERY cell at once: a spacer
     behind each cell's content. A cell is aligned to the middle of its row, so
     growing one cell alone shifts every other cell's content down by half of
     what was added — the neighbouring inputs jump — and where the row was
     already taller than the cell, the input above the message moves up, under
     the caret. With the same amount added to all of them, every cell keeps its
     offset and only the row's bottom edge moves.

     The row learns about the message through `:has()`, from `data-error-lines`
     on the cell (`measureError()` in wireEditableCell): a selector, because a
     row partial replaces the `<tr>` and would take a style written on it away,
     and a line count, because a selector cannot carry a height. A line is the
     message's `text-xs` line (1rem) and the quarter is its `mt-1`. With two
     messages in one row the longer one wins — the later rule does.

     Only a browser without `:has()` falls short, and gracefully: no room is
     made and the message lies over the top of the row below. --}}
@once
    <style>
        td [data-cell-error] {
            position: absolute;
            inset-inline: 0;
            top: 100%;
        }

        tr:has([data-error-lines]) > td::after {
            content: '';
            display: block;
            height: 1.25rem;
        }

        tr:has([data-error-lines="2"]) > td::after { height: 2.25rem; }
        tr:has([data-error-lines="3"]) > td::after { height: 3.25rem; }
        tr:has([data-error-lines="4"]) > td::after { height: 4.25rem; }
        tr:has([data-error-lines="5"]) > td::after { height: 5.25rem; }
        tr:has([data-error-lines="6"]) > td::after { height: 6.25rem; }
    </style>
@endonce
