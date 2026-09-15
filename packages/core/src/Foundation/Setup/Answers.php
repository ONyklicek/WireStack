<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Setup;

use Closure;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;

/**
 * A question a setup step asks again while the answer is not one it can use.
 *
 * Every answer a step takes ends up somewhere that has a shape: an e-mail
 * address the user form will hold to its rule, a prefix written into
 * `routes/web.php`, a relation written into a config file. An answer that does
 * not fit was accepted and written — `admin` as an address, a quote in a prefix
 * that left the routes file a parse error and the whole application down with
 * it. So a step says what is wrong and asks again, three times at most, and a
 * person who cannot give a usable answer three times is better served by the
 * step saying so than by a loop.
 *
 * Unattended, once: a console nobody is at gives the same default every time.
 */
final readonly class Answers
{
    public function __construct(private SetupConsole $console) {}

    /**
     * The first answer with no problem, or null when there was none.
     *
     * @param  Closure(): string  $ask
     * @param  Closure(string): ?string  $problem  What is wrong with an answer, without a full stop; null when nothing is.
     */
    public function until(Closure $ask, Closure $problem, int $tries = 3): ?string
    {
        for ($attempt = 0; $attempt < $tries; $attempt++) {
            // Not trimmed here: a password is an answer too, and its spaces are
            // part of it. A question whose answer should be trimmed trims it.
            $answer = $ask();
            $wrong = $problem($answer);

            if ($wrong === null) {
                return $answer;
            }

            $this->console->warn($wrong.'.');

            if (! $this->console->isInteractive()) {
                break;
            }
        }

        return null;
    }
}
