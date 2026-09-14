<?php

declare(strict_types=1);

namespace NyonCode\Wire\Install;

/**
 * Which question a part belongs to.
 *
 * The installer asks twice rather than once, because the two lists are not the
 * same kind of choice. The stack is what this framework *is* — forms, tables,
 * the shell — and a person installing it has an opinion about all of it. The
 * modules are ready-made areas that render *inside* the shell, so there is no
 * sense in offering "a users area" to somebody who has not taken a panel to put
 * it in: the second question is asked only when the first one is answered with
 * the shell.
 */
enum ComponentGroup: string
{
    /**
     * The framework itself. Offered first, and to everyone.
     */
    case Stack = 'stack';

    /**
     * A ready-made area, which needs somewhere to be. Offered only once the
     * shell is part of the answer.
     */
    case Module = 'module';

    /**
     * Neither: something that sits beside an installation rather than in it.
     * Listed, never offered — `wire-boost` asks which AI agents to configure,
     * which is not a question this command should answer for anybody.
     */
    case Tooling = 'tooling';
}
