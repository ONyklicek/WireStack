<?php

declare(strict_types=1);

namespace NyonCode\WireCore\GlobalSearch;

/**
 * What a palette row *is*, which is the same question as what pressing Enter on
 * it does.
 *
 * An enum rather than a nullable `actionName` read as a flag, because the four
 * kinds do not fall into two: a record and a navigation entry are both links and
 * still want different headings and different keys, and a command and a record
 * action are both invocations that differ in what context they carry. Deriving
 * the kind from which fields happen to be filled would put that reasoning in
 * every caller.
 *
 * Lives beside the palette rather than in Foundation: nothing outside this
 * module has an opinion about how a search result is entered.
 */
enum PaletteRowKind: string
{
    /** A record a resource matched. Enter follows its page. */
    case Record = 'record';

    /** An entry from the application's own menu. Enter follows its URL. */
    case Navigation = 'navigation';

    /** An action that stands on its own. Enter runs it, or hands it on. */
    case Command = 'command';

    /** An action about the record the palette drilled into. */
    case RecordAction = 'record-action';

    /**
     * Whether Enter on this row is a navigation rather than an invocation.
     *
     * The palette branches on this once instead of listing the link kinds at
     * each place that has to know — and a kind added later is a compile-time
     * question here rather than a silently-missing arm somewhere else.
     */
    public function isLink(): bool
    {
        return match ($this) {
            self::Record, self::Navigation => true,
            self::Command, self::RecordAction => false,
        };
    }
}
