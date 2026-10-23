<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use Illuminate\Http\Request;

/**
 * Whether a poll tick is the only thing this request asks of a component.
 *
 * A tick answers with the data alone — rows, or the data region — and skips the
 * component's render. That is only right when nothing else rides in the same
 * request: Livewire pools what one component queued in one tick into one commit,
 * so a tick can arrive beside a field update in an open modal, a per-page change
 * or a cell save, and each of those wants the render a skip would swallow.
 * Livewire decides the skip after the LAST call, so the tick cannot see a call
 * queued after it — which is why this reads the whole commit from the request
 * rather than tracking what has run so far.
 *
 * Anything it cannot read means "not alone": the tick then renders like any
 * other request, which is the behaviour from before it learned to stay small.
 */
final class PollCommit
{
    /**
     * @param  list<string>  $pollMethods  the calls a tick is made of
     */
    public static function isAlone(string $componentId, array $pollMethods, ?Request $request = null): bool
    {
        $request ??= request();

        foreach ((array) $request->input('components', []) as $commit) {
            if (! is_array($commit) || ! is_string($commit['snapshot'] ?? null)) {
                continue;
            }

            $snapshot = json_decode($commit['snapshot'], true);

            if (! is_array($snapshot) || ($snapshot['memo']['id'] ?? null) !== $componentId) {
                continue;
            }

            if (! empty($commit['updates'])) {
                return false;
            }

            foreach ((array) ($commit['calls'] ?? []) as $call) {
                if (! in_array($call['method'] ?? null, $pollMethods, true)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }
}
