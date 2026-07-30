#!/usr/bin/env bash

# Run every CDP driver in workbench/scripts against one preview server.
#
# The drivers are the only gate over Alpine/Livewire behaviour — Pest sees the
# markup, not what the browser does with it — but each one boots its own Chrome
# and expects a server already running, so running "all of them" was a manual
# chore. This does the boring part: one server, sequential runs, a summary.
#
# Each driver gets its own DevTools port here (CHROME_PORT), because their own
# defaults collide — eight of them ask for 9337 — and a collision does not fail
# loudly: the second Chrome cannot bind, the driver connects to the FIRST one,
# and hangs on a page that will never be what it expects. The previous fix for
# that was to pkill every headless Chrome before each driver, which traded a
# collision for a race and made whole sweeps report drivers as failed that had
# never run an assertion.
#
# Usage:
#   bash scripts/verify-drivers.sh              # all of them
#   bash scripts/verify-drivers.sh selection    # only those matching "selection"
#   PREVIEW_PORT=8086 bash scripts/verify-drivers.sh
#   CHROME_PORT_BASE=9700 bash scripts/verify-drivers.sh   # if 9600+ is busy
#   DRIVER_TIMEOUT=300 bash scripts/verify-drivers.sh
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

server_up() {
    curl -fsS "http://127.0.0.1:${PREVIEW_PORT}/previews/forms-overview" >/dev/null 2>&1
}

# Make sure a preview server is answering, starting one if not.
#
# Checked before EVERY driver, not just once. `testbench serve` is a single
# process PHP dev server and it does fall over mid-sweep; when it did, the
# remaining 37 drivers all "failed" with Alpine never booting, because their
# pages were never served. One crash used to cost a whole sweep and read as 37
# separate regressions.
ensure_server() {
    server_up && return 0

    if [[ -n "$SERVER_PID" ]]; then
        echo "  ..   preview server stopped answering — restarting it"
        kill -9 "$SERVER_PID" 2>/dev/null
        wait "$SERVER_PID" 2>/dev/null
    fi

    vendor/bin/testbench serve --host=127.0.0.1 --port="$PREVIEW_PORT" >>/tmp/wire-verify-drivers.log 2>&1 &
    SERVER_PID="$!"
    server_restarts=$((server_restarts + 1))

    for _ in $(seq 1 40); do
        server_up && return 0
        sleep 1
    done

    return 1
}

server_restarts=0

if server_up; then
    echo "Using the preview server already on :${PREVIEW_PORT}"
else
    echo "Starting a preview server on :${PREVIEW_PORT}"
    if ! ensure_server; then
        echo "The preview server never came up — see /tmp/wire-verify-drivers.log" >&2
        exit 1
    fi
    server_restarts=0
fi

echo
pass=0
failed=()

# Whatever is listening on a TCP port, or nothing.
listeners_on() {
    lsof -ti "tcp:$1" -sTCP:LISTEN 2>/dev/null || true
}

# Make a DevTools port usable, or give up on it.
#
# A driver that was interrupted leaves its Chrome behind, and the next driver on
# that port does NOT fail loudly: its own Chrome cannot bind, so it connects to
# the SURVIVOR and then waits forever on a page that will never be what it
# expects. This used to be handled by pkill-ing every headless Chrome before each
# driver, which is a race by construction — it kills browsers belonging to a
# concurrent sweep, or to a driver someone is running by hand, and a sweep that
# is itself pkill-ed mid-run reports its remaining drivers as failures that never
# executed a single assertion.
#
# Kill only what holds this one port, then wait for the kernel to release it.
free_port() {
    local port="$1" pids

    pids="$(listeners_on "$port")"
    [[ -n "$pids" ]] && kill -9 $pids 2>/dev/null

    for _ in $(seq 1 20); do
        [[ -z "$(listeners_on "$port")" ]] && return 0
        sleep 0.25
    done

    return 1
}

