#!/usr/bin/env bash

# Run every CDP driver in workbench/scripts against one preview server.
#
# The drivers are the only gate over Alpine/Livewire behaviour — Pest sees the
# markup, not what the browser does with it — but each one boots its own Chrome
# and expects a server already running, so running "all of them" was a manual
# chore. This does the boring part: one server, sequential runs (they each grab
# a DevTools port and a fresh profile), a summary at the end.
#
# Usage:
#   bash scripts/verify-drivers.sh              # all of them
#   bash scripts/verify-drivers.sh selection    # only those matching "selection"
#   PREVIEW_PORT=8086 bash scripts/verify-drivers.sh
#
# Exit 0 = every driver passed; 1 = at least one failed.

set -uo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

PREVIEW_PORT="${PREVIEW_PORT:-8085}"
FILTER="${1:-}"

drivers=()
for f in workbench/scripts/verify-*.mjs; do
    name="$(basename "$f" .mjs)"
    name="${name#verify-}"
    if [[ -z "$FILTER" || "$name" == *"$FILTER"* ]]; then
        drivers+=("$f")
    fi
done

if [[ ${#drivers[@]} -eq 0 ]]; then
    echo "No drivers match '${FILTER}'." >&2
    exit 1
fi

SERVER_PID=""
cleanup() {
    if [[ -n "$SERVER_PID" ]] && kill -0 "$SERVER_PID" 2>/dev/null; then
        kill "$SERVER_PID" 2>/dev/null || true
        wait "$SERVER_PID" 2>/dev/null || true
    fi
}
trap cleanup EXIT

# Reuse a server that is already up; otherwise start one and own it.
if curl -fsS "http://127.0.0.1:${PREVIEW_PORT}/previews/forms-overview" >/dev/null 2>&1; then
    echo "Using the preview server already on :${PREVIEW_PORT}"
else
    echo "Starting a preview server on :${PREVIEW_PORT}"
    vendor/bin/testbench serve --host=127.0.0.1 --port="$PREVIEW_PORT" >/tmp/wire-verify-drivers.log 2>&1 &
    SERVER_PID="$!"

    for _ in $(seq 1 40); do
        curl -fsS "http://127.0.0.1:${PREVIEW_PORT}/previews/forms-overview" >/dev/null 2>&1 && break
        sleep 1
    done

    if ! curl -fsS "http://127.0.0.1:${PREVIEW_PORT}/previews/forms-overview" >/dev/null 2>&1; then
        echo "The preview server never came up — see /tmp/wire-verify-drivers.log" >&2
        exit 1
    fi
fi

echo
pass=0
failed=()

for f in "${drivers[@]}"; do
    name="$(basename "$f" .mjs)"
    name="${name#verify-}"

    # Each driver spawns its own Chrome on a fixed DevTools port and kills it on
    # the way out — but a driver that was interrupted leaves one behind, and the
    # next run on that port CONNECTS TO THE SURVIVOR instead of its own browser
    # and then waits forever on a page that will never be what it expects. Clear
    # any stragglers first; this owns the whole sweep, so nothing else is using
    # a headless Chrome right now.
    pkill -f "Google Chrome.*--headless=new.*remote-debugging-port" 2>/dev/null || true

    # A hung driver must not hang the sweep. Hand-rolled rather than timeout(1),
    # which macOS does not ship.
    limit="${DRIVER_TIMEOUT:-180}"
    tmp="$(mktemp)"
    node "$f" >"$tmp" 2>&1 &
    driver_pid=$!
    ( sleep "$limit"; kill -9 "$driver_pid" 2>/dev/null ) &
    watchdog_pid=$!
    wait "$driver_pid"
    code=$?
    kill "$watchdog_pid" 2>/dev/null
    wait "$watchdog_pid" 2>/dev/null
    out="$(cat "$tmp")"
    rm -f "$tmp"
    [[ $code -eq 137 ]] && out="${out}"$'\n'"DRIVER ERROR: killed after ${limit}s"

    # Most drivers print a summary; a few only signal through their exit code.
    summary="$(echo "$out" | grep -aoE "[0-9]+/[0-9]+ checks passed|All [0-9]+ checks passed|[0-9]+ check\(s\) FAILED" | tail -1)"
    [[ -z "$summary" ]] && summary="exit=${code}"

    if [[ $code -eq 0 ]]; then
        printf "  ok    %-26s %s\n" "$name" "$summary"
        pass=$((pass + 1))
    else
        printf "  FAIL  %-26s %s\n" "$name" "$summary"
        failed+=("$name")
        echo "$out" | grep -aE "^FAIL|DRIVER ERROR" | head -5 | sed 's/^/            /'
    fi
done

echo
if [[ ${#failed[@]} -eq 0 ]]; then
    echo "All ${pass} driver(s) passed."
    exit 0
fi

echo "${pass} passed, ${#failed[@]} failed: ${failed[*]}"
exit 1
