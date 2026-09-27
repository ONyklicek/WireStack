<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Enums;

/**
 * Where a cluster draws the way across between its members.
 *
 * `Start` and `End` are a column beside the content from `lg` up and a row of
 * tabs below it — a column on a phone is a column nobody scrolls to. `Top` is
 * tabs at every width, the shape a record's own pages already use.
 */
enum SubNavigationPosition: string
{
    case Start = 'start';
    case End = 'end';
    case Top = 'top';
}