run_driver() {
    # Runs one driver, sets: code, out. A hung driver must not hang the sweep;
    # hand-rolled rather than timeout(1), which macOS does not ship.
    local f="$1" port="$2" limit="${DRIVER_TIMEOUT:-180}" tmp driver_pid watchdog_pid

    tmp="$(mktemp)"
    CHROME_PORT="$port" node "$f" >"$tmp" 2>&1 &
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

    # Leave nothing for the next driver to trip over.
    local pids
    pids="$(listeners_on "$port")"
    [[ -n "$pids" ]] && kill -9 $pids 2>/dev/null

    return 0
}

# The drivers' own defaults collide — eight of them ask for 9337 — so give each
# one a port of its own for the length of the sweep instead. They all honour
# CHROME_PORT.
port_base="${CHROME_PORT_BASE:-9600}"
index=0

for f in "${drivers[@]}"; do
    name="$(basename "$f" .mjs)"
    name="${name#verify-}"
    port=$((port_base + index))
    index=$((index + 1))

    if ! ensure_server; then
        printf "  FAIL  %-26s %s\n" "$name" "preview server would not come back up"
        failed+=("$name")
        continue
    fi

    if ! free_port "$port"; then
        printf "  FAIL  %-26s %s\n" "$name" "port ${port} never freed"
        failed+=("$name")
        continue
    fi

    run_driver "$f" "$port"

    # Retry once, on a fresh port, when the run says nothing about the code under
    # test: the driver never reached an assertion, or the server died underneath
    # it (every check after that point fails for the same irrelevant reason). A
    # driver that ran and reported a real FAIL against a live server is never
    # retried — that is the signal we are here for.
    checks_ran="$(echo "$out" | grep -acE "^(PASS|FAIL)" || true)"
    if [[ $code -ne 0 ]] && { [[ "$checks_ran" -eq 0 ]] || ! server_up; }; then
        if ensure_server; then
            port=$((port_base + 100 + index))
            if free_port "$port"; then
                retried=" (retried)"
                run_driver "$f" "$port"
                checks_ran="$(echo "$out" | grep -acE "^(PASS|FAIL)" || true)"
            fi
        fi
    fi

    # Report what the run actually did. The old version grepped out the LAST
    # summary-looking line regardless of exit code, so a driver that printed
    # "15/15 checks passed" and then threw was reported as
    # "FAIL … 15/15 checks passed" — the count says it all went well and the
    # verdict says otherwise. Trust the summary only on a clean exit.
    if [[ $code -eq 0 ]]; then
        summary="$(echo "$out" | grep -aoE "[0-9]+/[0-9]+ checks passed|All [0-9]+ checks passed" | tail -1)"
        # A few drivers signal only through their exit code.
        [[ -z "$summary" ]] && summary="ran, no summary (exit=0)"
        printf "  ok    %-26s %s%s\n" "$name" "$summary" "${retried:-}"
        pass=$((pass + 1))
    else
        if [[ "$checks_ran" -eq 0 ]]; then
            summary="never ran a check (exit=${code})"
        else
            summary="aborted after ${checks_ran} check(s) (exit=${code})"
        fi
        printf "  FAIL  %-26s %s%s\n" "$name" "$summary" "${retried:-}"
        failed+=("$name")
        echo "$out" | grep -aE "^FAIL|DRIVER ERROR" | head -5 | sed 's/^/            /'
    fi
    unset retried
done

echo
# Surface this rather than letting the restarts quietly absorb it: a dev server
# that keeps dying is worth knowing about even when every driver ends up green.
if [[ $server_restarts -gt 0 ]]; then
    echo "note: the preview server was restarted ${server_restarts}× mid-sweep (see /tmp/wire-verify-drivers.log)"
fi

if [[ ${#failed[@]} -eq 0 ]]; then
    echo "All ${pass} driver(s) passed."
    exit 0
fi

echo "${pass} passed, ${#failed[@]} failed: ${failed[*]}"
exit 1
